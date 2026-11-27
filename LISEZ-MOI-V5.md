# CITRAC GMAO — Évolutions V5 (embarquement, supervision, présence, RH)

Cette archive contient **votre projet réel modifié** (dossiers `config/`, `src/`, `templates/`, `migrations/`, plus les fichiers ajoutés dans `public/` et le connecteur `connecteur-zkteco/`). Aucun fichier existant n'a été supprimé et aucune donnée n'est effacée par les scripts fournis.

> **Important — ce qui n'a pas été fait ici :** l'environnement de préparation ne dispose ni de PHP, ni de Composer, ni de base PostgreSQL, ni de navigateur, ni de Flutter. Les fichiers ont donc été écrits et contrôlés (structure, syntaxe, cohérence des appels), mais **rien n'a été exécuté, compilé ni testé à l'écran**. La validation locale reste à faire chez vous, avec les commandes indiquées plus bas. Aucun APK n'a été produit.

---

## 1. Mise en route (à faire une fois)

```bash
# 1. Sauvegarder la base ET le dossier du projet avant tout
pg_dump -Fc votre_base > sauvegarde_avant_v5.dump

# 2. Déposer l'archive sur le serveur, conserver votre .env.local existant
composer install --no-dev --optimize-autoloader

# 3. Préparer d'abord le socle export (déjà présent chez vous), puis V5
php bin/console app:export:installer              # diagnostic, n'écrit rien
php bin/console app:export:installer --appliquer  # si le diagnostic le demande
php bin/console app:embarquement:installer        # diagnostic : liste les requêtes V5
php bin/console app:embarquement:installer --appliquer

# 4. Alternative : migration Doctrine (mêmes requêtes, idempotentes)
php bin/console doctrine:migrations:migrate --dry-run
php bin/console doctrine:migrations:migrate

# 5. Vider le cache et vérifier
php bin/console cache:clear
php bin/console lint:container
php bin/console debug:router | grep logistique
```

Le script SQL brut `migrations/2026_11_27_evolutions.sql` est fourni pour un administrateur base qui préfère psql. Il est enveloppé dans `BEGIN … COMMIT` : si un solde de sacs devient négatif, **toute la transaction est annulée** avec un message explicite, rien n'est supprimé.

### Export PDF / Excel de la fiche dossier

L'export utilise deux librairies facultatives, nécessaires **uniquement** pour ces deux boutons :

```bash
composer require dompdf/dompdf phpoffice/phpspreadsheet
```

Sans elles, les boutons répondent par un message clair (service indisponible, code 503) au lieu d'une erreur opaque ; le reste de l'application fonctionne normalement. Les photos d'empotage sont lues **sur le disque** (aucun accès réseau) et le dossier `var/export/photos` doit être inscriptible par le serveur web.

---

## 2. Module Embarquements — point par point

### A1 — Saisie sans rechargement de page
Le formulaire de dossier (`/logistique/embarquement…`) enregistre désormais en arrière-plan. En cas de refus, le message du serveur s'affiche dans la page, la saisie est conservée ; en cas de succès, vous êtes conduit au suivi documentaire du dossier. Le stockage classique reste actif si JavaScript est désactivé.

### A2 — Statut « APPROUVÉ »
Les libellés affichés sont maintenant **APPROUVÉ**, **NON APPROUVÉ**, **POUR APPROBATION**. Les valeurs enregistrées en base restent `APPROUVE` / `NON_APPROUVE` / `POUR_APPROBATION` : les anciens enregistrements `APPROVE` / `NON_APPROVE` sont convertis par le script d'installation, sans perte. La conversion couvre aussi les dix états de certificats du dossier et le tableau des certificats du suivi.

### A3 — Photos d'empotage (25 maximum, moins de 200 Ko)
- 25 photos **au total** par dossier, photos déjà enregistrées comprises.
- Formats acceptés : JPEG, PNG, WEBP (12 Mo et 24 mégapixels maximum à la source).
- Conversion serveur : redimensionnement (1600 px max), correction d'orientation, qualité ajustée jusqu'à obtenir **moins de 200 000 octets** ; au-delà de 12 tentatives, le refus est explicite et la photo n'est pas enregistrée.
- Stockage **hors dossier public** (`var/export/photos`), nom aléatoire, restitution uniquement par une route protégée qui vérifie que la photo appartient bien au dossier.
- Une case à cocher permet de retirer une photo lors de l'enregistrement ; le fichier est alors supprimé du disque.
- L'extension PHP **GD** doit être active (message clair si elle ne l'est pas).

### A4 — Contrôles compacts et lisibles
Feuille de style additive `public/css/citrac/evolutions-v5.css` : champs, listes et boutons plus compacts (taille de police ~0,875 rem, hauteur 31 px), tableaux défilables, messages d'erreur en rouge, barres de progression vert cacao. Aucune règle existante n'est supprimée : la couche se charge après les styles du projet.

### A5 — Création rapide (CV, exécutions, formules, références)
Des fenêtres de création rapide évitent de quitter la saisie :
- **CV de déblocage**, **CV d'exécution** (avec période) et **formule** (fichier facultatif) depuis le formulaire de dossier ;
- **Propriétaire, produit, client, navire, pays, banque, transitaire, compagnie de navigation** via le bouton « + Ajouter » : la référence est créée si elle n'existe pas (comparaison insensible à la casse et aux espaces) et sélectionnée immédiatement.
Toutes ces opérations passent par un jeton CSRF et renvoient un message d'erreur précis en cas de refus.

### A6 — Fiche en lecture seule + export PDF / Excel
`/logistique/embarquements/{id}/fiche` affiche une fiche complète, non modifiable : données du dossier, planning, conteneurs/lots/sacs/poids, formules et CV avec périodes, origine des lots, suivi documentaire, photos. Chaque ligne de la liste des dossiers propose le lien **Fiche**. Export **PDF** (en-tête CITRAC, A4, photos intégrées) et **Excel** (une feuille « Dossier », en-tête CITRAC, photos insérées, colonnes ajustées).

### A7 — Barre de progression et délai documentaire de 5 jours
Chaque dossier calcule un pourcentage d'avancement : jalons opérationnels (OT, date OT, navire, booking, tonnage, formule, empotage, relevage, embarquement, N° BL, date BL, remise) **plus** les documents attendus transmis. Le pourcentage, le nombre d'étapes et la barre apparaissent dans la liste, la fiche et l'export.
Le délai court **en jours calendaires depuis la date d'embarquement** jusqu'à la remise des documents, ou jusqu'à aujourd'hui si la remise n'est pas faite (mention « Remise en attente »). Dès que le délai **dépasse 5 jours**, la cellule passe en rouge avec « Délai > 5 jours ». Des dates incohérentes (remise antérieure à l'embarquement) sont signalées au lieu d'afficher un faux délai.

### A8 — Huit nouveaux champs de dossier
`Date OT`, `Banque de nantissement`, `Transitaire`, `Compagnie de navigation`, `Booking`, `Port d'embarquement`, `N° BL`, `Date BL`. Deux référentiels sont créés (`transitaire`, `compagnie de navigation`) et éditables dans l'administration des références. La banque de nantissement réutilise le référentiel banque existant, avec une contrainte de clé étrangère `ON DELETE RESTRICT` (aucune référence utilisée ne peut être supprimée).

### A9 — Période sur les CV
Champ `periode` sur les CV de déblocage et d'exécution, libre (100 caractères), affiché dans les listes, la fiche, l'export et les fenêtres de création rapide. Facultatif : un CV sans période reste valide et s'affiche « Période non renseignée ».

### A10 — Soldes de sacs et de poids par lot
Le nombre de sacs restants est suivi par lot (`sacs_restants`), comme le poids net. Le formulaire propose par défaut le stock restant, refuse un dépassement (message indiquant le disponible réel), et restitue les sacs lors d'une modification de dossier. À l'installation, les soldes sont calculés à partir des sacs déjà chargés dans les dossiers existants ; **si un solde devient négatif, l'installation s'arrête** en demandant un rapprochement manuel plutôt que de corriger en silence. Le poids est toujours exprimé en kg avec 3 décimales ; les sacs sont des entiers.

### A11 — Origine des lots : N° lot ⇒ N° camion
La fiche et l'export reconstituent, pour chaque lot embarqué, les camions d'origine en remontant `Lot → LotDechargement → Déchargement cacao (code, immatriculation)` puis, si besoin, les lots féve des ordres de production, avec protection contre les boucles. Quand l'origine n'est pas traçable, la mention « Origine non renseignée » et l'origine libre du lot sont affichées — aucune information n'est inventée.

### A12 — Graphiques des tableaux de bord DL / SHIPPING / DG / AGENT_SHIPPING
Trois indicateurs visuels sont ajoutés aux profils concernés :
- **Avancement des dossiers** (répartition 0–24 %, 25–49 %, 50–99 %, 100 %) ;
- **Consommation des CV** (accordé / octroyé / consommé, kg) ;
- des **compteurs d'embarquement** (dossiers en cours, dépassements de délai).
Le rôle `ROLE_AGENT_SHIPPING` (et l'alias `AGENT_SHIPPING`) est désormais déclaré dans la hiérarchie des rôles : sans cela, un agent shipping ne voyait pas ses gadgets.

### A13 — Administration de toutes les références
`/references/edition` liste l'ensemble des référentiels, avec recherche, pagination et formulaire d'édition générique (types simples, dates, booléens, JSON, relations). Les nouvelles références **transitaire** et **compagnie de navigation** y figurent, ainsi que les références logistique, maintenance, RH et stock déjà existantes. Les noms d'utilisateur, rôles et permissions ne sont pas modifiables par ce formulaire (réservé à l'administration des comptes).

---

## 3. Supervision (page mur)

- **Point du stock cacao par jour** : le calcul est fait en SQL portable (`CAST(colonne AS DATE)`), car le DQL du projet n'accepte pas `DATE()`. Une erreur de calcul est affichée à l'écran avec son message, elle n'est plus masquée par un panneau vide.
- **Filtres de période** (7 / 14 / 30 / 60 jours) et **mode** (mouvements, cacao, toutes les matières) appliqués de bout en bout, y compris à l'actualisation automatique toutes les 60 s.
- **Gadgets agrandis** : les blocs utiles passent en demi-largeur (6/12) pour être lisibles sur un mur d'écrans.
- **Nouveau graphique « Visites et lots produits par jour »** : deux courbes (visites, lots produits) sur la période choisie, avec message explicite si la librairie de graphiques n'est pas chargée.

---

## 4. Pointage ZKTeco — synchronisation locale vers l'API HTTPS

Trois pièces, dans `connecteur-zkteco/` :

1. `zkteco_sync.py` — lit le fichier Access **en lecture seule** (`READONLY=1`), envoie les passages par lots (500 par défaut, 1 000 maximum), avec reprise glissante de 3 jours pour rattraper les passages tardifs, verrou de fichier contre les exécutions simultanées, et refus des redirections HTTP pour ne jamais transmettre le jeton à un autre domaine. Le marqueur d'avancement n'est écrit **qu'après** l'accusé de réception de tous les lots.
2. `config.exemple.json` — à copier en `config.json` et à adapter (chemin du `.mdb/.accdb`, URL HTTPS, nom de source).
3. `lancer-sync.exemple.ps1` — tâche planifiée Windows (toutes les 10 minutes, par exemple).

Côté application, la route `POST /api/presences/zkteco` :
- exige **HTTPS** et un jeton `Bearer` d'au moins 32 caractères (`ZKTECO_API_TOKEN` dans `.env.local`), comparé en temps constant ;
- limite la charge à 1 Mo et 1 000 passages, valide badge et horodatage (fuseau `Africa/Abidjan`, pas de date future) ;
- ignore les doublons grâce à une empreinte SHA-256 unique (table `rh_zkteco_passage`) ;
- rapproche le badge du **code de pointage** ou du **matricule** de l'employé — jamais du nom — et signale les badges inconnus ou ambigus dans la réponse (`badgesNonRapproches`) ;
- calcule l'heure d'arrivée (la plus tôt), l'heure de départ (la plus tardive, seulement si elle est postérieure), le retard et les heures supplémentaires en **minutes** (unité déjà utilisée par les écrans RH) ; une présence isolée ne crée pas de fausse heure de départ.

Le pare-feu `zkteco_api` de `security.yaml` laisse cette URL hors session : l'authentification est faite par le jeton, pas par un compte utilisateur. Aucun port entrant n'est ouvert sur le poste local : c'est le poste qui appelle le serveur.

---

## 5. Ressources humaines

- **Lignes rouges pour les employés sortis** dans la liste des employés (fond rouge clair, texte rouge, liens compris) et **étiquette « Actif » en vert** — les couleurs sont appliquées par une classe, sans modifier les gabarits métier existants.
- **Dossiers de candidats spontanés** : nouvel écran `/rh/vivier` (recherche par nom, prénoms, poste recherché, téléphone, courriel ; pagination 30 par page). Un dossier d'accueil contient nom, prénoms, contacts, adresse, **poste recherché**, diplômes, expériences, **observations d'accueil** et jusqu'à 10 pièces (10 Mo par pièce, formats bureautiques et images).
  Depuis la fiche, « Rattacher au recrutement » crée la candidature sur une **offre publiée** au moyen du service de références RH existant, puis ouvre directement la candidature : le dossier spontané entre ainsi dans le circuit normal de recrutement. Les pièces déposées restent visibles dans la candidature.

---

## 6. Points d'attention et limites connues

1. **Non exécuté ici** (voir l'avertissement en tête). À valider chez vous : `cache:clear`, `lint:container`, `debug:router`, parcours d'un dossier complet, export PDF et Excel, tâche planifiée ZKTeco en `--simulation` d'abord.
2. **Photos** : nécessite l'extension GD ; dossier `var/export/photos` inscriptible ; les photos historiques stockées dans `public/data/...` restent consultables comme avant, elles ne sont pas migrées automatiquement.
3. **Export** : dépend de dompdf et phpspreadsheet ; sans elles, message 503 explicite.
4. **Soldes de sacs** : les dossiers historiques dont la ventilation des lots est incomplète peuvent nécessiter un rapprochement manuel ; l'installation refuse de produire un solde négatif.
5. **Pointage** : le rapprochement se fait sur le code de pointage ou le matricule ; les badges non rapprochés sont listés pour correction, jamais affectés au hasard.
6. **Rôles** : `ROLE_AGENT_SHIPPING` est ajouté à la hiérarchie ; si vos comptes utilisent le rôle `AGENT_SHIPPING` sans préfixe, l'alias est également déclaré.
7. **Sauvegarde** : faites `pg_dump` et une copie du dossier avant l'installation ; les scripts sont idempotents et sans suppression, mais une sauvegarde reste la meilleure protection.

---

## 7. Fichiers principaux ajoutés ou modifiés

- Entités : `Transitaire`, `CompagnieNavigation`, `Dossier` (8 champs), `CvDeblocage`/`CvExecution` (période), `SuiviEmbarquement` (photos), `Lot` (sacs restants), `Candidat` (poste recherché, observations, dossier spontané).
- Services : `PhotosEmpotage`, `IndicateursDossier`, `FicheDossier`, `EnregistrerEmbarquement` (contrôles sacs/poids), `StockCacaoJourService`.
- Contrôleurs : `FicheDossierController` (fiche, photo, export PDF/Excel), `CvRapideController`, `AjoutRapideExportController`, `EditionReferencesController`, `VivierCandidatsController`, `ApiPresenceZktecoController`, `SupervisionController`.
- Modèles : fiche en lecture seule, export PDF, liste avec progression et délai, formulaires rapides, vivier RH, supervision.
- Styles et scripts : `public/css/citrac/evolutions-v5.css`, `public/css/citrac/supervision-v5.css`, `public/js/citrac/{ajax-v5,operations-logistiques,ajout-rapide-export,cv-rapide-v5,embarquement-ajax,supervision-v5}.js`.
- Installation : `src/Command/InstallerEmbarquementCommand.php`, `migrations/Version20261127120000.php`, `migrations/evolutions_v5_sql.php`, `migrations/2026_11_27_evolutions.sql`.

Bon test, et signalez-moi le message exact de toute erreur rencontrée : je la corrigerai sur vos fichiers.
