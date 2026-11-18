# CITRAC GMAO — Dossiers d'embarquement, messagerie interne et rapports PDF

Ce lot couvre trois demandes. Tous les fichiers sont **vos fichiers d'origine modifiés ou complétés**,
sans renommage ni restructuration du projet. Le thème cacao, la gestion du courrier, les bons de
sortie, le stock, les réunions et l'hébergement restent inchangés.

---

## 1. Page « Dossiers d'embarquement » (liste)

Avant, le menu ne proposait que le **formulaire** de création. La liste des dossiers existe maintenant.

- **Adresse** : `/logistique/embarquements` (route `embarquements_index`)
- **Contenu** : bandeau de compteurs (dossiers, en cours, clôturés, à planifier, ce mois),
  recherche (n° OT, client, navire, destination, propriétaire), filtres statut / navire /
  période de création, tableau des dossiers (client, produit, propriétaire, navire, destination,
  date d'embarquement, statut), pagination 25 par page.
- **Actions** : bouton « Clôturer » / « Rouvrir » protégé par jeton de sécurité
  (`/logistique/embarquements/{id}/statut`).
- **Menu latéral** : « Dossiers d'embarquement » pointe désormais sur la liste, et
  « Nouvel embarquement » reste sur le formulaire.

Fichiers : `src/Controller/Logistique/DossierEmbarquement.php`,
`templates/logistique/embarquement/liste.html.twig`, `templates/logistique/embarquement/index.html.twig`,
`templates/base.html.twig`.

> Rappel : les champs `tonnage` et `nb_tc` sont des identifiants techniques, ils ne sont pas
> affichés dans le tableau.

---

## 2. Rapports PDF — lignes de données plus petites que les en-têtes

Les tailles de police du tableau étaient identiques entre l'en-tête et les lignes. Elles sont
maintenant distinctes et centralisées en haut des deux générateurs PDF :

| Tableau | En-tête | Lignes |
|---|---|---|
| Tableau simple | 9,5 | 8,2 |
| Rapports multi-tableaux | 8,6 | 7,6 (total : 7,8 gras) |
| Bon de sortie | 9,0 | 7,9 |
| Fiche d'intervention | 7,8 gras | 7,0 |

Les hauteurs de ligne ont été ajustées en conséquence (4,8 / 4,4 mm ; 5,7 mm pour la fiche
d'intervention) pour que le texte respire sans chevaucher les filets.

Fichiers : `src/Services/pdfService.php`, `src/Services/FicheInterventionPdf.php`.

---

## 3. Messagerie interne (chat)

Un chat entre utilisateurs, accessible depuis le menu latéral (« Messagerie », avec pastille
du nombre de messages non lus).

- **Canal général** : visible par tout le monde (`/messagerie`).
- **Conversations privées** : une conversation par collègue (`/messagerie?canal=<id utilisateur>`).
- **Fonctionnement** : rafraîchissement automatique toutes les 4 secondes (sans rechargement),
  envoi instantané, Entrée = envoyer / Maj + Entrée = retour à la ligne, compteur 2 000 caractères,
  recherche dans la liste des conversations, affichage adapté au téléphone (liste puis discussion).
- **Sécurité** : jeton de sécurité sur l'envoi, accès réservé aux utilisateurs connectés
  (règle déjà en place : tout accès est refusé par défaut).

Fichiers : `src/Entity/Chat/MessageChat.php`, `src/Entity/Chat/LectureChat.php`,
`src/Repository/Chat/MessageChatRepository.php`, `src/Repository/Chat/LectureChatRepository.php`,
`src/Controller/Chat/ChatController.php`, `templates/chat/index.html.twig`, `public/css/chat.css`,
`public/css/sidebar-cacao.css` (pastille du menu), `templates/base.html.twig` (entrée de menu
+ rafraîchissement de la pastille toutes les 30 s).

---

## ⚠️ IMPORTANT — une seule commande à ne pas oublier

Les deux tables du chat doivent être créées :

```bash
php bin/console doctrine:migrations:migrate
```

La migration `Version20261119120000` est **rejouable sans risque** : elle utilise
`CREATE TABLE IF NOT EXISTS` et vérifie les index avant de les créer.

---

## Mise en route

```bash
composer install
php bin/console cache:clear
php bin/console doctrine:migrations:migrate
symfony server:start        # ou votre serveur web habituel
```

Puis **Ctrl + F5** dans le navigateur (les fichiers CSS/JS ont changé).

En cas de souci, les traces sont dans `var/log/` (regarder d'abord `dev.log` sur la page concernée).

---

## Ce qui n'a pas pu être vérifié ici

Le code a été écrit et relu, mais **il n'a pas été compilé ni exécuté** : l'environnement de
préparation ne dispose ni de PHP/Composer, ni de base de données, ni de navigateur. Merci de
lancer les commandes ci-dessus sur votre machine ; en cas d'erreur, envoyez-moi le message exact,
je corrige tout de suite.
