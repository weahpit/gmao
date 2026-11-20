<?php

namespace App\Controller\Rh;

use App\Entity\Rh\DocumentRh;
use App\Repository\Rh\CandidatRepository;
use App\Repository\Rh\DocumentRhRepository;
use App\Repository\Rh\EmployeRepository;
use App\Security\RhAcces;
use App\Service\Rh\StockageDocumentRh;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Bibliotheque documentaire RH : pieces du dossier, documents de recrutement
 * et pieces medicales confidentielles.
 */
#[Route('/rh/documents', name: 'rh_documents')]
#[IsGranted('ROLE_USER')]
final class DocumentRhController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private DocumentRhRepository $documents,
        private EmployeRepository $employes,
        private CandidatRepository $candidats,
        private StockageDocumentRh $stockage,
        private RhAcces $acces,
    ) {}

    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if (!$this->acces->estRh() && !$this->acces->peutRecruter()) {
            throw $this->createAccessDeniedException('La gestion documentaire RH est reservee au service RH.');
        }

        $categorie = (string) $request->query->get('categorie', '');
        $categories = $this->categoriesAutorisees();

        if ($categorie !== '' && !array_key_exists($categorie, $categories)) {
            $categorie = '';
        }

        $documents = $this->documents->findRecents($categorie !== '' ? $categorie : null, 200);
        if (!$this->acces->peutVoirMedical()) {
            $documents = array_values(array_filter(
                $documents,
                static fn (DocumentRh $document) => $document->getCategorie() !== DocumentRh::CATEGORIE_MEDICAL
            ));
        }

        return $this->render('rh/documents/index.html.twig', [
            'documents' => $documents,
            'categorie' => $categorie,
            'categories' => $categories,
            'types' => DocumentRh::TYPES,
            'compteurs' => $this->documents->compterParCategorie(),
        ]);
    }

    #[Route('/nouveau', name: '_new', methods: ['GET', 'POST'])]
    public function nouveau(Request $request): Response
    {
        if (!$this->acces->estRh() && !$this->acces->peutRecruter()) {
            throw $this->createAccessDeniedException('Le depot de document RH est reserve au service RH.');
        }

        $document = (new DocumentRh())->setDeposePar($this->acces->utilisateur());
        $categories = $this->categoriesAutorisees();
        $donnees = [
            'categorie' => array_key_first($categories),
            'type' => 'contrat',
            'titre' => '',
            'employe' => '',
            'candidat' => '',
        ];
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rh_document', (string) $request->request->get('_token'))) {
                $erreurs[] = 'Le formulaire a expire. Rechargez la page puis recommencez.';
            } else {
                $donnees = $request->request->all();
                $categorie = (string) ($donnees['categorie'] ?? '');

                if (!array_key_exists($categorie, $categories)) {
                    $erreurs[] = 'Categorie de document non autorisee pour votre profil.';
                }

                $titre = trim((string) ($donnees['titre'] ?? ''));
                if ($titre === '') {
                    $erreurs[] = 'Le titre du document est obligatoire.';
                }

                $employe = null;
                $candidat = null;
                if (($donnees['employe'] ?? '') !== '') {
                    $employe = $this->employes->find((string) $donnees['employe']);
                    if ($employe === null) {
                        $erreurs[] = 'Employe introuvable.';
                    }
                }
                if (($donnees['candidat'] ?? '') !== '') {
                    $candidat = $this->candidats->find((string) $donnees['candidat']);
                }

                if ($employe === null && $candidat === null) {
                    $erreurs[] = 'Rattachez le document a un employe ou a un candidat.';
                }

                $fichier = $request->files->get('fichier');
                if (!$fichier instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                    $erreurs[] = 'Selectionnez le fichier a deposer.';
                }

                if ($erreurs === []) {
                    $document
                        ->setCategorie($categorie)
                        ->setType((string) ($donnees['type'] ?? 'autre'))
                        ->setTitre($titre)
                        ->setEmploye($employe)
                        ->setCandidat($candidat);

                    try {
                        $this->stockage->stocker($fichier, $document);
                        $this->em->persist($document);
                        $this->em->flush();
                    } catch (\RuntimeException $exception) {
                        $erreurs[] = $exception->getMessage();
                    }

                    if ($erreurs === []) {
                        $this->addFlash('success', 'Le document "' . $document->getTitre() . '" est depose.');

                        return $this->redirectToRoute('rh_documents');
                    }
                }
            }
        }

        return $this->render('rh/documents/form.html.twig', [
            'donnees' => $donnees,
            'categories' => $categories,
            'types' => DocumentRh::TYPES,
            'employes' => $this->employes->findActifs(),
            'candidats' => $this->candidats->findTous(),
            'erreurs' => $erreurs,
        ]);
    }

    #[Route('/{id}/telecharger', name: '_telecharger', methods: ['GET'])]
    public function telecharger(string $id): BinaryFileResponse
    {
        $document = $this->documents->find($id);
        if ($document === null) {
            throw $this->createNotFoundException('Document introuvable.');
        }

        $this->controlerAcces($document);

        $chemin = $this->stockage->chemin($document);
        if ($chemin === null || !is_file($chemin)) {
            throw $this->createNotFoundException('Le fichier "' . ($document->getNomOriginal() ?? $document->getId()) . '" est introuvable sur le disque. Contactez l\'administrateur.');
        }

        $reponse = new BinaryFileResponse(new File($chemin));
        $reponse->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $document->getNomOriginal() ?? ('document-' . $document->getId() . '.bin')
        );
        $reponse->headers->set('Content-Type', $document->getMime() ?: 'application/octet-stream');

        return $reponse;
    }

    #[Route('/{id}/supprimer', name: '_supprimer', methods: ['POST'])]
    public function supprimer(Request $request, string $id): Response
    {
        $this->acces->exigerRh();

        $document = $this->documents->find($id);
        if ($document === null) {
            throw $this->createNotFoundException('Document introuvable.');
        }

        if (!$this->isCsrfTokenValid('rh_document_suppression_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Le formulaire a expire. Rechargez la page puis recommencez.');
            return $this->redirectToRoute('rh_documents');
        }

        $titre = $document->getTitre() ?? $document->getNomOriginal();

        try {
            $this->stockage->supprimer($document);
        } catch (\Throwable) {
            // On continue : la reference en base doit disparaitre meme si le disque est inaccessible.
        }

        $this->em->remove($document);
        $this->em->flush();
        $this->addFlash('success', 'Le document "' . $titre . '" est supprime.');

        return $this->redirectToRoute('rh_documents');
    }

    private function controlerAcces(DocumentRh $document): void
    {
        if ($document->getCategorie() === DocumentRh::CATEGORIE_MEDICAL) {
            $this->acces->exigerMedical();
            return;
        }

        if (!$this->acces->estRh() && !$this->acces->peutRecruter()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas acces a ce document.');
        }
    }

    /** @return array<string, string> */
    private function categoriesAutorisees(): array
    {
        $categories = DocumentRh::CATEGORIES;

        if (!$this->acces->peutVoirMedical()) {
            unset($categories[DocumentRh::CATEGORIE_MEDICAL]);
        }

        if (!$this->acces->estRh()) {
            unset($categories[DocumentRh::CATEGORIE_RH]);
        }

        return $categories;
    }
}
