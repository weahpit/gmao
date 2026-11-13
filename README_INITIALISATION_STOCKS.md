# Initialisation des stocks après inventaire physique

## Besoin

Après un **inventaire physique**, l'administrateur doit pouvoir **initialiser les types de stock à une date précise**.
Les mouvements **postérieurs** à cette date sont ensuite pris en compte pour obtenir le **stock final**.

## Règle de calcul appliquée

```
Stock final = Quantité inventoriée à la date D
            + Entrées POSTÉRIEURES à D
            - Sorties  POSTÉRIEURES à D
```

- Les mouvements **antérieurs** à la date d'inventaire sont **ignorés** (l'inventaire remplace l'historique jusqu'à cette date).
- Les mouvements **du jour de l'inventaire** sont considérés comme **déjà compris dans le comptage physique** (la date est enregistrée en fin de journée).
- Les **sorties annulées** (`statut = ANNULE`) ne sont **jamais** déduites.
- Si **aucune initialisation** n'existe pour un type de stock, le calcul reste celui d'avant (tous les mouvements sur la période filtrée).

## Ce qui a été ajouté

| Fichier | Rôle |
|---|---|
| `src/Entity/Stock/InitialisationStock.php` | Entité de la table `stock_initialisation` (rubrique, nature, quantité, unité, date d'inventaire, ancien stock, écart, observation, auteur, état) |
| `src/Repository/Stock/InitialisationStockRepository.php` | `actives()`, `plusRecentePour()`, `indexees()`, `historique()` |
| `src/Service/Stock/StockInitialService.php` | **Cœur du calcul** : `apercu()`, `initialiser()`, `desactiver()`, `stockFinal()` |
| `src/Controller/Stock/InitialisationStockController.php` | Page web + API JSON |
| `templates/saisie_stock/initialisation.html.twig` | Écran d'initialisation (thème cacao existant) |
| `src/Migrations/Version20261113120000.php` | Création de la table `stock_initialisation` |
| `SaisieStockRepository` / `SortieStockRepository` | Nouvelles méthodes `findByRubrique($rubrique, $apres)`, `sommeParUniteApres()` |
| `SaisieStockController` + `templates/saisie_stock/index.html.twig` | Ajout du **stock initial**, des **entrées/sorties après inventaire** et du **stock final** dans l'écran « Consultations » |
| `templates/base.html.twig` | Menu « Gestion du stock » → « Déchets et Echantiollonnage » → **Initialisation des stocks** (ADMIN / DG) |

Aucun fichier existant n'a été renommé ni déplacé ; le thème cacao, la sécurité, le courrier, les bons à signer et les modules stock sont conservés.

## Écran administrateur

URL : **`/stock/initialisation`** (route `app_stock_initialisation`)

- Accès : **ROLE_ADMIN** (saisie et désactivation) ; **ROLE_DG** en consultation.
- Formulaire : type de stock (rubrique du référentiel), nature facultative, quantité inventoriée, unité (référentiel `stock_unite`, « Kg » par défaut), **date de l'inventaire** (pas de date future), observation / n° de PV, case « remplaçant ».
- Tableau « Calcul du stock final par type de stock » : stock initial, entrées après inventaire, sorties après inventaire, **stock final Kg** et **stock final Litres**.
- Tableau « Initialisations actives » : quantité, unité, date, ancien stock théorique, **écart** (quantité inventoriée − ancien stock), observation, auteur, bouton **Désactiver**.
- Tableau « Historique » : toutes les initialisations, y compris celles remplacées/désactivées.

Une nouvelle initialisation sur le même type de stock **désactive automatiquement** la précédente (elle reste dans l'historique).

## API JSON (mobile)

Format de réponse identique au reste de l'application : `{ "code": 200, "msg": "...", "data": ... }`.

| Méthode | URL | Description |
|---|---|---|
| POST | `/stock/api/initialisation` | Créer / remplacer une initialisation (ADMIN). Corps JSON : `rubrique`, `nature`, `quantite`, `unite`, `dateInventaire` (AAAA-MM-JJ), `observation`, `remplacer` |
| POST | `/stock/api/initialisation/desactiver` | Désactiver (ADMIN). Corps JSON : `id` |
| GET | `/stock/api/initialisation/apercu` | Détail du calcul + liste des initialisations actives |
| GET | `/stock/api/initialisation/stock-final` | Stock final par type de stock (objet mobile) |

Exemple de réponse `stock-final` :

```json
{
  "code": 200,
  "msg": "OK",
  "data": [
    {
      "rubrique": "Cacao",
      "initialise": true,
      "dateInventaire": "2026-11-10",
      "stockInitial": { "kg": 1200, "litres": 0 },
      "entrees":      { "kg": 350,  "litres": 0 },
      "sorties":      { "kg": 120,  "litres": 0 },
      "stockFinal":   { "kg": 1430, "litres": 0 }
    }
  ]
}
```

## Mise en place (à faire sur votre serveur)

1. Copier les fichiers dans votre projet.
2. Créer la table :
   ```bash
   php bin/console doctrine:migrations:migrate
   ```
   ou, si vous n'utilisez pas les migrations :
   ```bash
   php bin/console doctrine:schema:update --force
   ```
3. Vider le cache :
   ```bash
   php bin/console cache:clear
   ```
4. Lancer :
   ```bash
   symfony server:start
   ```
5. Se connecter en administrateur, ouvrir **Gestion du stock → Déchets et Echantiollonnage → Initialisation des stocks**, puis **Ctrl+F5** pour recharger le menu.

## Vérification rapide de la règle

1. Inventaire du 10/11 : quantité 1 200 Kg.
2. Entrée du 12/11 : 350 Kg.
3. Sortie du 14/11 : 120 Kg.
4. Sortie annulée du 15/11 : 80 Kg (ignorée).

Résultat attendu : **stock final = 1 200 + 350 − 120 = 1 430 Kg**. Les mouvements du 10/11 et antérieurs ne sont pas recomptés.

> Remarque : ce livrable n'a pas été exécuté ici (pas de PHP/Composer/BDD sur l'environnement de génération). La validation se fait avec les commandes ci-dessus sur votre serveur.

---

# Mise à jour du 13/11/2026 — tableau « Consultations » simplifié

## Écran « Consultations des stocks »

Le récapitulatif par type de stock n'affiche plus les six colonnes
`Entrées Kg | Entrées Litres | Sorties Kg | Sorties Litres | Solde Kg | Solde Litres`.
Il affiche désormais **trois colonnes** :

| Type Stock | Entrées | Sorties | Unité |
|---|---|---|---|
| Cacao | 350,00 | 120,00 | Kg |
| Huile de palme | 12,50 | 3,00 | Litres |

- **Entrées** = total des entrées du type de stock **dans son unité**
- **Sorties** = total des sorties du type de stock **dans son unité**
- **Unité** = `Kg` ou `Litres`, détectée d'après les mouvements du type de stock
  (un type de stock qui n'a que des mouvements en Kg affiche `Kg`)

Quand une **initialisation de stock** est active, deux colonnes supplémentaires
apparaissent à droite : **Stock initial** et **Stock final** (toujours dans
l'unité du type de stock).

Le pied de tableau donne deux lignes de totaux : **TOTAL en Kg** (toujours) et,
seulement s'il existe des mouvements en litres, une ligne **TOTAL en Litres**.
Le solde Kg / Litres reste visible dans les cartes du haut de page.

> Aucun fichier n'a été renommé ni déplacé. Le thème cacao, la sécurité, le
> courrier, les bons à signer et les autres modules stock sont inchangés.

## API mobile du courrier (nouveau)

Fichier : `src/Controller/Courrier/CourrierMobileApiController.php`

| Méthode | URL | Rôle |
|---|---|---|
| GET | `/api/courrier/mobile/referentiel` | listes (services, organisme, priorité), agent connecté |
| GET | `/api/courrier/mobile/suggestions?q=...` | autocomplétion correspondant / organisme |
| POST | `/api/courrier/mobile/creer` | enregistrement rapide d'un courrier entrant ou sortant |

Ces réponses acceptent les **deux formats** utilisés par les applications
(`code: 200` + `data`, ou `code: sucess`/`error` + `data`), pour rester
compatible avec l'application mobile.

Le numéro d'enregistrement est **toujours attribué par le serveur** : l'application
mobile ne le saisit pas.

## Alias de route ajouté

`GET /api/stock/initialisation/stock-final` (en plus de
`/api/initialisation/stock-final`) : permet aux applications mobiles qui
utilisent l'un ou l'autre chemin d'obtenir le stock final.
