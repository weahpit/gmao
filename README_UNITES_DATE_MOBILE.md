# Unités de mesure, dates opération grisées et bon de sortie sur mobile

## 1. Dates opération grisées (entrées et sorties)

Sur les deux formulaires du module Stock — **Nouvelle saisie (entrée)** et **Sortie de stock** — le champ « Date et heure » est désormais **grisé et non modifiable**. Un petit texte sous le champ précise : « Date fixée automatiquement par le serveur ».

La valeur enregistrée n'est plus celle du navigateur : c'est **l'heure du serveur** qui fait foi. Une date envoyée malgré tout par le navigateur ou par l'application mobile n'est retenue que si elle est à moins de 3 minutes de l'heure serveur (simple décalage d'horloge) ; sinon elle est ignorée. Il n'est donc plus possible d'antidater ou de post-dater une entrée ou une sortie.

Fichiers touchés :
- `templates/saisie_stock/saisie.html.twig` et `templates/saisie_stock/sortie.html.twig` (champ `readonly` + libellé d'aide) ;
- `templates/saisie_stock/_styles.html.twig` (classe `.ss-date-figee` et `.ss-hint`, dans le style cacao existant) ;
- `SaisieStockFormController` et `SortieStockController` (méthode `dateOperationServeur()`).

## 2. Nouvelle table des unités (`stock_unite`)

Les unités ne sont plus codées en dur : elles viennent de la table **`stock_unite`**, partagée par le web et le mobile.

- Entité `App\Entity\Stock\Unite` : `libelle` (Kg, Litres…), `symbole`, `parDefaut`, `active`, `position`.
- Dépôt `UniteRepository` : `libelles()` (unités actives triées) et `libelleParDefaut()`.
- `ReferentielStock::unites()`, `uniteValide()` et `uniteDefaut()` : source unique pour les formulaires web et les API mobiles.
- Fixtures : Kg (par défaut), Litres, Sacs, Tonnes, Cartons, Pièces.
- Si la table est vide, on retombe automatiquement sur les anciennes valeurs (`Kg`, `Litres`) : aucune liste vide, rien ne casse.

Ajouter une unité = ajouter une ligne dans `stock_unite` (ou dans les fixtures). Elle apparaît immédiatement dans le web et sur mobile, sans redéploiement.

La règle « liste fermée » de l'entité `SortieStock` a été remplacée par une validation contre le référentiel, pour que les nouvelles unités soient acceptées.

## 3. Application mobile

Les API mobiles ont été alignées sur le web :

| Ce qui change | Endpoint |
|---|---|
| Unités lues dans `stock_unite` + `uniteDefaut` réel | `GET api/saisie-stock/referentiel`, `GET api/sortie-stock/referentiel` |
| Bloc `dateOperation` (champ à griser, origine « serveur ») | mêmes endpoints |
| Date opération enregistrée = heure serveur (± 3 min de tolérance) | `POST api/saisie-stock`, `POST api/sortie-stock` |
| Validation d'unité contre la table | mêmes POST |
| Bon de sortie pour **toute** rubrique (infos dans le référentiel) | `GET api/sortie-stock/referentiel` → `bonSortie` |
| Détail d'un bon (fiche + statut + signataire) | `GET api/sortie-stock/bon/{token}` *(nouveau)* |
| Liste des bons à signer (Directeur Général) | `GET api/sortie-stock/bons-a-signer` |
| Signature du bon | `POST api/sortie-stock/bon/{token}/signer` |
| Annulation / refus du bon | `POST api/sortie-stock/bon/{token}/annuler` *(nouveau)* |

Côté application, il suffit de :
1. rendre le champ date **désactivé** (lecture seule) en affichant `data.date` du référentiel ;
2. remplir la liste des unités avec `data.unites` et présélectionner `data.uniteDefaut` ;
3. pour un profil Direction Générale, appeler `bons-a-signer` puis `.../signer` ou `.../annuler` ;
4. après la création d'une sortie, `data.bon_reference` et `data.bon_statut` renvoient le bon émis (toute rubrique).

Les réponses restent au format maison `{code, msg, data}` (`success` | `warning` | `error`).

## 4. Mise à jour de la base

```sql
CREATE TABLE stock_unite (
  id BINARY(16) NOT NULL,
  libelle VARCHAR(30) NOT NULL,
  symbole VARCHAR(20) DEFAULT NULL,
  par_defaut TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  position INT NOT NULL DEFAULT 0,
  UNIQUE INDEX uni_stock_unite_libelle (libelle),
  PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

INSERT INTO stock_unite (id, libelle, symbole, par_defaut, active, position) VALUES
 (UNHEX(REPLACE(UUID(),'-','')), 'Kg', 'kg', 1, 1, 0),
 (UNHEX(REPLACE(UUID(),'-','')), 'Litres', 'L', 0, 1, 1),
 (UNHEX(REPLACE(UUID(),'-','')), 'Sacs', 'sac', 0, 1, 2),
 (UNHEX(REPLACE(UUID(),'-','')), 'Tonnes', 't', 0, 1, 3),
 (UNHEX(REPLACE(UUID(),'-','')), 'Cartons', 'crt', 0, 1, 4),
 (UNHEX(REPLACE(UUID(),'-','')), 'Pieces', 'pce', 0, 1, 5);
```

Plus simple avec Doctrine :

```bash
php bin/console doctrine:schema:update --force
# puis, pour peupler les unités sans rejouer toutes les fixtures :
php bin/console doctrine:fixtures:load --append
```

La colonne `unite` de `saisie_stock` et `sortie_stock` reste un texte (`VARCHAR(20)`) : aucune migration de données n'est nécessaire, les anciennes valeurs « Kg » / « Litres » restent valides.

## 5. Vérification locale

```bash
composer install
php bin/console cache:clear
symfony server:start
```

Puis, avec **Ctrl+F5** :
1. `Stock > Nouvelle saisie` : la date est grisée, la liste des unités contient les nouvelles valeurs.
2. `Stock > Sorties de stock` : idem, et l'encart « Bon de sortie : toute sortie de stock » est visible.
3. Enregistrez une sortie sur une rubrique non cacao → bon `BS-…` émis, visible dans « Bons à signer ».
4. Testez les endpoints mobiles (`api/sortie-stock/referentiel`, création, `bons-a-signer`, `signer`, `annuler`).

## 6. Important

Ce code **n'a pas été compilé ni exécuté** ici : aucun serveur PHP, aucune base et aucun navigateur ne sont disponibles dans mon environnement. Les points ci-dessus sont à valider de votre côté ; en cas d'erreur, transmettez le message (ou `var/log/dev.log`) et je corrige.

Sauvegardez votre base avant la mise à jour du schéma.
