# CITRAC — Circuit de validation du bon de sortie (3 étapes)

Ce correctif met en place le circuit demandé :

| Étape | Acteur | Action | Statut résultant | Affichage |
|-------|--------|--------|------------------|-----------|
| 1 | **Magasinier** | Sort les articles → un bon est émis | `EN_ATTENTE` | « En cours de validation » (rouge) |
| 2 | **Directeur Général** | Signe le bon | `VALIDE` | « Validé » (vert) |
| 3 | **Vigil** (guérite) | Scanne le QR et clôture | `CLOTURE` | « Clôturé » (bleu-gris) |

Règle clé : **le vigil ne peut clôturer un bon que s'il a d'abord été signé
par le DG.** Un bon encore `EN_ATTENTE` est refusé à la guérite avec le
message « en attente de la signature du Directeur Général ».

> ⚠️ Code **non compilé/non testé** ici (ni PHP/Composer, ni base PostgreSQL,
> ni Flutter dans l'environnement de génération). À valider chez vous.

---

## 1. Fichiers modifiés (côté backend Symfony)

À recopier dans votre projet GMAO en respectant les chemins :

```
src/Entity/BonSortie.php
src/Controller/Api/stock/ApiMouvementsStockController.php
config/packages/security.yaml
```

### Ce qui change
- **BonSortie.php** : nouveau statut `CLOTURE`, libellés FR (`LIBELLES`),
  nouveaux champs `signe_at` / `signe_par` (DG) et `cloture_at` /
  `cloture_par` (vigil), et les méthodes `isEnAttente()`, `isValide()`,
  `isCloture()`, `getStatutLabel()`.
- **ApiMouvementsStockController.php** :
  - nouvel endpoint **signature DG** : `POST api/bonSortie/signer/{token}`
    (rôle `ROLE_DG` ou `ROLE_ADMIN`) — `EN_ATTENTE` → `VALIDE` ;
  - l'endpoint du vigil `POST api/bonSortie/valider/{token}` (+ alias
    `.../cloturer/{token}`) **clôture** désormais le bon : il refuse les bons
    non signés (`EN_ATTENTE`) et passe `VALIDE` → `CLOTURE` ;
  - `serialiserBon()` renvoie `statut_label`, `signe_*` et `cloture_*`.
- **security.yaml** : ajout du rôle **`ROLE_DG`** dans la hiérarchie
  (`ROLE_ADMIN` hérite de `ROLE_DG`).

## 2. Mise à jour de la base de données

Deux options.

**A. Via Doctrine (recommandé)**
```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

**B. SQL direct** (PostgreSQL) — fichier `migration_bon_sortie_workflow.sql` :
```bash
psql -U postgres -d gmaobd -f migration_bon_sortie_workflow.sql
```

## 3. Donner le rôle au Directeur Général

Attribuez `ROLE_DG` au compte du DG (via votre gestion des utilisateurs,
comme pour `ROLE_VIGIL`). Un `ROLE_ADMIN` peut aussi signer.

## 4. Brancher le bouton « Signer » dans l'interface web du DG

Le DG signe depuis l'interface web GMAO. Ajoutez un bouton sur la page du bon
qui appelle l'endpoint de signature, par exemple :

```javascript
// bouton « Signer le bon » visible uniquement si statut == EN_ATTENTE
fetch('/api/bonSortie/signer/' + token, { method: 'POST' })
  .then(r => r.json())
  .then(res => {
      // res.code === 'success' => rafraîchir l'affichage, statut => Validé
      alert(res.msg);
  });
```

(Le `token` est celui déjà présent sur le bon / dans le QR.)

## 5. Endpoints finaux

| Méthode & route | Rôle | Effet |
|-----------------|------|-------|
| `GET  api/bonSortie/scan/{token}` | VIGIL / ADMIN | Détails + statut |
| `POST api/bonSortie/signer/{token}` | **DG** / ADMIN | `EN_ATTENTE` → `VALIDE` |
| `POST api/bonSortie/valider/{token}` | VIGIL / ADMIN | `VALIDE` → `CLOTURE` |
| `POST api/bonSortie/cloturer/{token}` | VIGIL / ADMIN | alias de `valider` |

## 6. Côté application mobile

L'app mobile (paquet `citrac_stock_mobile`) a été mise à jour en conséquence :
- 3 statuts colorés (rouge / vert / bleu-gris) ;
- le bouton **« Clôturer le bon »** n'apparaît que si le bon est `VALIDE` ;
- si le bon est encore `EN_ATTENTE`, un bandeau rouge indique qu'il attend la
  signature du DG et la clôture est bloquée ;
- si le bon est déjà `CLOTURE`, l'app affiche « sortie autorisée ».
