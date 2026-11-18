# Déchargement cacao — Date obligatoire, administration et traçabilité des lots

Ce lot de modifications s'applique **directement dans vos fichiers existants** : rien n'a été renommé
ni déplacé, le thème cacao, la sécurité, le courrier, les bons de sortie, les réunions et Infomaniak
restent intacts.

> Je n'ai pas pu compiler/rendre dans votre environnement (pas de PHP, pas de base de données).
> La validation se fait chez vous avec les commandes de la section « Validation locale ».

---

## 1. La date de déchargement devient OBLIGATOIRE à l'import

À l'import d'un fichier Excel/CSV :

- la colonne **« Date de déchargement »** fait partie des colonnes obligatoires (avec *Code du bon*,
  *Date* et *PoidsNet*) : si elle est absente du fichier, l'analyse s'arrête avec un message clair ;
- une ligne dont la date de déchargement est **vide** ou **illisible** est **rejetée** dans le rapport
  (« Date de déchargement manquante (obligatoire) » / « … illisible ») ;
- garde-fou supplémentaire côté serveur : même si quelqu'un modifie la page dans son navigateur, une
  ligne sans date de déchargement n'est **jamais écrite en base** ;
- la page d'import affiche un encart d'information et les textes d'aide mentionnent les 4 colonnes
  obligatoires (le texte « les colonnes obligatoires sont reconnues » a été mis à jour).

La colonne en base reste autorisée à vide : **les bons déjà importés ne sont pas cassés**.
Dans la consultation, un bon sans date affiche une pastille « à compléter » et l'administrateur
peut renseigner la date en le modifiant.

## 2. L'administrateur peut modifier et supprimer un déchargement

Depuis la page de consultation des déchargements, une colonne **Actions** apparaît pour le profil
administrateur :

- **Modifier** ouvre un formulaire complet (bon, dates, site, produit, coopérative, camion,
  transporteur, traitant, sacs, poids net, PNP, statut, catégorie, observation) ;
- contrôles appliqués : code obligatoire et **unique**, date de déchargement obligatoire, poids net > 0,
  sacs non négatifs, date de déchargement non antérieure à la date du bon ;
- l'auteur et la date de dernière modification sont enregistrés ;
- **Supprimer** demande une confirmation puis retire le bon.

**Suppression refusée** si le camion alimente déjà un ou plusieurs lots fabriqués : le message donne les
numéros de lots concernés. Il faut d'abord retirer l'association depuis la fiche du lot.
Les profils non administrateurs ne voient ni la colonne ni les routes (accès refusé).

## 3. Traçabilité des lots fabriqués (fèves) ↔ camions déchargés

Nouvelle entrée de menu **Traçabilité → Traçabilité des lots (fèves)**.

### Liste des lots
- recherche par numéro de lot / qualité, filtre *Tous / Lots tracés / Lots à rattacher*, pagination ;
- pour chaque lot : date de production, produit, qualité, poids théorique, **nombre de camions**,
  **poids affecté** et une **barre de couverture** (poids affecté / poids théorique) ;
- compteurs en tête : lots tracés, camions rattachés, poids affecté total.

### Fiche d'un lot
- camions déjà rattachés avec leur poids net, le **poids affecté au lot**, les sacs et une observation ;
- alerte si la couverture est inférieure à 95 % (avec les kilos restant à rattacher) ;
- **zone de rattachement** : recherche d'un bon (code, coopérative, immatriculation), cases à cocher,
  poids prérempli avec le poids net du camion (ajustable), sacs et observation ; on peut rattacher
  plusieurs camions d'un seul coup ;
- bouton **Retirer** sur chaque camion (association erronée) ;
- bouton **Imprimer la fiche** (mise en page d'impression dédiée).

### Vue inverse
En cliquant sur le code d'un bon (consultation ou fiche de lot) : la liste des lots fabriqués avec ce
camion, avec les poids affectés.

Les relations sont stockées dans une nouvelle table dédiée (association plusieurs-à-plusieurs enrichie
du poids affecté, des sacs, d'une observation et de l'auteur). Un même camion peut alimenter plusieurs
lots (scission) et un lot peut regrouper plusieurs camions (mélange).

---

## Installation

1. Décompressez l'archive et **copiez les fichiers sur votre projet** en conservant l'arborescence
   (les fichiers fournis remplacent les vôtres, aucun autre fichier n'est à déplacer).
2. Créez la table de traçabilité :

```bash
php bin/console cache:clear
php bin/console doctrine:migrations:migrate
```

Si la commande de migration n'est pas disponible sur votre serveur, exécutez à la place le script SQL
fourni : `sql/2026-11-18_tracabilite_lots.sql` (idempotent, `IF NOT EXISTS`), ou :

```bash
php bin/console doctrine:schema:update --force
```

3. Videz le cache du navigateur (**Ctrl+F5**) : la page d'import charge le script d'import mis à jour.

## Fichiers concernés

| Fichier | Rôle |
|---|---|
| `src/Service/Stock/DechargementCacaoImportService.php` | date de déchargement obligatoire (colonnes + contrôle par ligne + garde à l'enregistrement) |
| `src/Controller/Stock/DechargementCacaoController.php` | routes de modification et de suppression (administrateur) |
| `src/Controller/Stock/TracabiliteLotController.php` | liste des lots, fiche du lot, rattachement, retrait, vue inverse |
| `src/Entity/Stock/LotDechargement.php` | association lot ↔ déchargement (poids affecté, sacs, observation, auteur) |
| `src/Repository/Stock/LotDechargementRepository.php` | requêtes et statistiques de traçabilité |
| `migrations/Version20261118090000.php` | création de la table et des index/contraintes |
| `sql/2026-11-18_tracabilite_lots.sql` | script SQL de secours |
| `templates/dechargement_cacao/edit.html.twig` | formulaire de modification du bon |
| `templates/stock/tracabilite_lots.html.twig` | liste des lots + couverture |
| `templates/stock/tracabilite_lot.html.twig` | fiche du lot + rattachement des camions |
| `templates/stock/tracabilite_dechargement.html.twig` | vue inverse (camion → lots) |
| `templates/dechargement_cacao/consultation.html.twig` | colonne Actions (administrateur), lien traçabilité |
| `templates/dechargement_cacao/index.html.twig` | encart « date obligatoire » |
| `templates/dechargement_cacao/_styles.html.twig` | styles formulaires, couverture, actions, impression |
| `templates/base.html.twig` | entrée de menu « Traçabilité des lots (fèves) » |
| `assets/dechargement_cacao/import.js` + `public/assets/dechargement/import.js` | textes des 4 colonnes obligatoires (copies synchronisées) |

## Validation locale

```bash
php bin/console cache:clear
php bin/console doctrine:migrations:migrate
php bin/console doctrine:schema:validate
php bin/console debug:router | grep -i tracabilite
```

Parcours à tester :
1. importer un fichier **sans** colonne « Date de déchargement » → analyse bloquée avec message ;
2. importer un fichier avec une ligne sans date → ligne rejetée dans le rapport ;
3. consultation → colonne **Actions** visible seulement en administrateur, modifier puis supprimer un bon ;
4. traçabilité → ouvrir un lot, cocher 2 camions, rattacher, vérifier la couverture ;
5. tenter de supprimer un camion rattaché → refus avec le numéro de lot ;
6. cliquer sur un code de bon → voir les lots alimentés.

En cas d'erreur, consultez `var/log/dev.log` (ou `prod.log`).
