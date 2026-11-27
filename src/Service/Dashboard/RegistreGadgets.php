<?php

namespace App\Service\Dashboard;

use App\Entity\User;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Catalogue des tableaux de bord par role et de leurs gadgets.
 *
 * Chaque gadget est decrit une seule fois ici : libelle, explication, type de
 * graphique, largeur d'affichage et source de donnees. Le controleur ne
 * connait que ce catalogue, ce qui permet d'ajouter un gadget sans toucher au
 * gabarit ni aux routes.
 */
class RegistreGadgets
{
    /**
     * Role metier => roles Symfony qui y donnent acces.
     * L'ordre de ce tableau fixe la priorite quand un compte cumule
     * plusieurs roles.
     */
    private const ROLES = [
        'DIRECTION' => ['ROLE_DG', 'ROLE_ADMIN'],
        'RH' => ['ROLE_RH', 'ROLE_DRH'],
        'LOGISTIQUE' => ['ROLE_DL', 'ROLE_LOGISTIQUE', 'ROLE_LOGISTIQU', 'ROLE_SHIPPING', 'ROLE_AGENT_SHIPPING', 'AGENT_SHIPPING'],
        // Stock cacao : agents de saisie et roles de supervision.
        'STOCK' => ['ROLE_SAISIE_STOCK', 'ROLE_DL', 'ROLE_DG', 'ROLE_ADMIN', 'ROLE_RESP_LABO', 'ROLE_RESP_AQ'],
        // Stock PDR : magasinier et roles de supervision (jamais le cacao).
        'STOCK_PDR' => ['ROLE_PDR', 'ROLE_DL', 'ROLE_DG', 'ROLE_ADMIN', 'ROLE_RESP_AQ', 'ROLE_AQ'],
        'COURRIER' => ['ROLE_COURRIER'],
        'MAINTENANCE' => ['ROLE_TECHNICIAN'],
        'SECURITE' => ['ROLE_VIGIL'],
        'SANTE' => ['ROLE_RH', 'ROLE_MEDICAL'],
        'ENCADREMENT' => ['ROLE_CHEF_SERVICE', 'ROLE_MANAGER'],
        // Accueil des visiteurs : guerite et roles de supervision. Place apres
        // l'encadrement pour ne pas changer le tableau par defaut d'un chef
        // de service.
        'VISITES' => ['ROLE_VIGIL', 'ROLE_COURRIER', 'ROLE_ADMIN', 'ROLE_DG', 'ROLE_DL', 'ROLE_CHEF_SERVICE', 'ROLE_AQ', 'ROLE_RESP_AQ'],
        'MON_ESPACE' => ['ROLE_USER'],
    ];

    public function __construct(private DonneesTableauDeBord $donnees)
    {
    }

    /**
     * Tableaux de bord accessibles au compte connecte.
     *
     * @return array<string, string> cle => libelle
     */
    public function rolesDisponibles(?UserInterface $utilisateur): array
    {
        if (!$utilisateur) {
            return [];
        }

        $rolesCompte = $utilisateur->getRoles();
        $disponibles = [];

        foreach (self::ROLES as $cle => $rolesRequis) {
            foreach ($rolesRequis as $roleRequis) {
                if (in_array($roleRequis, $rolesCompte, true)) {
                    $disponibles[$cle] = $this->definitionRole($cle)['libelle'];
                    break;
                }
            }
        }

        // Un compte sans role fonctionnel garde au moins son espace personnel.
        if ($disponibles === []) {
            $disponibles['MON_ESPACE'] = 'Mon espace';
        }

        return $disponibles;
    }

    public function roleParDefaut(?UserInterface $utilisateur): ?string
    {
        $disponibles = $this->rolesDisponibles($utilisateur);
        if ($disponibles === []) {
            return null;
        }

        return array_key_first($disponibles);
    }

    public function definitionRole(string $role): array
    {
        $roles = $this->roles();

        return $roles[$role] ?? $roles['MON_ESPACE'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function gadgetsPour(string $role): array
    {
        return $this->definitionRole($role)['gadgets'];
    }

    /**
     * @return list<string>
     */
    public function codes(string $role): array
    {
        return array_map(static fn (array $gadget): string => $gadget['code'], $this->gadgetsPour($role));
    }

    public function existe(string $role, string $code): bool
    {
        return in_array($code, $this->codes($role), true);
    }

    public function definitionGadget(string $role, string $code): ?array
    {
        foreach ($this->gadgetsPour($role) as $gadget) {
            if ($gadget['code'] === $code) {
                return $gadget;
            }
        }

        return null;
    }

    /**
     * Catalogue complet. Les fonctions flechees ne sont evaluees qu'au moment
     * de l'affichage du gadget : un gadget masque ou desactive ne coute rien.
     */
    private function roles(): array
    {
        $d = $this->donnees;

        return [
            'DIRECTION' => [
                'libelle' => 'Direction',
                'icone' => 'briefcase',
                'accroche' => "Vue consolidee de l'activite de l'entreprise.",
                'gadgets' => [
                    ['code'=>'dg_avancement_v5','libelle'=>'Avancement des dossiers','description'=>'Même pourcentage que le tableau général ; documents transmis et jalons opérationnels.','type'=>'barres','largeur'=>6,'calcul'=>static fn()=>$d->avancementDossiers()],
                    ['code'=>'dg_cv_v5','libelle'=>'Consommation des CV','description'=>'Accordé, octroyé aux exécutions, consommé (kg).','type'=>'barres','largeur'=>6,'calcul'=>static fn()=>$d->volumeCvParDeblocage(12)],
                    ['code' => 'st_dechargement', 'libelle' => 'Cacao decharge', 'description' => 'Poids net par jour (quatorze jours).', 'type' => 'aire', 'largeur' => 12, 'calcul' => static fn () => $d->dechargementParJour(14)],
                    ['code' => 'dg_effectif', 'libelle' => 'Effectif actif', 'description' => 'Employes en activite.', 'type' => 'chiffre', 'largeur' => 3, 'calcul' => static fn () => $d->effectifActif()],
                    ['code' => 'dg_courriers', 'libelle' => 'Courriers a traiter', 'description' => 'Courriers en attente de traitement.', 'type' => 'chiffre', 'largeur' => 3, 'calcul' => static fn () => $d->courriersATraiter()],
                    ['code' => 'dg_bons', 'libelle' => 'Bons en attente', 'description' => 'Bons de sortie a signer.', 'type' => 'chiffre', 'largeur' => 3, 'calcul' => static fn () => $d->bonsEnAttente()],
                    ['code' => 'dg_demandes', 'libelle' => 'Demandes achat', 'description' => 'Demandes a valider.', 'type' => 'chiffre', 'largeur' => 3, 'calcul' => static fn () => $d->demandesNouvelles()],
                    ['code' => 'dg_activite', 'libelle' => 'Activite des six derniers mois', 'description' => 'Courriers, bons de sortie et interventions.', 'type' => 'ligne', 'largeur' => 8, 'calcul' => static fn () => $d->activiteMensuelle()],
                    ['code' => 'dg_effectif_service', 'libelle' => 'Effectif par service', 'description' => 'Repartition des employes actifs.', 'type' => 'donut', 'largeur' => 4, 'calcul' => static fn () => $d->effectifParService()],
                    ['code' => 'dg_bons_statut', 'libelle' => 'Bons de sortie par statut', 'description' => 'Etat du circuit de validation.', 'type' => 'donut', 'largeur' => 6, 'calcul' => static fn () => $d->bonsParStatut()],
                    ['code' => 'dg_interventions', 'libelle' => 'Interventions par statut', 'description' => 'Charge de maintenance en cours.', 'type' => 'donut', 'largeur' => 6, 'calcul' => static fn () => $d->interventionsParStatut()],
                ],
            ],

            'RH' => [
                'libelle' => 'Ressources humaines',
                'icone' => 'users',
                'accroche' => 'Effectifs, contrats, absences et ponctualite.',
                'gadgets' => [
                    ['code' => 'rh_effectif_service', 'libelle' => 'Effectif par service', 'description' => 'Employes actifs rattaches a chaque service.', 'type' => 'donut', 'largeur' => 4, 'calcul' => static fn () => $d->effectifParService()],
                    ['code' => 'rh_contrats', 'libelle' => 'Contrats en cours par type', 'description' => 'CDI, CDD, stage et interim.', 'type' => 'donut', 'largeur' => 4, 'calcul' => static fn () => $d->contratsParType()],
                    ['code' => 'rh_sexe', 'libelle' => 'Repartition hommes / femmes', 'description' => 'Effectif actif par sexe.', 'type' => 'donut', 'largeur' => 4, 'calcul' => static fn () => $d->effectifParSexe()],
                    ['code' => 'rh_mouvements', 'libelle' => 'Embauches et departs', 'description' => 'Six derniers mois.', 'type' => 'barres', 'largeur' => 8, 'calcul' => static fn () => $d->embauchesDeparts()],
                    ['code' => 'rh_absences', 'libelle' => 'Absences du mois par type', 'description' => 'Conges, maladie, maternite, absences diverses.', 'type' => 'barres', 'largeur' => 4, 'calcul' => static fn () => $d->absencesParType()],
                    ['code' => 'rh_retards', 'libelle' => 'Retards du mois', 'description' => 'Ponctualite issue des pointages.', 'type' => 'chiffre', 'largeur' => 4, 'calcul' => static fn () => $d->retardsMois()],
                    ['code' => 'rh_pointages', 'libelle' => 'Pointages enregistres', 'description' => 'Quatorze derniers jours.', 'type' => 'aire', 'largeur' => 8, 'calcul' => static fn () => $d->pointagesParJour(14)],
                ],
            ],

            'STOCK' => [
                'libelle' => 'Stocks et magasin',
                'icone' => 'package',
                'accroche' => 'Stocks cacao, saisies du jour et suivi des demandes.',
                'gadgets' => [
                    ['code' => 'st_recap', 'libelle' => 'Récapitulatif du stock par produit', 'description' => 'Comme la consultation des stocks : une ligne par type de stock (entrées, sorties, unité, stock initial, stock final).', 'type' => 'tableau', 'largeur' => 12, 'calcul' => static fn () => $d->recapitulatifStockCacao()],
                    ['code' => 'st_jour', 'libelle' => 'Saisies du jour', 'description' => "Détail des entrées de stock cacao enregistrées aujourd'hui.", 'type' => 'tableau', 'largeur' => 12, 'calcul' => static fn () => $d->saisiesDuJour()],
                    ['code' => 'st_demandes', 'libelle' => "Demandes d'achat par statut", 'description' => 'Suivi de la chaine de traitement.', 'type' => 'donut', 'largeur' => 4, 'calcul' => static fn () => $d->demandesParStatut()],
                ],
            ],

            'STOCK_PDR' => [
                'libelle' => 'Stock PDR',
                'icone' => 'wrench',
                'accroche' => 'Pièces de rechange : seuils, mouvements du jour et circuit des bons. Les profils qualite ne voient que la famille EQUIPEMENTS DE PROTECTION INDIVIDUELLE.',
                'gadgets' => [
                    ['code' => 'pdr_seuil', 'libelle' => 'Articles sous le seuil', 'description' => 'Articles dont la quantite atteint le seuil.', 'type' => 'chiffre', 'largeur' => 4, 'calcul' => static fn () => $d->articlesSousSeuil()],
                    ['code' => 'pdr_recap', 'libelle' => 'Récapitulatif du stock PDR', 'description' => 'Stock actuel, seuil et alerte de chaque pièce de rechange.', 'type' => 'tableau', 'largeur' => 12, 'calcul' => static fn () => $d->recapitulatifStockPdr()],
                    ['code' => 'pdr_jour', 'libelle' => 'Mouvements PDR du jour', 'description' => "Entrées et sorties de pièces enregistrées aujourd'hui.", 'type' => 'tableau', 'largeur' => 12, 'calcul' => static fn () => $d->mouvementsPdrDuJour()],
                    ['code' => 'pdr_bons', 'libelle' => 'Bons de sortie par statut', 'description' => 'Attente, validation, cloture, annulation.', 'type' => 'donut', 'largeur' => 4, 'calcul' => static fn () => $d->bonsParStatut()],
                    ['code' => 'pdr_flux', 'libelle' => 'Entrees et sorties', 'description' => "Nombre d'operations par semaine (six semaines).", 'type' => 'barres', 'largeur' => 6, 'calcul' => static fn () => $d->fluxStock()],
                    ['code' => 'pdr_rubriques', 'libelle' => 'Top 5 des rubriques sorties', 'description' => 'Quantites sorties sur trente jours.', 'type' => 'barres', 'largeur' => 6, 'calcul' => static fn () => $d->topRubriquesSorties()],
                    ['code' => 'pdr_demandes', 'libelle' => "Demandes d'achat par statut", 'description' => 'Suivi de la chaîne de traitement magasin.', 'type' => 'donut', 'largeur' => 6, 'calcul' => static fn () => $d->demandesParStatut()],
                ],
            ],

            'LOGISTIQUE' => [
                'libelle' => 'Logistique et production',
                'icone' => 'ship',
                'accroche' => 'Lots de production, embarquements, documents export et parc equipements.',
                'gadgets' => [
                    ['code'=>'lg_avancement_v5','libelle'=>'Avancement des dossiers','description'=>'Même pourcentage que le tableau général ; documents transmis et jalons opérationnels.','type'=>'barres','largeur'=>6,'calcul'=>static fn()=>$d->avancementDossiers()],
                    ['code' => 'lg_embarq_point', 'libelle' => 'Point embarquement', 'description' => 'Dossiers en cours, clôturés, à planifier et tonnage déclaré.', 'type' => 'fiche', 'largeur' => 4, 'calcul' => static fn () => $d->pointGlobalEmbarquement()],
                    ['code' => 'lg_cv_volume', 'libelle' => 'CV de deblocage : volumes', 'description' => 'Volume accorde, octroye aux executions et utilise par les formules (kg).', 'type' => 'barres', 'largeur' => 6, 'calcul' => static fn () => $d->volumeCvParDeblocage(8)],
                    ['code' => 'lg_lots_produit', 'libelle' => 'Poids net disponible par produit', 'description' => 'Somme du poids net disponible des lots, par produit fini.', 'type' => 'barres', 'largeur' => 4, 'calcul' => static fn () => $d->poidsDisponibleParProduit()],
                    ['code' => 'lg_lots_qualite', 'libelle' => 'Lots par qualité', 'description' => 'Répartition des lots de production selon la qualité déclarée.', 'type' => 'donut', 'largeur' => 4, 'calcul' => static fn () => $d->lotsParQualite()],
                    ['code' => 'lg_embarq_recents', 'libelle' => 'Derniers embarquements', 'description' => 'Référence OT, client, produit, destination, navire, poids et statut.', 'type' => 'tableau', 'largeur' => 8, 'calcul' => static fn () => $d->derniersEmbarquements(15)],
                    ['code' => 'lg_embarq_navire', 'libelle' => 'Dossiers par navire', 'description' => 'Navires les plus chargés en nombre de dossiers.', 'type' => 'barres', 'largeur' => 4, 'calcul' => static fn () => $d->embarquementsParNavire()],
                    ['code' => 'lg_lots', 'libelle' => 'Lots de production', 'description' => 'Derniers lots : produit, qualité, magasin, poids net disponible et sacs.', 'type' => 'tableau', 'largeur' => 12, 'calcul' => static fn () => $d->lotsDisponibles(20)],
                    ['code' => 'lg_embarq_dest', 'libelle' => 'Dossiers par destination', 'description' => 'Répartition des dossiers d\'embarquement par destination.', 'type' => 'donut', 'largeur' => 4, 'calcul' => static fn () => $d->embarquementsParDestination()],
                    ['code' => 'lg_dossiers', 'libelle' => 'Dossiers suivis', 'description' => 'Dossiers et documents rattaches.', 'type' => 'chiffre', 'largeur' => 4, 'calcul' => static fn () => $d->dossiersEtDocuments()],
                    ['code' => 'lg_documents', 'libelle' => 'Documents par modele', 'description' => 'Repartition des pieces par modele.', 'type' => 'barres', 'largeur' => 4, 'calcul' => static fn () => $d->documentsParModele()],
                    ['code' => 'lg_equipements', 'libelle' => 'Equipements par etat', 'description' => 'Bon, en panne, en maintenance.', 'type' => 'donut', 'largeur' => 4, 'calcul' => static fn () => $d->equipementsParEtat()],
                    ['code' => 'lg_bons_service', 'libelle' => 'Bons par service demandeur', 'description' => 'Trente derniers jours.', 'type' => 'barres', 'largeur' => 6, 'calcul' => static fn () => $d->bonsParService()],
                    ['code' => 'lg_demandes', 'libelle' => "Demandes d'achat par statut", 'description' => 'Etat des demandes en circulation.', 'type' => 'donut', 'largeur' => 6, 'calcul' => static fn () => $d->demandesParStatut()],
                ],
            ],

            'COURRIER' => [
                'libelle' => 'Courrier',
                'icone' => 'mail',
                'accroche' => 'Flux, delais et repartition par service.',
                'gadgets' => [
                    ['code' => 'co_a_traiter', 'libelle' => 'Courriers a traiter', 'description' => 'En attente de traitement.', 'type' => 'chiffre', 'largeur' => 4, 'calcul' => static fn () => $d->courriersATraiter()],
                    ['code' => 'co_statuts', 'libelle' => 'Courriers par statut', 'description' => 'A traiter, en cours, traite, archive.', 'type' => 'donut', 'largeur' => 4, 'calcul' => static fn () => $d->courriersParStatut()],
                    ['code' => 'co_priorites', 'libelle' => 'Courriers par priorite', 'description' => 'Normale, urgente, tres urgente.', 'type' => 'donut', 'largeur' => 4, 'calcul' => static fn () => $d->courriersParPriorite()],
                    ['code' => 'co_flux', 'libelle' => 'Entrants et sortants', 'description' => 'Six derniers mois.', 'type' => 'ligne', 'largeur' => 8, 'calcul' => static fn () => $d->courriersFluxMois()],
                    ['code' => 'co_services', 'libelle' => 'Courriers par service', 'description' => 'Services les plus sollicites.', 'type' => 'barres', 'largeur' => 4, 'calcul' => static fn () => $d->courriersParService()],
                ],
            ],

            'MAINTENANCE' => [
                'libelle' => 'Maintenance',
                'icone' => 'wrench',
                'accroche' => 'Interventions, alertes et etat du parc.',
                'gadgets' => [
                    ['code' => 'mt_alertes', 'libelle' => 'Alertes ouvertes', 'description' => 'Alertes equipements non traitees.', 'type' => 'chiffre', 'largeur' => 4, 'calcul' => static fn () => $d->alertesOuvertes()],
                    ['code' => 'mt_statuts', 'libelle' => 'Interventions par statut', 'description' => 'Charge en cours et historique.', 'type' => 'donut', 'largeur' => 4, 'calcul' => static fn () => $d->interventionsParStatut()],
                    ['code' => 'mt_types', 'libelle' => 'Preventif et correctif', 'description' => 'Six derniers mois.', 'type' => 'barres', 'largeur' => 4, 'calcul' => static fn () => $d->interventionsParMoisType()],
                    ['code' => 'mt_equipements', 'libelle' => 'Equipements par etat', 'description' => 'Disponibilite du parc.', 'type' => 'donut', 'largeur' => 6, 'calcul' => static fn () => $d->equipementsParEtat()],
                    ['code' => 'mt_services', 'libelle' => 'Demandes par service', 'description' => 'Services demandeurs les plus actifs.', 'type' => 'barres', 'largeur' => 6, 'calcul' => static fn () => $d->interventionsParService()],
                ],
            ],

            'SECURITE' => [
                'libelle' => 'Securite et guerite',
                'icone' => 'shield',
                'accroche' => 'Visiteurs presents, entrees et motifs de visite.',
                'gadgets' => [
                    ['code' => 'vg_presents', 'libelle' => 'Visiteurs presents', 'description' => 'Personnes encore sur site.', 'type' => 'chiffre', 'largeur' => 4, 'calcul' => static fn () => $d->visiteursPresents()],
                    ['code' => 'vg_sorties', 'libelle' => 'Sorties du jour', 'description' => 'Sorties enregistrees a la guerite.', 'type' => 'chiffre', 'largeur' => 4, 'calcul' => static fn () => $d->sortiesJour()],
                    ['code' => 'vg_visites', 'libelle' => 'Entrees par jour', 'description' => 'Sept derniers jours.', 'type' => 'barres', 'largeur' => 4, 'calcul' => static fn () => $d->visitesParJour(7)],
                    ['code' => 'vg_motifs', 'libelle' => 'Motifs de visite', 'description' => 'Trente derniers jours.', 'type' => 'donut', 'largeur' => 6, 'calcul' => static fn () => $d->visitesParMotif()],
                    ['code' => 'vg_flux', 'libelle' => 'Frequentation', 'description' => 'Entrees quotidiennes.', 'type' => 'aire', 'largeur' => 6, 'calcul' => static fn () => $d->visitesParJour(14)],
                ],
            ],

            'VISITES' => [
                'libelle' => 'Visites',
                'icone' => 'door-open',
                'accroche' => "Passages du jour, visiteurs présents et historique des visites.",
                'gadgets' => [
                    ['code' => 'vs_point', 'libelle' => 'Point global', 'description' => 'Présents, entrées et sorties du jour, visites du mois.', 'type' => 'fiche', 'largeur' => 4, 'calcul' => static fn () => $d->pointGlobalVisites()],
                    ['code' => 'vs_jour', 'libelle' => 'Visites du jour', 'description' => "Toutes les entrées enregistrées aujourd'hui, avec l'heure de sortie.", 'type' => 'tableau', 'largeur' => 8, 'calcul' => static fn () => $d->visitesDuJour()],
                    ['code' => 'vs_recap', 'libelle' => 'Récapitulatif par motif', 'description' => 'Trente derniers jours, avec la part de chaque motif.', 'type' => 'tableau', 'largeur' => 6, 'calcul' => static fn () => $d->recapitulatifVisites(30)],
                    ['code' => 'vs_freq', 'libelle' => 'Fréquentation', 'description' => 'Entrées des sept derniers jours.', 'type' => 'barres', 'largeur' => 6, 'calcul' => static fn () => $d->visitesParJour(7)],
                    ['code' => 'vs_details', 'libelle' => 'Détails des visites', 'description' => 'Quinze derniers passages : date, heure, visiteur, motif, service et sortie.', 'type' => 'tableau', 'largeur' => 12, 'calcul' => static fn () => $d->detailsVisites(15)],
                ],
            ],

            'SANTE' => [
                'libelle' => 'Sante au travail',
                'icone' => 'heart-pulse',
                'accroche' => 'Visites medicales et accidents du travail (donnees confidentielles).',
                'gadgets' => [
                    ['code' => 'md_visites', 'libelle' => 'Visites medicales', 'description' => 'Six derniers mois.', 'type' => 'barres', 'largeur' => 6, 'calcul' => static fn () => $d->visitesMedicalesParMois()],
                    ['code' => 'md_aptitudes', 'libelle' => 'Aptitudes', 'description' => 'Apte, apte avec reserve, inapte.', 'type' => 'donut', 'largeur' => 6, 'calcul' => static fn () => $d->aptitudes()],
                    ['code' => 'md_echeances', 'libelle' => 'Echeances a venir', 'description' => 'Controles prevus sous trente jours.', 'type' => 'chiffre', 'largeur' => 4, 'calcul' => static fn () => $d->prochainesVisites()],
                    ['code' => 'md_types', 'libelle' => 'Types de visite', 'description' => 'Periodique, embauche, reprise.', 'type' => 'donut', 'largeur' => 4, 'calcul' => static fn () => $d->typesVisiteMedicale()],
                    ['code' => 'md_accidents', 'libelle' => 'Accidents du travail', 'description' => 'Douze derniers mois, par gravite.', 'type' => 'barres', 'largeur' => 4, 'calcul' => static fn () => $d->accidentsParGravite()],
                    ['code' => 'md_arrets', 'libelle' => 'Arrets de travail', 'description' => 'Jours d\'arret par motif sur douze mois.', 'type' => 'barres', 'largeur' => 6, 'calcul' => static fn () => $d->arretsParType()],
                    ['code' => 'md_arrets_attente', 'libelle' => 'Arrets a instruire', 'description' => 'Demandes d\'arret en attente de decision.', 'type' => 'chiffre', 'largeur' => 3, 'calcul' => static fn () => $d->arretsEnAttente()],
                    ['code' => 'md_plan_vaccinal', 'libelle' => 'Vaccins planifies', 'description' => 'Vaccins inscrits au plan vaccinal de l\'entreprise.', 'type' => 'chiffre', 'largeur' => 3, 'calcul' => static fn () => $d->vaccinsPlanifies()],
                ],
            ],

            'ENCADREMENT' => [
                'libelle' => 'Encadrement',
                'icone' => 'user-check',
                'accroche' => "Demandes de votre equipe et activite des services.",
                'gadgets' => [
                    ['code' => 'en_absences', 'libelle' => 'Absences a viser', 'description' => 'Demandes soumises par les equipes.', 'type' => 'chiffre', 'largeur' => 4, 'calcul' => static fn () => $d->absencesAValider()],
                    ['code' => 'en_courriers', 'libelle' => 'Courriers par statut', 'description' => 'Charge de traitement du courrier.', 'type' => 'donut', 'largeur' => 4, 'calcul' => static fn () => $d->courriersParStatut()],
                    ['code' => 'en_interventions', 'libelle' => 'Interventions par statut', 'description' => 'Demandes de maintenance en cours.', 'type' => 'donut', 'largeur' => 4, 'calcul' => static fn () => $d->interventionsParStatut()],
                    ['code' => 'en_effectif', 'libelle' => 'Effectif par service', 'description' => 'Vue ressources humaines.', 'type' => 'donut', 'largeur' => 6, 'calcul' => static fn () => $d->effectifParService()],
                    ['code' => 'en_demandes', 'libelle' => "Demandes d'achat par statut", 'description' => 'Suivi des besoins de vos services.', 'type' => 'barres', 'largeur' => 6, 'calcul' => static fn () => $d->demandesParStatut()],
                ],
            ],

            'MON_ESPACE' => [
                'libelle' => 'Mon espace',
                'icone' => 'id-card',
                'accroche' => 'Vos informations, vos absences et vos pointages.',
                'gadgets' => [
                    ['code' => 'me_fiche', 'libelle' => 'Ma fiche', 'description' => 'Matricule, service, poste et contrat.', 'type' => 'fiche', 'largeur' => 4, 'calcul' => static fn (?User $utilisateur) => $d->monEspaceFiche($utilisateur ?? throw new \RuntimeException('Utilisateur absent.'))],
                    ['code' => 'me_absences', 'libelle' => 'Mes absences', 'description' => 'Demandes deposees.', 'type' => 'chiffre', 'largeur' => 4, 'calcul' => static fn (?User $utilisateur) => $d->monEspaceAbsences($utilisateur ?? throw new \RuntimeException('Utilisateur absent.'))],
                    ['code' => 'me_formations', 'libelle' => 'Mes formations', 'description' => 'Formations suivies ou planifiees.', 'type' => 'chiffre', 'largeur' => 4, 'calcul' => static fn (?User $utilisateur) => $d->monEspaceFormations($utilisateur ?? throw new \RuntimeException('Utilisateur absent.'))],
                    ['code' => 'me_pointages', 'libelle' => 'Mes pointages', 'description' => 'Presences des quatorze derniers jours.', 'type' => 'barres', 'largeur' => 12, 'calcul' => static fn (?User $utilisateur) => $d->monEspacePointages($utilisateur ?? throw new \RuntimeException('Utilisateur absent.'))],
                ],
            ],
        ];
    }
}
