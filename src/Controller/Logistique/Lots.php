<?php

namespace App\Controller\Logistique;

use App\Entity\Logistique\Magasin;
use App\Entity\Logistique\Proprietaire;
use App\Entity\Production\Lot;
use App\Entity\Production\Produit;
use App\Repository\Logistique\MagasinRepository;
use App\Repository\Logistique\ProprietaireRepository;
use App\Repository\Production\LotRepository;
use App\Repository\Production\ProduitRepository;
use App\Repository\Stock\LotDechargementRepository;
use App\Service\Production\GestionLotService;
use App\Service\Production\NatureLot;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * PRODUCTION DE LOTS (cacao).
 *
 * Ecran unique :
 *  - liste filtree / paginee des lots (numero, proprietaire, produit,
 *    emplacement, date, poids net theorique, qualite, camions rattaches) ;
 *  - formulaire de creation / modification d'un lot ;
 *  - scission conforme / non conforme avec controle du poids ;
 *  - suppression d'un lot jamais rattache a un camion.
 *
 * Les anciennes routes API (api/getAllLots, api/saveLot, api/Prod/ScissionLot)
 * restent disponibles pour les autres ecrans et s'appuient sur le meme service.
 */
#[Route('/logistique/ProductionLots')]
class Lots extends AbstractController
{
    private const PAR_PAGE = 25;
    private const SITUATIONS = ['tous', 'avec_camion', 'sans_camion', 'nc', 'conforme', 'scinde', 'grade1', 'grade2', 'sous_grade'];

    public function __construct(
        private readonly LotRepository $lots,
        private readonly ProprietaireRepository $proprietaires,
        private readonly ProduitRepository $produits,
        private readonly MagasinRepository $magasins,
        private readonly LotDechargementRepository $liens,
        private readonly GestionLotService $gestion,
        private readonly NatureLot $nature,
    ) {
    }

    #[Route('', name: 'production_lot', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->ecran($request, $request->query->all());
    }

    #[Route('/Enregistrer', name: 'production_lot_enregistrer', methods: ['POST'])]
    public function enregistrer(Request $request): Response
    {
        if (!$this->peutModifier()) {
            $this->addFlash('danger', 'Votre profil ne permet pas de modifier les lots de production.');

            return $this->redirectToRoute('production_lot');
        }

        if (!$this->isCsrfTokenValid('lot_enregistrer', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée : merci de renvoyer le formulaire.');

            return $this->redirectToRoute('production_lot');
        }

        $donnees = $request->request->all();
        $lot = $this->lotDepuis((string) ($donnees['lot_id'] ?? ''));

        $donnees['proprietaire'] = $this->proprietaire($donnees['proprietaire'] ?? null);
        $donnees['produit'] = $this->produit($donnees['produit'] ?? null);
        $donnees['magasin'] = $this->magasin($donnees['magasin'] ?? null);

        $resultat = $this->gestion->enregistrer($donnees, $lot, $this->utilisateur());

        if (!$resultat['ok']) {
            return $this->ecran($request, $donnees, $resultat['erreurs'], $lot, $this->filtresDepuis($donnees));
        }

        $this->addFlash('success', $resultat['creation']
            ? sprintf('Le lot « %s » a été créé.', $resultat['lot']->getNumeroLot())
            : sprintf('Le lot « %s » a été mis à jour.', $resultat['lot']->getNumeroLot()));

        return $this->redirectToRoute('production_lot', $this->retour($donnees));
    }

    /**
     * Actions groupees sur les lots coches : declaration non conforme ou
     * suppression. Les lots deja rattaches a un dossier ou a un camion sont
     * ignores (aucune perte de tracabilite).
     */
    #[Route('/Selection', name: 'production_lot_selection', methods: ['POST'])]
    public function selection(Request $request): Response
    {
        if (!$this->peutModifier()) {
            $this->addFlash('danger', 'Votre profil ne permet pas de modifier les lots de production.');

            return $this->redirectToRoute('production_lot');
        }

        if (!$this->isCsrfTokenValid('lot_selection', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée : rechargez la page puis recommencez.');

            return $this->redirectToRoute('production_lot');
        }

        $action = (string) $request->request->get('action', 'nc');
        $ids = array_values(array_filter((array) $request->request->all('ids'), static fn ($id): bool => is_string($id) && Uuid::isValid($id)));

        if ($ids === []) {
            $this->addFlash('warning', 'Aucun lot sélectionné.');

            return $this->redirectToRoute('production_lot');
        }

        if ($action === 'supprimer' && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('danger', 'Seul un administrateur peut supprimer des lots.');

            return $this->redirectToRoute('production_lot');
        }

        $traites = 0;
        $ignores = 0;

        foreach ($ids as $id) {
            $lot = $this->lots->find($id);
            if (!$lot instanceof Lot) {
                ++$ignores;

                continue;
            }

            $rattache = !$lot->getCodeDossier()->isEmpty() || $this->liens->compterParLot($lot) > 0;

            if ($action === 'supprimer') {
                if ($rattache) {
                    ++$ignores;

                    continue;
                }
                $this->lots->getEntityManager()->remove($lot);
                ++$traites;

                continue;
            }

            // Seuls les produits finis ont une qualite C / NC : un lot de feves
            // porte un grade (Grade 1, Grade 2, Sous-grade) et n'est pas scinde.
            if (!$this->nature->estFini($lot) || 'NC' === mb_strtoupper((string) $lot->getQualite())) {
                ++$ignores;

                continue;
            }

            $lot->setQualite('NC')->setUpdatedAt(new \DateTimeImmutable())->setUpdatedBy($this->utilisateur());
            ++$traites;
        }

        $this->lots->getEntityManager()->flush();

        if ($action === 'supprimer') {
            $this->addFlash($traites > 0 ? 'success' : 'warning', sprintf('%d lot(s) supprimé(s). %d ignoré(s) car rattaché(s) à un camion ou à un dossier.', $traites, $ignores));
        } else {
            $this->addFlash($traites > 0 ? 'success' : 'warning', sprintf('%d lot(s) déclaré(s) non conforme(s). %d ignoré(s) : déjà non conforme(s), déjà rattaché(s), ou lot de fèves (qualité par grade).', $traites, $ignores));
        }

        return $this->redirectToRoute('production_lot');
    }

    #[Route('/Scission', name: 'production_lot_scission', methods: ['POST'])]
    public function scission(Request $request): Response
    {
        if (!$this->peutModifier()) {
            $this->addFlash('danger', 'Votre profil ne permet pas de scinder un lot.');

            return $this->redirectToRoute('production_lot');
        }

        if (!$this->isCsrfTokenValid('lot_scission', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée : merci de relancer la scission.');

            return $this->redirectToRoute('production_lot');
        }

        $lot = $this->lotDepuis((string) $request->request->get('lot_id', ''));
        if (!$lot instanceof Lot) {
            $this->addFlash('danger', 'Lot introuvable : actualisez la page puis réessayez.');

            return $this->redirectToRoute('production_lot');
        }

        $poids = str_replace([' ', ','], ['', '.'], (string) $request->request->get('poids_nc', ''));
        $resultat = $this->gestion->scinder($lot, is_numeric($poids) ? (float) $poids : 0.0, $this->utilisateur());

        if (!$resultat['ok']) {
            foreach ($resultat['erreurs'] as $erreur) {
                $this->addFlash('danger', $erreur);
            }

            return $this->redirectToRoute('production_lot', $this->retour($request->request->all()) + ['lot' => (string) $lot->getId()]);
        }

        $this->addFlash('success', sprintf(
            'Scission effectuée : %s kg non conformes dans le lot « %s » (%s sacs), %s kg conformes dans « %s » (%s sacs).',
            $this->kg((float) $resultat['poidsNc']),
            $resultat['lotNc']->getNumeroLot(),
            $this->kg((float) $resultat['sacsNc']),
            $this->kg((float) $resultat['lotC']->getPnTh()),
            $resultat['lotC']->getNumeroLot(),
            $this->kg((float) $resultat['lotC']->getNbSacs())
        ));

        return $this->redirectToRoute('production_lot', $this->retour($request->request->all()));
    }

    #[Route('/Supprimer', name: 'production_lot_supprimer', methods: ['POST'])]
    public function supprimer(Request $request): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('danger', 'Seul un administrateur peut supprimer un lot.');

            return $this->redirectToRoute('production_lot');
        }

        if (!$this->isCsrfTokenValid('lot_supprimer', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée : merci de relancer la suppression.');

            return $this->redirectToRoute('production_lot');
        }

        $lot = $this->lotDepuis((string) $request->request->get('lot_id', ''));
        if (!$lot instanceof Lot) {
            $this->addFlash('danger', 'Lot introuvable : actualisez la page puis réessayez.');

            return $this->redirectToRoute('production_lot');
        }

        $resultat = $this->gestion->supprimer($lot);
        $this->addFlash($resultat['ok'] ? 'success' : 'danger', $resultat['message']);

        return $this->redirectToRoute('production_lot', $this->retour($request->request->all()));
    }

    /**
     * Rendu de l'ecran (GET, ou POST invalide pour reafficher les valeurs saisies).
     *
     * @param array<string, mixed> $parametres
     * @param string[]             $erreurs
     * @param array<string, mixed>|null $filtresBruts
     */
    private function ecran(Request $request, array $parametres, array $erreurs = [], ?Lot $lot = null, ?array $filtresBruts = null): Response
    {
        $brut = $filtresBruts ?? $parametres;

        $page = max(1, (int) ($brut['page'] ?? 1));
        $situation = (string) ($brut['situation'] ?? 'tous');
        if (!in_array($situation, self::SITUATIONS, true)) {
            $situation = 'tous';
        }

        $filtres = [
            'q' => trim((string) ($brut['q'] ?? '')),
            'proprietaire' => $this->proprietaire($brut['proprietaire'] ?? null),
            'produit' => $this->produit($brut['produit'] ?? null),
            'magasin' => $this->magasin($brut['magasin'] ?? null),
            'situation' => $situation,
        ];

        $total = $this->lots->compterFiltres($filtres);
        $pages = max(1, (int) ceil($total / self::PAR_PAGE));
        if ($page > $pages) {
            $page = $pages;
        }

        $resultats = $this->lots->rechercher($filtres, self::PAR_PAGE, ($page - 1) * self::PAR_PAGE);

        if (null === $lot && isset($brut['lot'])) {
            $lot = $this->lotDepuis((string) $brut['lot']);
        }

        // Filtres a conserver dans les liens (modifier, pagination).
        $lienFiltres = array_filter([
            'q' => $filtres['q'],
            'proprietaire' => $filtres['proprietaire'] ? (string) $filtres['proprietaire']->getId() : null,
            'produit' => $filtres['produit'] ? (string) $filtres['produit']->getId() : null,
            'magasin' => $filtres['magasin'] ? (string) $filtres['magasin']->getId() : null,
            'situation' => 'tous' === $filtres['situation'] ? null : $filtres['situation'],
        ], static fn ($valeur): bool => null !== $valeur && '' !== (string) $valeur);

        if ($page > 1) {
            $lienFiltres['page'] = $page;
        }

        $liens = $this->liens->statistiques();
        $parLot = [];
        $natures = [];
        foreach ($resultats as $ligne) {
            $parLot[(string) $ligne->getId()] = $liens[(string) $ligne->getId()]['nb'] ?? 0;
            $natures[(string) $ligne->getId()] = $this->nature->natureLot($ligne);
        }

        $produits = $this->produits->findBy([], ['libelle' => 'ASC']);
        $natureProduits = [];
        foreach ($produits as $produit) {
            $natureProduits[(string) $produit->getId()] = $this->nature->natureProduit($produit);
        }

        return $this->render('logistique/lots/index.html.twig', [
            'lots' => $resultats,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'parPage' => self::PAR_PAGE,
            'filtres' => $filtres,
            'lienFiltres' => $lienFiltres,
            'cumuls' => $this->lots->cumuls($filtres),
            'globaux' => $this->lots->totauxGlobaux(),
            'rattachements' => $this->liens->totauxGlobaux(),
            'camionsParLot' => $parLot,
            'proprietaires' => $this->proprietaires->findBy([], ['denomination' => 'ASC']),
            'produits' => $produits,
            'natures' => $natures,
            'natureProduits' => $natureProduits,
            'libellesQualite' => NatureLot::GRADES + NatureLot::QUALITES_FINIES,
            'magasins' => $this->magasins->findBy([], ['libelle' => 'ASC']),
            'lotEdition' => $lot,
            'camionsEdition' => $lot instanceof Lot ? $this->liens->compterParLot($lot) : 0,
            'valeurs' => $this->valeurs($lot, $parametres),
            'erreurs' => $erreurs,
            'peutModifier' => $this->peutModifier(),
            'peutSupprimer' => $this->isGranted('ROLE_ADMIN'),
            'situations' => self::SITUATIONS,
        ]);
    }

    /**
     * Valeurs du formulaire : saisie en cours, sinon lot edite, sinon vide.
     *
     * @param array<string, mixed> $parametres
     *
     * @return array<string, string>
     */
    private function valeurs(?Lot $lot, array $parametres): array
    {
        if (array_key_exists('numero_lot', $parametres)) {
            return [
                'numero_lot' => (string) ($parametres['numero_lot'] ?? ''),
                'proprietaire' => $this->identifiant($parametres['proprietaire'] ?? null),
                'produit' => $this->identifiant($parametres['produit'] ?? null),
                'magasin' => $this->identifiant($parametres['magasin'] ?? null),
                'date_prod' => (string) ($parametres['date_prod'] ?? ''),
                'pn_th' => (string) ($parametres['pn_th'] ?? ''),
                'pb_th' => (string) ($parametres['pb_th'] ?? ''),
                'nb_sacs' => (string) ($parametres['nb_sacs'] ?? ''),
                'qualite' => (string) ($parametres['qualite'] ?? 'C'),
            ];
        }

        if (!$lot instanceof Lot) {
            return [
                'numero_lot' => '',
                'proprietaire' => '',
                'produit' => '',
                'magasin' => '',
                'date_prod' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
                'pn_th' => '',
                'pb_th' => '',
                'nb_sacs' => '',
                'qualite' => 'C',
            ];
        }

        return [
            'numero_lot' => (string) $lot->getNumeroLot(),
            'proprietaire' => (string) $lot->getCodeProprio()?->getId(),
            'produit' => (string) $lot->getCodeProduit()?->getId(),
            'magasin' => (string) $lot->getCodeMagasin()?->getId(),
            'date_prod' => $lot->getDateProd()?->format('Y-m-d') ?? '',
            'pn_th' => null !== $lot->getPnTh() ? (string) $lot->getPnTh() : '',
            'pb_th' => null !== $lot->getPbTht() ? (string) $lot->getPbTht() : '',
            'nb_sacs' => null !== $lot->getNbSacs() ? (string) $lot->getNbSacs() : '',
            'qualite' => (string) ($lot->getQualite() ?: 'C'),
        ];
    }

    /**
     * Filtres a conserver apres une action (redirection).
     *
     * @param array<string, mixed> $donnees
     *
     * @return array<string, string|int>
     */
    private function retour(array $donnees): array
    {
        $retour = [
            'q' => trim((string) ($donnees['filtre_q'] ?? '')),
            'situation' => (string) ($donnees['filtre_situation'] ?? 'tous'),
        ];

        foreach (['proprietaire', 'produit', 'magasin'] as $cle) {
            $valeur = $this->identifiant($donnees['filtre_' . $cle] ?? null);
            if ('' !== $valeur) {
                $retour[$cle] = $valeur;
            }
        }

        $page = (int) ($donnees['filtre_page'] ?? 1);
        if ($page > 1) {
            $retour['page'] = $page;
        }

        return $retour;
    }

    /**
     * @param array<string, mixed> $donnees
     *
     * @return array<string, mixed>
     */
    private function filtresDepuis(array $donnees): array
    {
        return [
            'q' => (string) ($donnees['filtre_q'] ?? ''),
            'proprietaire' => $donnees['filtre_proprietaire'] ?? null,
            'produit' => $donnees['filtre_produit'] ?? null,
            'magasin' => $donnees['filtre_magasin'] ?? null,
            'situation' => (string) ($donnees['filtre_situation'] ?? 'tous'),
            'page' => (string) ($donnees['filtre_page'] ?? 1),
        ];
    }

    private function lotDepuis(string $id): ?Lot
    {
        if ('' === $id || !Uuid::isValid($id)) {
            return null;
        }

        $lot = $this->lots->pourSelection($id);

        return $lot[0] ?? null;
    }

    private function proprietaire(mixed $id): ?Proprietaire
    {
        $id = $this->identifiant($id);

        return '' !== $id ? $this->proprietaires->find($id) : null;
    }

    private function produit(mixed $id): ?Produit
    {
        $id = $this->identifiant($id);

        return '' !== $id ? $this->produits->find($id) : null;
    }

    private function magasin(mixed $id): ?Magasin
    {
        $id = $this->identifiant($id);

        return '' !== $id ? $this->magasins->find($id) : null;
    }

    private function identifiant(mixed $valeur): string
    {
        if ($valeur instanceof Proprietaire || $valeur instanceof Produit || $valeur instanceof Magasin) {
            return (string) $valeur->getId();
        }
        if ($valeur instanceof Uuid) {
            return (string) $valeur;
        }

        $valeur = trim((string) $valeur);

        return Uuid::isValid($valeur) ? $valeur : '';
    }

    private function peutModifier(): bool
    {
        return $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DG');
    }

    private function utilisateur(): string
    {
        $utilisateur = $this->getUser();

        return null !== $utilisateur && method_exists($utilisateur, 'getUserIdentifier')
            ? (string) $utilisateur->getUserIdentifier()
            : 'system';
    }

    private function kg(float $valeur): string
    {
        return number_format($valeur, 0, ',', ' ');
    }
}
