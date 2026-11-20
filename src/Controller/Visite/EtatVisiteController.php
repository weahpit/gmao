<?php

namespace App\Controller\Visite;

use App\Entity\Services;
use App\Entity\Visite\Visite;
use App\Repository\Visite\VisiteRepository;
use App\Services\pdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Etat des visites : apercu a l'ecran et export PDF (periode, nom, service).
 *
 *  - /visites/etat        : filtres + apercu du tableau
 *  - /visites/etat.pdf    : meme etat, au format PDF (FPDF deja utilise par la GMAO)
 */
#[Route('/visites/etat')]
#[IsGranted('ROLE_USER')]
final class EtatVisiteController extends AbstractController
{
    private const LIMITE_APERCU = 300;

    public function __construct(
        private VisiteRepository $visites,
        private EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'app_visites_etat', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filtres = $this->lireFiltres($request);
        $service = $this->serviceDepuisFiltre($filtres['service_id'] ?? $filtres['service'] ?? '');

        $requete = $this->visites->construireRequete([
            'du' => $filtres['du'],
            'au' => $filtres['au'],
            'q' => $filtres['q'],
            'service' => $service,
            'statut' => $filtres['statut'],
        ]);

        $total = (int) (clone $requete)
            ->select('COUNT(v.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $apercu = $requete->setMaxResults(self::LIMITE_APERCU)->getQuery()->getResult();

        return $this->render('visite/etat.html.twig', [
            'filtres' => $filtres,
            'apercu' => $apercu,
            'total' => $total,
            'limiteApercu' => self::LIMITE_APERCU,
            'services' => $this->listeServices(),
            'statuts' => Visite::STATUTS,
            'resume' => $this->resume($filtres, $service, $total),
        ]);
    }

    #[Route('.pdf', name: 'app_visites_etat_pdf', methods: ['GET'])]
    public function pdf(Request $request, pdfService $pdfService): Response
    {
        $filtres = $this->lireFiltres($request);
        $service = $this->serviceDepuisFiltre($filtres['service_id'] ?? $filtres['service'] ?? '');

        $liste = $this->visites->rechercher([
            'du' => $filtres['du'],
            'au' => $filtres['au'],
            'q' => $filtres['q'],
            'service' => $service,
            'statut' => $filtres['statut'],
        ]);

        $lignes = [];
        $presents = 0;
        $minutesTotal = 0;

        foreach ($liste as $visite) {
            /** @var Visite $visite */
            $visiteur = $visite->getVisiteur();
            if ($visite->isEnCours()) {
                ++$presents;
            }
            $minutesTotal += $visite->getDureeMinutes();

            $lignes[] = [
                $visite->getHeureEntree()->format('d/m/Y'),
                $visiteur?->getNomComplet() ?? '-',
                $visiteur?->getPieceComplete() ?? '-',
                (string) ($visiteur?->getSociete() ?? '-'),
                $visite->getServiceLibelle(),
                (string) ($visite->getPersonneVisitee() ?? '-'),
                (string) ($visite->getMotif() ?? '-'),
                $visite->getHeureEntree()->format('H:i'),
                $visite->getHeureSortie()?->format('H:i') ?? '-',
                $visite->getDureeLibelle(),
                $visite->getStatutLibelle(),
            ];
        }

        if ($lignes !== []) {
            $lignes[] = [
                'TOTAL',
                count($liste) . ' visite(s)',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                intdiv($minutesTotal, 60) . ' h ' . str_pad((string) ($minutesTotal % 60), 2, '0', STR_PAD_LEFT),
                $presents . ' present(s)',
            ];
        }

        $contenu = $pdfService->genererRapport(
            'Etat des visites',
            $this->resume($filtres, $service, count($liste)),
            $this->criteres($filtres, $service, count($liste)),
            [[
                'titre' => 'Detail des passages',
                'entetes' => ['Date', 'Visiteur', 'Piece d\'identite', 'Societe', 'Service visite', 'Personne visitee', 'Motif', 'Entree', 'Sortie', 'Duree', 'Statut'],
                'poids' => [8, 17, 13, 13, 14, 13, 12, 6, 6, 6, 7],
                'lignes' => $lignes,
                'align' => ['C', 'L', 'L', 'L', 'L', 'L', 'L', 'C', 'C', 'C', 'C'],
            ]],
            'L'
        );

        $nomFichier = 'etat-visites-' . date('Ymd-Hi') . '.pdf';

        return new Response($contenu, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nomFichier . '"',
        ]);
    }

    /**
     * @return array{du: string, au: string, q: string, service_id: string, statut: string}
     */
    private function lireFiltres(Request $request): array
    {
        $du = trim((string) $request->query->get('du', ''));
        $au = trim((string) $request->query->get('au', ''));
        $statut = trim((string) $request->query->get('statut', ''));

        // Le formulaire envoie "service", l'ecran lit "service_id" : on expose
        // les deux cles pour eviter toute lecture d'une cle absente.
        $service = trim((string) $request->query->get('service', ''));

        return [
            'du' => $this->dateValide($du) ?? (new \DateTimeImmutable('first day of this month'))->format('Y-m-d'),
            'au' => $this->dateValide($au) ?? (new \DateTimeImmutable())->format('Y-m-d'),
            'q' => trim((string) $request->query->get('q', '')),
            'service' => $service,
            'service_id' => $service,
            'statut' => isset(Visite::STATUTS[$statut]) ? $statut : '',
        ];
    }

    private function dateValide(string $valeur): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $valeur);

        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $valeur ? $valeur : null;
    }

    private function serviceDepuisFiltre(string $serviceId): ?Services
    {
        if ($serviceId === '') {
            return null;
        }

        $service = $this->em->getRepository(Services::class)->find($serviceId);

        return $service instanceof Services ? $service : null;
    }

    /** @param array<string, string> $filtres */
    private function resume(array $filtres, ?Services $service, int $total): string
    {
        $parties = [
            'du ' . $this->dateFr($filtres['du']) . ' au ' . $this->dateFr($filtres['au']),
        ];

        if ($filtres['q'] !== '') {
            $parties[] = 'visiteur : "' . $filtres['q'] . '"';
        }
        if ($service !== null) {
            $parties[] = 'service : ' . trim((string) $service->getLibelle());
        }
        if ($filtres['statut'] !== '') {
            $parties[] = 'statut : ' . (Visite::STATUTS[$filtres['statut']] ?? $filtres['statut']);
        }

        return implode(' - ', $parties) . ' - ' . $total . ' visite(s)';
    }

    /**
     * @param array<string, string> $filtres
     *
     * @return list<array{0: string, 1: string}>
     */
    private function criteres(array $filtres, ?Services $service, int $total): array
    {
        $criteres = [
            ['Periode', 'du ' . $this->dateFr($filtres['du']) . ' au ' . $this->dateFr($filtres['au'])],
        ];

        if ($filtres['q'] !== '') {
            $criteres[] = ['Visiteur / recherche', $filtres['q']];
        }
        if ($service !== null) {
            $direction = $service->getCodeDirection()?->getLibelle();
            $criteres[] = ['Service visite', trim(($direction !== null ? $direction . ' / ' : '') . (string) $service->getLibelle())];
        }
        if ($filtres['statut'] !== '') {
            $criteres[] = ['Statut', Visite::STATUTS[$filtres['statut']] ?? $filtres['statut']];
        }
        $criteres[] = ['Nombre de visites', (string) $total];

        return $criteres;
    }

    private function dateFr(string $date): string
    {
        $objet = \DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $objet instanceof \DateTimeImmutable ? $objet->format('d/m/Y') : $date;
    }

    /** @return list<Services> */
    private function listeServices(): array
    {
        try {
            return $this->em->getRepository(Services::class)
                ->createQueryBuilder('s')
                ->leftJoin('s.code_direction', 'd')
                ->addSelect('d')
                ->orderBy('d.libelle', 'ASC')
                ->addOrderBy('s.libelle', 'ASC')
                ->getQuery()
                ->getResult();
        } catch (\Throwable) {
            return [];
        }
    }
}
