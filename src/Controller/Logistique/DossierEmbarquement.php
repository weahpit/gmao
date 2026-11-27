<?php

namespace App\Controller\Logistique;

use App\Entity\Logistique\Client;
use App\Entity\Logistique\Dossier;
use App\Entity\Logistique\Destination;
use App\Entity\Logistique\Navire;
use App\Entity\Logistique\Proprietaire;
use App\Entity\Production\Produit;
use App\Repository\Logistique\DossierRepository;
use App\Service\Logistique\EmbarquementManager;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Dossiers d'embarquement (shipping).
 *
 *  - /logistique/embarquements            : liste des dossiers (filtres + compteurs)
 *  - /logistique/embarquements/{id}/statut : clôture / réouverture d'un dossier
 *  - /logistique/enregistrement-dossier   : formulaire de création (page existante)
 *
 * Les regles metier vivent dans EmbarquementManager : ce controleur ne fait
 * que lire la requete, appeler le service et afficher le resultat.
 */
#[IsGranted('ROLE_USER')]
class DossierEmbarquement extends AbstractController
{
    public function __construct(
        private readonly DossierRepository $dossiers,
        private readonly EmbarquementManager $embarquements,
    ) {}

    // ------------------------------------------------------------------
    //  Liste des dossiers d'embarquement
    // ------------------------------------------------------------------
    #[Route('logistique/embarquements', name: 'embarquements_index', methods: ['GET'])]
    public function liste(Request $request, \App\Service\Logistique\IndicateursDossier $indicateurs): Response
    {
        $autorise=false;foreach (['ROLE_ADMIN','ROLE_DG','ROLE_DL','ROLE_SHIPPING','ROLE_AGENT_SHIPPING','AGENT_SHIPPING','ROLE_LOGISTIQUE','ROLE_LOGISTIQU'] as $role) if ($this->isGranted($role)) $autorise=true;
        if (!$autorise) throw $this->createAccessDeniedException();
        $filtres = $this->embarquements->normaliserFiltres([
            'q'      => $request->query->get('q'),
            'statut' => $request->query->get('statut'),
            'navire' => $request->query->get('navire'),
            'du'     => $request->query->get('du'),
            'au'     => $request->query->get('au'),
        ]);

        $page = max(1, (int) $request->query->get('page', 1));
        $resultat = $this->embarquements->rechercher($filtres, $page);

        return $this->render('logistique/embarquement/liste.html.twig', [
            'dossiers' => $resultat['dossiers'],
            'filtres'  => $filtres,
            'navires'  => $this->embarquements->navires(),
            'total'    => $resultat['total'],
            'page'     => $resultat['page'],
            'nbPages'  => $resultat['nbPages'],
            'parPage'  => $resultat['parPage'],
            'stats'    => $this->embarquements->statistiques(),
            'avancements'=> $indicateurs->pourDossiers($resultat['dossiers']),
        ]);
    }

    // ------------------------------------------------------------------
    //  Clôturer / rouvrir un dossier
    // ------------------------------------------------------------------
    #[Route('logistique/embarquements/{id}/statut', name: 'embarquement_statut', methods: ['POST'])]
    public function changerStatut(Request $request, string $id): Response
    {
        $autorise = false;
        foreach (['ROLE_ADMIN','ROLE_DG','AGENT_SHIPPING','ROLE_AGENT_SHIPPING','ROLE_LOGISTIQUE','ROLE_LOGISTIQU','ROLE_SHIPPING','ROLE_DL'] as $role) if ($this->isGranted($role)) $autorise = true;
        if (!$autorise) throw $this->createAccessDeniedException();
        try {
            $dossier = $this->dossiers->find(Uuid::fromString($id));
        } catch (\Throwable) {
            $dossier = null;
        }

        if (!$dossier instanceof Dossier) {
            throw $this->createNotFoundException('Dossier d\'embarquement introuvable.');
        }

        if (!$this->isCsrfTokenValid('embarquement_statut_' . $dossier->getId(), (string) $request->request->get('_token'))) {
            if ($request->isXmlHttpRequest()) return $this->json(['message'=>'Jeton invalide.'],403);
            $this->addFlash('danger', 'Action refusée : jeton de sécurité invalide. Rechargez la page et réessayez.');

            return $this->redirigerVersListe($request);
        }

        // La cloture est reservee aux profils DL et Shipping : les autres
        // profils habilites au module peuvent toujours rouvrir un dossier.
        $fermeture = !$dossier->isCloture();
        if ($fermeture && !$this->isGranted('ROLE_DL') && !$this->isGranted('ROLE_SHIPPING')) {
            if ($request->isXmlHttpRequest()) return $this->json(['message'=>'Clôture réservée aux profils DL et Shipping.'],403);
            $this->addFlash('warning', 'Seuls les profils DL et Shipping peuvent clôturer un dossier d\'embarquement.');

            return $this->redirigerVersListe($request);
        }

        $cloture = $this->embarquements->basculerCloture($dossier, $this->nomUtilisateur());

        if ($request->isXmlHttpRequest()) return $this->json(['message'=>$cloture ? 'Dossier clôturé.' : 'Dossier rouvert.']);
        $this->addFlash('success', $cloture
            ? 'Dossier ' . $dossier->getNumeroOt() . ' clôturé.'
            : 'Dossier ' . $dossier->getNumeroOt() . ' rouvert.');

        return $this->redirigerVersListe($request);
    }

    // ------------------------------------------------------------------
    //  Formulaire de création (page existante)
    // ------------------------------------------------------------------
    /** Nom complet de l'utilisateur connecte, ou chaine vide. */
    private function nomUtilisateur(): string
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur || !method_exists($utilisateur, 'getNom')) {
            return '';
        }

        $nom = (string) $utilisateur->getNom();
        if (method_exists($utilisateur, 'getPrenoms')) {
            $nom .= ' ' . (string) $utilisateur->getPrenoms();
        }

        return trim($nom);
    }

    private function redirigerVersListe(Request $request): Response
    {
        $retour = (string) $request->request->get('retour', '');
        if ($retour !== '' && str_starts_with($retour, '/logistique/embarquements') && !str_contains($retour, chr(92)) && !str_contains($retour, "\r") && !str_contains($retour, "\n")) {
            return $this->redirect($retour);
        }

        return $this->redirectToRoute('embarquements_index');
    }
}
