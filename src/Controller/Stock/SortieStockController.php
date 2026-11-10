<?php

namespace App\Controller\Stock;

use App\Entity\Emplacement;
use App\Entity\Services;
use App\Entity\Stock\SaisieStock;
use App\Entity\Stock\SortieStock;
use App\Entity\User;
use App\Repository\Stock\SortieStockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Page de sortie de stock : formulaire (type de stock, quantité, unité, motif,
 * emplacement, service, photo) + sorties du jour.
 *
 * Les sorties alimentent la page de consultation (/stock/saisies) :
 * solde = entrées (saisies) - sorties.
 */
#[Route('/stock/sorties')]
final class SortieStockController extends AbstractController
{
    /** Types MIME de photo acceptés => extension du fichier stocké. */
    private const TYPES_IMAGE = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private const TAILLE_MAX_PHOTO = 5 * 1024 * 1024; // 5 Mo

    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
        #[Autowire('%photo_sortie_stock%')] private string $dossierPhotos,
    ) {}

    #[Route('', name: 'app_sorties_stock', methods: ['GET', 'POST'])]
    public function index(Request $request, SortieStockRepository $repo): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        $sortie = new SortieStock();
        $sortie->setService($user->getCodeService()); // service de l'agent par défaut
        $erreurs = [];
        $reponse = new Response();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('sortie_stock', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

                return $this->redirectToRoute('app_sorties_stock', [], Response::HTTP_SEE_OTHER);
            }

            $this->hydrater($sortie, $request, $user);
            $photo = $request->files->get('photo');
            $erreurs = $this->valider($sortie, $photo);

            if ($erreurs === []) {
                $nomPhoto = null;
                try {
                    if ($photo instanceof UploadedFile) {
                        $nomPhoto = sprintf(
                            'sortie_%s.%s',
                            bin2hex(random_bytes(8)),
                            self::TYPES_IMAGE[(string) $photo->getMimeType()]
                        );
                        $photo->move($this->dossierPhotos, $nomPhoto);
                        $sortie->setPhoto($nomPhoto);
                    }
                    $this->em->persist($sortie);
                    $this->em->flush();

                    $this->addFlash('success', sprintf(
                        'Sortie enregistrée : %s %s — %s.',
                        number_format((float) $sortie->getQuantite(), 2, ',', ' '),
                        $sortie->getUnite(),
                        $sortie->getRubrique()
                    ));

                    return $this->redirectToRoute('app_sorties_stock', [], Response::HTTP_SEE_OTHER);
                } catch (\Throwable) {
                    if ($nomPhoto !== null) {
                        @unlink(rtrim($this->dossierPhotos, '/') . '/' . $nomPhoto);
                    }
                    $erreurs['_global'] = "Enregistrement impossible, veuillez réessayer.";
                }
            }

            // Formulaire invalide : on le réaffiche avec ses erreurs (422 pour Turbo).
            $reponse->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $aujourdhui = (new \DateTimeImmutable('today'))->format('Y-m-d');

        return $this->render('saisie_stock/sortie.html.twig', [
            'sortie'        => $sortie,
            'erreurs'       => $erreurs,
            'dateValeur'    => $sortie->getDateSortie()?->format('Y-m-d\TH:i'),
            'serviceId'     => (string) $sortie->getService()?->getId(),
            'emplacementId' => (string) $sortie->getEmplacement()?->getId(),
            'rubriques'     => SaisieStock::RUBRIQUES,
            'unites'        => SaisieStock::UNITES,
            'natures'       => SortieStock::NATURES,
            'motifs'        => SortieStock::MOTIFS,
            'services'      => $this->em->getRepository(Services::class)->findBy([], ['libelle' => 'ASC']),
            'emplacements'  => $this->em->getRepository(Emplacement::class)->findBy([], ['libelle' => 'ASC']),
            'sorties'       => $repo->findFiltre($aujourdhui, $aujourdhui),
            'dossierPhotos' => $this->dossierPhotos,
        ], $reponse);
    }

    /** Remplit la sortie à partir des champs postés (sans rien valider). */
    private function hydrater(SortieStock $sortie, Request $request, User $user): void
    {
        $in = $request->request;

        $brut = str_replace([' ', ','], ['', '.'], trim((string) $in->get('quantite')));

        $sortie->setAgent($user);
        $sortie->setAgentLibelle(trim(($user->getPrenoms() ?? '') . ' ' . ($user->getNom() ?? '')));
        $sortie->setRubrique(trim((string) $in->get('rubrique')));
        $sortie->setQuantite(is_numeric($brut) ? (float) $brut : null);
        $sortie->setUnite(trim((string) $in->get('unite')) ?: 'Kg');
        $sortie->setMotif(trim((string) $in->get('motif')));
        $sortie->setNature(trim((string) $in->get('nature')) ?: null);

        /** @var Services|null $service */
        $service = $this->trouver(Services::class, $in->get('service'));
        $sortie->setService($service);
        $sortie->setServiceLibelle($service?->getLibelle());

        /** @var Emplacement|null $emplacement */
        $emplacement = $this->trouver(Emplacement::class, $in->get('emplacement'));
        $sortie->setEmplacement($emplacement);
        $sortie->setEmplacementLibelle($emplacement?->getLibelle());

        $date = trim((string) $in->get('date_sortie'));
        if ($date === '') {
            $sortie->setDateSortie(new \DateTime());
        } else {
            try {
                $sortie->setDateSortie(new \DateTime($date));
            } catch (\Throwable) {
                $sortie->setDateSortie(null); // signalée par la contrainte NotNull
            }
        }
    }

    /**
     * Valide l'entité (contraintes #[Assert] de SortieStock) et la photo.
     *
     * @return array<string,string> message d'erreur par champ
     */
    private function valider(SortieStock $sortie, mixed $photo): array
    {
        $erreurs = [];
        foreach ($this->validator->validate($sortie) as $violation) {
            $erreurs[$violation->getPropertyPath()] ??= (string) $violation->getMessage();
        }

        if ($photo instanceof UploadedFile) {
            if (!$photo->isValid()) {
                $erreurs['photo'] = 'Échec du téléversement de la photo.';
            } elseif (!isset(self::TYPES_IMAGE[(string) $photo->getMimeType()])) {
                $erreurs['photo'] = 'La photo doit être une image (jpg, png, webp).';
            } elseif ($photo->getSize() > self::TAILLE_MAX_PHOTO) {
                $erreurs['photo'] = 'La photo est trop lourde (5 Mo maximum).';
            }
        }

        return $erreurs;
    }

    private function trouver(string $classe, mixed $id): ?object
    {
        $id = trim((string) $id);
        if ($id === '') {
            return null;
        }
        try {
            return $this->em->getRepository($classe)->find(Uuid::fromString($id));
        } catch (\Throwable) {
            return null;
        }
    }
}
