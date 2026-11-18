# Habillage unifié — charte de la page de déchargement cacao

Bonjour,

Voici votre projet avec **un seul ajout** : une feuille de style commune qui applique
partout la même charte que la page de déchargement cacao (bandeau d'en-tête, cartes,
barres de section, tableaux, champs, boutons, badges, alertes, onglets, pagination,
filtres). Aucune page, aucun contrôleur, aucune route n'a été renommée ou déplacée.

---

## 1. Ce qui a été ajouté

| Fichier | Rôle |
|---|---|
| `public/css/cacao-ui.css` | **Nouveau.** La charte complète, en un seul fichier. |
| `templates/base.html.twig` | **3 lignes ajoutées** : un commentaire + le lien vers la feuille, placé tout à la fin du `<head>` (après le bloc `stylesheets` de chaque page, pour passer devant ses styles). |
| `public/cacao-ui-apercu.html` | **Nouveau, facultatif.** Une page d'aperçu statique pour voir le rendu de tous les composants sans lancer le site. Elle n'est liée à aucune route : vous pouvez la supprimer. |

Rien d'autre n'a été touché.

---

## 2. Le rendu, page par page

Comme le lien est dans le gabarit de base, **toutes les pages** qui en héritent prennent
la charte automatiquement : tableau de bord, déchargement cacao, embarquement,
production / lots, stocks cacao, mouvements de stock, bons de sortie, sorties,
tracabilité, logistique, demandes d'achat, courrier, réunions, utilisateurs,
paramètres, rapports, etc.

Ce qui change concrètement :

- **Bandeau d'en-tête** identique partout : même fond gris cacao translucide, même
  rayon, même cercle décoratif, même taille de titre. Les en-têtes qui avaient leur
  propre style (déchargement, saisie de stock, logistique, demandes d'achat) sont
  alignés sur le même modèle.
- **Cartes** : même fond crème, même bordure, même ombre douce, même rayon.
- **Titres de section** : la barre pleine bleu pétrole avec texte blanc, comme dans
  « Mouvements de stock ».
- **Tableaux** : en-têtes crème en majuscules, lignes qui s'éclairent au survol,
  en-têtes qui restent visibles quand le tableau défile (uniquement dans les
  conteneurs prévus pour défiler, jamais sous le bandeau).
- **Champs, sélecteurs, zones de texte** : même arrondi, même bordure, même halo ocre
  au focus, libellés en majuscules.
- **Boutons** : mêmes dégradés cacao (principal), mêmes variantes (valider, annuler,
  supprimer, neutre). Les boutons des pages qui avaient leur propre jeu de classes
  sont repris par des alias, sans modifier leur code.
- **Badges de statut, alertes, indicateurs chiffrés, pastilles de filtre, onglets,
  pagination, fenêtres modales, menus déroulants** : harmonisés dans la même palette.
- **Largeur de page** : le contenu est centré et limité en largeur, comme la page de
  déchargement cacao, au lieu de s'étirer sur toute la largeur d'un grand écran.
- **Mobile et tablette** : bandeau plus compact, tableaux qui défilent, boutons plus
  faciles à toucher, colonnes qui s'empilent.
- **Impression** : fonds et ombres retirés, en-têtes de tableau répétés, pour des
  sorties propres.
- **Accessibilité** : si le système demande « moins d'animations », les transitions
  sont coupées.

Le thème cacao, le choix FR / EN, le courrier, les bons, le stock, les réunions et
l'hébergement restent exactement comme avant.

---

## 3. Revenir en arrière (30 secondes)

Ouvrez `templates/base.html.twig` et supprimez la ligne :

```twig
<link rel="stylesheet" href="{{ asset('css/cacao-ui.css') }}">
```

Videz le cache, rechargez avec Ctrl + F5 : le site retrouve son apparence d'origine.
La feuille peut rester dans le projet, elle ne sera simplement plus chargée.
Vous pouvez aussi la vider de son contenu : le site restera debout, avec son ancien look.

---

## 4. Régler la charte sans toucher au code des pages

Tout est piloté par des variables, regroupées **en haut** du fichier
`public/css/cacao-ui.css`. Changez une valeur, videz le cache, rechargez :

| Variable | Ce qu'elle règle | Valeur actuelle |
|---|---|---|
| `--dc-cacao` | Le cacao principal (boutons, accents) | `#5a371e` |
| `--dc-cacao-fonce` | Le cacao des titres | `#2b1b10` |
| `--dc-creme` | Le fond des cartes | `#fdf9f4` |
| `--dc-bordure` | La couleur des bordures | `#e8dccd` |
| `--dc-head` | **La barre bleu pétrole des titres de section** | `#034c71` |
| `--dc-ocre` | Le halo au focus et les accents secondaires | `#b07b3d` |
| `--dc-rayon` | L'arrondi général des cartes et champs | `12px` |
| `--dc-largeur-page` | La largeur maximale du contenu | `1480px` |
| `--dc-degrade-primaire` | Le dégradé des boutons principaux | cacao foncé → cacao |

Exemples utiles :

- Barres de section plus foncées : `--dc-head: #023a57;`
- Contenu plus large (écrans très larges) : `--dc-largeur-page: 1700px;`
- Arrondis plus doux : `--dc-rayon: 8px;`
- Bandeau d'en-tête plus clair ou plus marqué : la règle du bandeau se trouve en
  section 1 du fichier, la transparence est réglée par `rgba(211, 211, 211, .71)`.

---

## 5. Deux limites à connaître

1. **Les styles écrits directement dans les balises** (`style="..."` au milieu d'un
   gabarit) restent prioritaires. C'est voulu : je n'ai pas modifié vos pages. Si une
   page garde un détail visuel isolé (un arrondi, une couleur), c'est qu'il est écrit
   en dur dans cette page ; dites-moi laquelle et je l'aligne.
2. **Les pages en PDF** (bons de sortie, rapports) ne sont pas concernées : elles ont
   leur propre mise en page d'impression, qui doit rester sobre.

---

## 6. Vérifier chez vous

Je n'ai pas pu exécuter le projet ici (pas de PHP, pas de base de données sur mon
environnement). Le fichier a été relu et testé sur une maquette statique des
composants, mais la validation finale se fait chez vous :

```bash
composer install          # si besoin
php bin/console cache:clear
symfony server:start      # ou votre serveur habituel
```

Puis dans le navigateur : **Ctrl + F5** (le navigateur garde l'ancienne feuille en
cache ; c'est la cause n°1 d'un « ça n'a pas changé »).

À regarder en priorité :

- Déchargement cacao → l'écran doit être **identique** à avant (c'est la référence).
- Un tableau de bord, une liste avec tableau, un formulaire de création, une page de
  détail, une fenêtre modale, une page en mobile.
- Le choix FR / EN dans l'en-tête.
- Une impression de bon ou de rapport.

Si quelque chose vous paraît trop clair, trop sombre ou mal aligné, envoyez-moi une
capture : je corrige la variable concernée, sans toucher à vos pages.

---

## 7. Contenu de l'archive

Le projet complet, **sans** `var/`, `vendor/`, `node_modules/`, `.git/`, ni les
fichiers déposés par les utilisateurs (`public/uploads`, `public/data`), pour garder
une archive légère. À l'extraction, relancez `composer install` si `vendor/` manque.

Bon test,
