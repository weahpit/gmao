# Bon de sortie de stock — toutes les rubriques

## 1. Ce qui change

Avant : le circuit de bon de sortie (émission, signature du DG, QR, PDF) ne s'appliquait qu'aux sorties dont la rubrique était le **cacao**.

Maintenant : **toute sortie de stock**, quel que soit le type (cacao, fournitures, EPI, pièces détachées, consommables, etc.), génère automatiquement un bon de sortie :

- une référence unique `BS-AAAAMMJJ-XXXXXX` est attribuée dès l'enregistrement de la sortie ;
- le bon est créé au statut **En cours de validation** ;
- il apparaît dans la page **Bons à signer** (réservée à la Direction Générale) et dans **Bons signés** ;
- il est imprimable en PDF avec QR code, comme avant pour le cacao ;
- les sorties antérieures à cette mise à jour, qui n'ont pas de bon, restent affichées avec « - » dans la colonne du bon : elles restent consultables et ne sont pas modifiées.

La notion de rubrique cacao existe encore dans le code, mais **uniquement à titre d'information métier** : elle ne conditionne plus le circuit de validation.

## 2. Fichiers concernés

- `src/Service/Stock/BonSortieStockService.php` : nouveau service générique (l'ancien service cacao a été remplacé).
- `src/Controller/Stock/BonsSortieStockController.php` : pages « Bons à signer » / « Bons signés », signature, annulation, PDF.
- `templates/mouvements_stock/bons_sortie_a_signer.html.twig` et `bons_sortie_signes.html.twig` : libellés neutralisés (plus de mention cacao).
- `public/js/api/stock/BonSortieStock.js` : signature/annulation côté page (l'ancien nom `window.signerBonCacao` reste disponible pour compatibilité).
- `src/Controller/Stock/SortieStockController.php` et `src/Controller/Api/stock/ApiSortieStockController.php` : émission du bon pour **toutes** les sorties, web et mobile.
- `templates/saisie_stock/sortie.html.twig` : encart explicite « Bon de sortie : toute sortie de stock, quel que soit le type », colonne statut du bon.
- `src/Entity/Stock/SortieStock.php`, `src/Repository/Stock/SortieStockRepository.php` : documentation et méthodes renommées (`findBonsEnAttente`, `findBonsSignes`).

Aucune page, aucun menu, aucune route existante n'a été supprimée : uniquement des ajouts et des renommages internes cohérents.

## 3. Routes

| URL | Nom | Accès |
|---|---|---|
| `/bons-sortie-stock/a-signer` | `app_bons_stock_a_signer` | Direction Générale |
| `/bons-sortie-stock/signes` | `app_bons_stock_signes` | DG + profil lecture |
| `/bons-sortie-stock/a-signer/{token}/signer` | `app_bon_stock_signer` | DG (POST) |
| `/bons-sortie-stock/a-signer/{token}/annuler` | `app_bon_stock_annuler` | DG (POST) |
| `/bons-sortie-stock/{reference}/pdf` | `app_bon_stock_pdf` | utilisateur connecté |

> Les noms de routes ont été choisis en `app_bon_stock_*` / `app_bons_stock_*` pour ne pas entrer en conflit avec la route `app_bon_sortie_pdf` déjà utilisée par le module API des mouvements de stock.

## 4. Hypothèse sur le rôle du signataire

Le bon est signé par le profil **Direction Générale**. Le projet ne contenant pas de rôle `ROLE_DR`, c'est `ROLE_DG` qui est utilisé. Si votre rôle s'appelle autrement, remplacez simplement `ROLE_DG` par le bon nom dans `BonsSortieStockController` et `base.html.twig` (menu).

## 5. Mise à jour de la base de données

Les colonnes du bon sont portées par la table `sortie_stock`. Si le schéma n'est pas encore à jour, exécutez (à adapter selon votre pratique) :

```sql
ALTER TABLE sortie_stock
  ADD COLUMN bon_reference VARCHAR(40) NULL,
  ADD COLUMN bon_token VARCHAR(64) NULL,
  ADD COLUMN bon_statut VARCHAR(20) NULL,
  ADD COLUMN signe_at DATETIME NULL,
  ADD COLUMN signe_par VARCHAR(120) NULL,
  ADD COLUMN observation_signature VARCHAR(255) NULL,
  ADD COLUMN client_uuid VARCHAR(64) NULL;

CREATE UNIQUE INDEX uni_sortie_bon_reference ON sortie_stock (bon_reference);
CREATE UNIQUE INDEX uni_sortie_bon_token ON sortie_stock (bon_token);
CREATE UNIQUE INDEX uni_sortie_client_uuid ON sortie_stock (client_uuid);
```

Pour le module Présences (évolution précédente) :

```sql
ALTER TABLE participant_reunion ADD COLUMN arrivee_at DATETIME NULL;
```

Sinon, en ligne de commande :

```bash
php bin/console doctrine:schema:update --force
# ou, si vous gérez les migrations :
php bin/console make:migration && php bin/console doctrine:migrations:migrate
```

Les sorties déjà enregistrées restent valides : `bon_statut` à `NULL` signifie simplement « pas de bon » (affiché « - »).

## 6. Installation et vérification locale

```bash
composer install
php bin/console cache:clear
symfony server:start        # ou votre serveur habituel
```

Puis dans le navigateur (**Ctrl+F5** pour vider le cache du navigateur) :

1. `Stock > Sorties de stock` : encart « Bon de sortie : toute sortie de stock, quel que soit le type ».
2. Enregistrez une sortie sur une rubrique **autre que le cacao** : elle doit recevoir une référence `BS-...` et le statut « En cours de validation ».
3. Connecté en Direction Générale, ouvrez **Bons à signer** : la sortie y figure, signez-la.
4. « Imprimer le bon » : le PDF s'ouvre avec le QR code et la signature.
5. **Bons signés** : le bon apparaît avec le nom du signataire et la date.

## 7. Important

Le code livré ici **n'a pas pu être compilé ni testé** dans mon environnement : aucun serveur PHP, aucune base de données et aucun navigateur n'y sont disponibles. Les vérifications ci-dessus sont donc à faire de votre côté ; en cas de message d'erreur, transmettez-le moi (ou le contenu de `var/log/dev.log`) et je corrige.

Sauvegardez votre base avant d'appliquer la mise à jour du schéma.
