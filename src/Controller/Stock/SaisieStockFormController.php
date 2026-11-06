<?php

namespace App\Controller\Stock;

use App\Entity\Services;
use App\Entity\Stock\SaisieStock;
use App\Entity\User;
use App\Repository\Stock\SaisieStockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Page de saisie (entrée) de stock : formulaire (type de stock, quantité,
 * unité, service, photo justificative) + saisies du jour.
 *
 * Pendant web de SortieStockController : même ergonomie, mais pour les ENTRÉES.
 * Les saisies alimentent la page de consultation (/stock/saisies) :
 * solde = entrées (saisies) - sorties.
 *
 * NB : l'entité SaisieStock ne porte pas de contraintes #[Assert] (elle est
 * alimentée surtout par le mobile, qui valide côté API). La validation est
 * donc faite manuellement ici, comme dans ApiSaisieStockController.
 */
#[Route('/stock/saisies/nouvelle')]
final class SaisieStockFormController extends AbstractController
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
        #[Autowire('%photo_saisie_stock%')] private string $dossierPhotos,
    ) {}

    #[Route('', name: 'app_saisie_stock_nouvelle', methods: ['GET', 'POST'])]
    public function index(Request $request, SaisieStockRepository $repo): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        $this->denyAccessUnlessGranted('ROLE_PDR');

        /** @var User $user */
        $user = $this->getUser();

        $saisie = new SaisieStock();
        $saisie->setService($user->getCodeService()); // service de l'agent par défaut
        $erreurs = [];
        $reponse = new Response();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('saisie_stock', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

                return $this->redirectToRoute('app_saisie_stock_nouvelle', [], Response::HTTP_SEE_OTHER);
            }

            $this->hydrater($saisie, $request, $user);
            $photo = $request->files->get('photo');
            $erreurs = $this->valider($saisie, $photo);

            if ($erreurs === []) {
                $nomPhoto = null;
                try {
                    if ($photo instanceof UploadedFile) {
                        $nomPhoto = sprintf(
                            'saisie_%s.%s',
                            bin2hex(random_bytes(8)),
                            self::TYPES_IMAGE[(string) $photo->getMimeType()]
                        );
                        $photo->move($this->dossierPhotos, $nomPhoto);
                        $saisie->setPhoto($nomPhoto);
                    }
                    $this->em->persist($saisie);
                    $this->em->flush();

                    $this->addFlash('success', sprintf(
                        'Saisie enregistrée : %s %s — %s.',
                        number_format((float) $saisie->getQuantite(), 2, ',', ' '),
                        $saisie->getUnite(),
                        $saisie->getRubrique()
                    ));

                    return $this->redirectToRoute('app_saisie_stock_nouvelle', [], Response::HTTP_SEE_OTHER);
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

        return $this->render('saisie_stock/saisie.html.twig', [
            'saisie'      => $saisie,
            'erreurs'     => $erreurs,
            'dateValeur'  => $saisie->getDateSaisie()?->format('Y-m-d\\TH:i'),
            'serviceId'   => (string) $saisie->getService()?->getId(),
            'rubriques'   => SaisieStock::RUBRIQUES,
            'unites'      => SaisieStock::UNITES,
            'services'    => $this->em->getRepository(Services::class)->findBy([], ['libelle' => 'ASC']),
            'saisies'     => $repo->findFiltre($aujourdhui, $aujourdhui),
            'dossierPhotos' => $this->dossierPhotos,
        ], $reponse);
    }

    /** Remplit la saisie à partir des champs postés (sans rien valider). */
    private function hydrater(SaisieStock $saisie, Request $request, User $user): void
    {
        $in = $request->request;

        $brut = str_replace([' ', ','], ['', '.'], trim((string) $in->get('quantite')));

        $saisie->setAgent($user);
        $saisie->setAgentLibelle(trim(($user->getPrenoms() ?? '') . ' ' . ($user->getNom() ?? '')));
        $saisie->setRubrique(trim((string) $in->get('rubrique')));
        $saisie->setQuantite(is_numeric($brut) ? (float) $brut : null);
        $saisie->setUnite(trim((string) $in->get('unite')) ?: 'Kg');

        /** @var Services|null $service */
        $service = $this->trouver(Services::class, $in->get('service'));
        $saisie->setService($service);
        $saisie->setServiceLibelle($service?->getLibelle());

        $date = trim((string) $in->get('date_saisie'));
        if ($date === '') {
            $saisie->setDateSaisie(new \DateTime());
        } else {
            try {
                $saisie->setDateSaisie(new \DateTime($date));
            } catch (\Throwable) {
                $saisie->setDateSaisie(null); // signalé par la validation ci-dessous
            }
        }
    }

    /**
     * Valide la saisie (manuellement : l'entité SaisieStock n'a pas d'#[Assert])
     * et la photo.
     *
     * @return array<string,string> message d'erreur par champ
     */
    private function valider(SaisieStock $saisie, mixed $photo): array
    {
        $erreurs = [];

        if (!in_array($saisie->getRubrique(), SaisieStock::RUBRIQUES, true)) {
            $erreurs['rubrique'] = 'Choisissez le type de stock.';
        }
        if ($saisie->getQuantite() === null) {
            $erreurs['quantite'] = 'Quantité invalide.';
        } elseif ($saisie->getQuantite() <= 0) {
            $erreurs['quantite'] = 'La quantité doit être supérieure à zéro.';
        }
        if (!in_array($saisie->getUnite(), SaisieStock::UNITES, true)) {
            $erreurs['unite'] = 'Unité invalide.';
        }
        if ($saisie->getService() === null) {
            $erreurs['service'] = 'Choisissez le service.';
        }
        if ($saisie->getDateSaisie() === null) {
            $erreurs['dateSaisie'] = 'Date invalide.';
        } elseif ($saisie->getDateSaisie() > new \DateTime('tomorrow')) {
            $erreurs['dateSaisie'] = 'La date ne peut pas être dans le futur.';
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
