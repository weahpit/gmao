<?php

declare(strict_types=1);

namespace App\Controller\Scan;

use App\Entity\BonSortie;
use App\Entity\Stock\SortieStock;
use App\Service\Reunion\QrCodeReunion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * PAGE PUBLIQUE DE SCAN QR DES BONS DE SORTIE (guerite / magasin).
 *
 * Le bon imprime porte un QR code qui encode un JSON :
 *   {app: CITRAC, typ: BON_SORTIE | BON_SORTIE_STOCK, ref: ..., tok: ...}
 * Cette page lit ce contenu et affiche la fiche du bon (lignes, signatures,
 * cloture) pour le controle a la sortie des marchandises.
 *
 * Routes (declarees PUBLIQUES dans security.yaml) :
 *   - GET /scan              : page de scan (camera) ou saisie de la reference
 *   - GET /scan/{code}       : meme page, avec un bon deja cible (lien direct)
 *   - GET /api/scan/{code}   : fiche JSON d'un bon (utilisee par la page)
 *
 * Deux familles de bons sont reconnues :
 *   - BON_SORTIE       : App\Entity\BonSortie (mouvements de materiel / PDR)
 *   - BON_SORTIE_STOCK : App\Entity\Stock\SortieStock (sorties de stock par rubrique)
 * Le jeton est cherche dans les deux tables : l'agent n'a donc jamais a
 * preciser de quel type de bon il s'agit.
 *
 * Aucune donnee sensible n'est exposee (pas de prix, pas de photo, pas
 * d'identifiant de connexion) et les routes sont en LECTURE SEULE : la
 * cloture se fait toujours depuis l'application mobile, avec un compte
 * authentifie. Le code du bon sert de cle : il est long et aleatoire.
 */
#[Route('/scan')]
final class ScanBonSortieController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private QrCodeReunion $qr,
    ) {}

    /** Page de scan (camera) + saisie manuelle de la reference du bon. */
    #[Route('', name: 'app_scan_bon', methods: ['GET'], priority: 20)]
    public function page(): Response
    {
        return $this->rendre(null);
    }

    /**
     * Lien direct /scan/<code> (QR imprime sur le bon, ou lien partage) :
     * le bon est affiche tout de suite, la camera reste disponible dessous
     * pour enchainer sur les bons suivants.
     */
    #[Route('/{code}', name: 'app_scan_bon_code', methods: ['GET'], requirements: ['code' => '[A-Za-z0-9_\-\.]{3,80}'])]
    public function pageCode(string $code): Response
    {
        return $this->rendre($code);
    }

    /** Fiche JSON d'un bon (par jeton, ou a defaut par reference). */
    #[Route('/api/scan/{code}', name: 'app_scan_bon_api', methods: ['GET'], priority: 30, requirements: ['code' => '[A-Za-z0-9_\-\.]{3,80}'])]
    public function api(string $code): JsonResponse
    {
        $bon = $this->lireBon($code);
        if ($bon === null) {
            return $this->json([
                'code' => 'warning',
                'msg'  => 'Aucun bon de sortie ne correspond a ce code. Verifiez qu\'il s\'agit bien d\'un bon CITRAC, ou saisissez la reference imprimee sur le bon.',
            ], 404);
        }

        return $this->json([
            'code' => 'success',
            'msg'  => 'Bon trouve.',
            'data' => $this->serialiser($bon),
        ]);
    }

    /** Rendu commun de la page de scan. */
    private function rendre(?string $code): Response
    {
        $cible = ($code !== null && $code !== '') ? $this->lireBon($code) : null;

        return $this->render('scan/bon_sortie.html.twig', [
            'cible_initiale' => $cible !== null ? $this->serialiser($cible) : null,
            'url_api'        => $this->generateUrl('app_scan_bon_api', ['code' => '__CODE__']),
            'url_page_bon'   => $this->generateUrl('app_scan_bon_code', ['code' => '__CODE__']),
            'qr_page'        => $this->qr->dataUri(
                $this->generateUrl('app_scan_bon', [], UrlGeneratorInterface::ABSOLUTE_URL),
                420
            ),
        ]);
    }

    // ------------------------------------------------------------------
    //  Recherche du bon
    // ------------------------------------------------------------------
    /**
     * Recherche par jeton dans les deux tables de bons, puis, en secours,
     * par reference (bons imprimes avant l'ajout de la colonne token).
     */
    private function lireBon(string $code): BonSortie|SortieStock|null
    {
        $code = trim($code);
        if ($code === '' || mb_strlen($code) > 80) {
            return null;
        }

        try {
            $bon = $this->em->getRepository(BonSortie::class)->findOneBy(['token' => $code]);
            if ($bon instanceof BonSortie) {
                return $bon;
            }
        } catch (\Throwable) {
            // Table absente ou base non migree : on poursuit la recherche.
        }

        try {
            $bon = $this->em->getRepository(SortieStock::class)->findOneByToken($code);
            if ($bon instanceof SortieStock) {
                return $bon;
            }
        } catch (\Throwable) {
        }

        try {
            $bon = $this->em->getRepository(BonSortie::class)->findOneBy(['reference' => $code]);
            if ($bon instanceof BonSortie) {
                return $bon;
            }
        } catch (\Throwable) {
        }

        try {
            $bon = $this->em->getRepository(SortieStock::class)->findOneByReference($code);
            if ($bon instanceof SortieStock) {
                return $bon;
            }
        } catch (\Throwable) {
        }

        return null;
    }

    // ------------------------------------------------------------------
    //  Mise en forme pour la page
    // ------------------------------------------------------------------
    /** @return array<string, mixed> */
    private function serialiser(BonSortie|SortieStock $bon): array
    {
        return $bon instanceof SortieStock
            ? $this->serialiserStock($bon)
            : $this->serialiserPdr($bon);
    }

    /** Bon de sortie « stock » (sortie par rubrique : cacao, PDR...). */
    private function serialiserStock(SortieStock $bon): array
    {
        $statut   = (string) ($bon->getStatut() ?: SortieStock::STATUT_EN_ATTENTE);
        $qte      = $bon->getQuantite();
        $quantite = $qte !== null
            ? rtrim(rtrim(number_format((float) $qte, 2, '.', ' '), '0'), '.')
            : '';

        return [
            'type'       => 'BON_SORTIE_STOCK',
            'type_label' => 'Bon de sortie de stock',
            'token'      => (string) $bon->getToken(),
            'reference'  => (string) $bon->getReference(),
            'statut'       => $statut,
            'statut_label' => $bon->getStatutLibelle(),
            'date'       => $bon->getDateSortie()
                ? $bon->getDateSortie()->format('d/m/Y \\a H:i')
                : ($bon->getCreatedAt() ? $bon->getCreatedAt()->format('d/m/Y \\a H:i') : ''),
            'service'    => (string) $bon->getServiceLibelle(),
            'demandeur'  => (string) $bon->getAgentLibelle(),
            'emplacement'=> (string) $bon->getEmplacementLibelle(),
            'etabli_par' => (string) $bon->getAgentLibelle(),
            'signe_par'  => (string) $bon->getSignePar(),
            'signe_at'   => $bon->getSigneAt() ? $bon->getSigneAt()->format('d/m/Y \\a H:i') : '',
            'observation_signature' => (string) $bon->getObservationSignature(),
            'cloture_par' => '',
            'cloture_at'  => '',
            'poste_controle' => '',
            'lignes'     => [[
                'code'        => '',
                'designation' => (string) $bon->getRubrique(),
                'qte'         => trim($quantite . ' ' . (string) $bon->getUnite()),
                'stock_avant' => '',
                'stock_apres' => '',
                'mention'     => (string) $bon->getMotif(),
            ]],
        ];
    }

    /** Bon de sortie « PDR / materiel » (App\Entity\BonSortie). */
    private function serialiserPdr(BonSortie $bon): array
    {
        $lignes = [];
        foreach (($bon->getLignes() ?: []) as $ligne) {
            if (!is_array($ligne)) {
                continue;
            }
            $lignes[] = [
                'code'        => (string) ($ligne['code'] ?? ''),
                'designation' => (string) ($ligne['designation'] ?? ($ligne['libelle'] ?? '')),
                'qte'         => (string) ($ligne['qte'] ?? ''),
                'stock_avant' => (string) ($ligne['stock_avant'] ?? ''),
                'stock_apres' => (string) ($ligne['stock_apres'] ?? ''),
                'mention'     => (string) ($ligne['mention'] ?? ''),
            ];
        }

        return [
            'type'       => 'BON_SORTIE',
            'type_label' => 'Bon de sortie (materiel / PDR)',
            'token'      => (string) $bon->getToken(),
            'reference'  => (string) $bon->getReference(),
            'statut'       => $bon->getStatut(),
            'statut_label' => $bon->getStatutLabel(),
            'date'       => $bon->getCreatedAt() ? $bon->getCreatedAt()->format('d/m/Y \\a H:i') : '',
            'service'    => (string) $bon->getService(),
            'demandeur'  => (string) $bon->getDemandeur(),
            'emplacement'=> '',
            'etabli_par' => (string) $bon->getEtabliPar(),
            'signe_par'  => (string) $bon->getSignePar(),
            'signe_at'   => $bon->getSigneAt() ? $bon->getSigneAt()->format('d/m/Y \\a H:i') : '',
            'observation_signature' => '',
            'cloture_par' => (string) $bon->getCloturePar(),
            'cloture_at'  => $bon->getClotureAt() ? $bon->getClotureAt()->format('d/m/Y \\a H:i') : '',
            'poste_controle' => (string) $bon->getPosteControle(),
            'lignes'     => $lignes,
        ];
    }
}
