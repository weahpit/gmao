# Tableau de bord par role

Chaque compte dispose d'un tableau de bord adapte a son metier, avec des
graphiques et des chiffres cles, et peut choisir ce qu'il veut voir.

Adresse : `/mon-tableau-de-bord` (entree « Mon tableau de bord » dans le menu).

## 1. Un tableau de bord par role

| Tableau de bord | Roles Symfony | Contenu principal |
|---|---|---|
| Direction | `ROLE_DG`, `ROLE_ADMIN` | 4 indicateurs, activite des 6 mois, effectif, bons, interventions |
| Ressources humaines | `ROLE_RH`, `ROLE_DRH` | effectif, contrats, hommes/femmes, embauches et departs, absences, retards, pointages |
| Stock et magasin | `ROLE_PDR`, `ROLE_SAISIE_STOCK` | seuils, bons, demandes d'achat, entrees/sorties, top rubriques, cacao decharge |
| Logistique et documents | `ROLE_DL`, `ROLE_LOGISTIQUE` | dossiers, documents par modele, etat du parc, bons par service |
| Courrier | `ROLE_COURRIER` | a traiter, statuts, priorites, flux entrants/sortants, services |
| Maintenance | `ROLE_TECHNICIAN` | alertes, interventions par statut et par type, parc, services demandeurs |
| Securite et guerite | `ROLE_VIGIL` | visiteurs presents, sorties du jour, entrees, motifs |
| Sante au travail | `ROLE_MEDECIN`, `ROLE_INFIRMIER` | visites medicales, aptitudes, echeances, accidents du travail |
| Encadrement | `ROLE_CHEF_SERVICE`, `ROLE_MANAGER` | absences a viser, courriers, interventions, effectif, demandes d'achat |
| Mon espace | tout compte (`ROLE_USER`) | fiche, absences, formations, pointages |

Un compte qui cumule plusieurs roles voit un selecteur en haut de page ;
chaque tableau de bord garde sa propre disposition.

## 2. Gerer ses gadgets

Bouton « Personnaliser mes gadgets » :

- **Masquer / Afficher** : le gadget quitte la grille ou y revient.
- **Desactiver / Activer** : le gadget reste visible, grise, et ne calcule plus
  rien (utile pour alleger la page ou eviter une requete couteuse).
- **Monter / Descendre** : change l'ordre d'affichage.
- **Retablir l'affichage par defaut** : efface les choix du compte pour ce role.

Les choix sont enregistres par compte **et** par role, dans la table
`dashboard_preference_gadget`. Un gadget jamais touche reste affiche et actif.

## 3. Installation

La table est creee automatiquement par Doctrine. Au choix :

    php bin/console doctrine:schema:update --force

ou, si vous preferez maitriser le SQL :

    psql -f docs/sql/dashboard_preference_gadget.sql

Verifier ensuite la presence de la route :

    php bin/console debug:router | grep tdb_role

## 4. Ajouter un gadget

Tout est centralise dans `src/Service/Dashboard/RegistreGadgets.php` :

1. ecrire la methode de calcul dans `DonneesTableauDeBord` (chiffre, graphique
   ou fiche) ;
2. ajouter une entree dans le tableau du role concerne :

```php
['code' => 'rh_mon_gadget', 'libelle' => 'Mon indicateur',
 'description' => 'Ce que montre le gadget.', 'type' => 'barres',
 'largeur' => 6, 'calcul' => static fn () => $d->maMethode()],
```

Types disponibles : `chiffre`, `donut`, `barres`, `ligne`, `aire`, `fiche`.
Largeur : 3, 4, 6, 8 ou 12 (grille Bootstrap). Les graphiques utilisent
ApexCharts, deja charge par le gabarit de base : aucune installation.

## 5. Depannage

- **Page vide ou 403** : le compte n'a aucun des roles listes ; il obtient
  automatiquement « Mon espace ».
- **« Donnees indisponibles : ... »** : la cause reelle est affichee dans la
  carte (table absente, colonne renommee). Le reste de la page continue de
  fonctionner.
- **Aucun graphique, message de bibliotheque** : verifier que le script
  ApexCharts du gabarit de base est bien charge (blocage reseau ou filtre
  d'assets).
- **« Jeton de securite invalide »** : la page a ete ouverte trop longtemps ;
  recharger puis recommencer.
