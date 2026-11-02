# Bouton « Signer » du Directeur Général (interface web GMAO)

Ce correctif ajoute, dans l'interface web Symfony/Twig, le bouton qui permet au
**Directeur Général** de signer un bon de sortie (`EN_ATTENTE` → `VALIDE`).
Il s'appuie sur l'endpoint déjà en place : `POST api/bonSortie/signer/{token}`.

> ⚠️ Code **non compilé/non testé** ici (pas de PHP/Composer ni de navigateur
> dans l'environnement de génération). À valider chez vous.

## Fichiers

```
templates/mouvements_stock/_bouton_signer.html.twig   (nouveau partiel Twig)
public/js/api/stock/BonSortie.js                      (nouveau script jQuery)
```

Ils respectent les conventions du projet : jQuery `$.ajax`, `URL_APP`,
`showToast()`, Bootstrap 5, palette cacao `#5a371e`.

## 1. Charger le script

Dans `templates/base.html.twig`, après jQuery / `showToast.js` (vers la fin,
près des autres `js/api/stock/...`), ajoutez :

```twig
<script src="{{ asset('js/api/stock/BonSortie.js') }}"></script>
```

## 2. Afficher le bouton sur la page d'un bon

Là où vous affichez un bon de sortie (page de détail ou ligne de liste),
incluez le partiel en lui passant le token et le statut du bon :

```twig
{% include 'mouvements_stock/_bouton_signer.html.twig' with {
    token:  bon.token,
    statut: bon.statut
} %}
```

Comportement automatique :
- **EN_ATTENTE + utilisateur ROLE_DG** → bouton marron « Signer le bon ».
- **EN_ATTENTE + autre utilisateur** → badge rouge « en attente de signature DG ».
- **VALIDE** → badge vert « Validé (signé par le DG) ».
- **CLOTURE** → badge gris « Clôturé — sortie autorisée ».
- **ANNULE** → badge « Annulé ».

Au clic : une petite fenêtre Bootstrap demande confirmation, puis l'appel AJAX
est envoyé. En cas de succès, un toast vert s'affiche et le bouton est remplacé
à chaud par le badge « Validé » (sans recharger la page).

## 3. Variante : bouton dans une liste chargée en AJAX

Si vos bons sont affichés dans un tableau généré en JavaScript, vous pouvez
construire le bouton à la main — le gestionnaire de clic fonctionne par
délégation (classe `.btn-signer-bon`) :

```html
<button class="btn btn-signer-bon fw-bold text-white"
        data-token="LE_TOKEN"
        style="background:#5a371e;border:none;">Signer</button>
```

Pour que la mise à jour à chaud retrouve la zone, enveloppez l'état dans :

```html
<span class="bon-signature-zone" data-token="LE_TOKEN"> ... </span>
```

## 4. Prérequis côté rôles

Le compte du Directeur Général doit posséder le rôle **`ROLE_DG`**
(déjà ajouté dans la hiérarchie `security.yaml` du correctif précédent).
Un `ROLE_ADMIN` peut aussi signer (il hérite de `ROLE_DG`).
