# Messagerie : accusés de lecture, fiabilité d'envoi, notifications — et app renommée « CITRAC PROCESS »

> **Important** : je n'ai pu ni compiler Flutter ni lancer PHP dans mon environnement. Le code a été relu
> (équilibre des blocs, syntaxe du JavaScript du gabarit web vérifiée), mais **la validation se fait chez vous** :
> `flutter analyze` côté mobile, `php bin/console lint:twig templates/chat` et un essai réel côté serveur.

---

## 1. Ce que vous verrez

### Sous chacun de VOS messages (web et mobile)

| Symbole | Sens |
|---|---|
| Horloge | l'envoi est en cours : le message n'est **pas encore** sur le serveur |
| **Une coche** | **envoyé** : le serveur l'a enregistré (c'est votre assurance qu'il est parti) |
| Deux coches grises | **remis** : arrivé sur l'appareil / dans l'application du destinataire |
| Deux coches **bleues** | **lu** : le destinataire a ouvert la conversation |
| Point d'exclamation rouge | **non envoyé** (voir ci-dessous) |

- En **liste de diffusion** ou sur le **canal Général**, les coches deviennent bleues dès qu'**au moins une**
  personne a lu, avec le détail « 3/12 » (lu par 3 personnes sur 12).
- Les coches évoluent toutes seules (rafraîchissement toutes les 4 s sur le web, 6 s sur mobile).

### Quand le message n'est pas parti
Le message s'affiche immédiatement avec l'horloge. S'il ne part pas (coupure réseau, refus du serveur) il passe en
rouge avec **« Réessayer »** et **« Supprimer »**. Sur mobile, les envois tombés en panne de réseau sont
**relancés automatiquement** (3 essais) dès que le réseau revient.

**Pas de doublon** : chaque envoi porte un identifiant unique. Si le serveur avait déjà enregistré le message mais
que la réponse s'est perdue en route, le réessai ne crée pas un deuxième message.

### Notifications
- Un message écrit **depuis le web** déclenche désormais aussi la notification sur le téléphone du destinataire
  (avant, seuls les messages écrits depuis le mobile en envoyaient).
- **Bug corrigé** : pour un message privé, la notification contenait le mauvais identifiant de conversation
  (celui du destinataire), donc toucher la notification renvoyait « conversation inaccessible ». Elle ouvre maintenant
  la bonne conversation, avec le bon titre ; le canal Général reste en lecture seule pour ceux qui n'y publient pas.
- Pas de notification en double quand vous êtes déjà dans la conversation ; les messages d'un même échange se
  regroupent dans une seule notification.
- Icône de notification monochrome (sinon Android affiche un carré blanc).
- Le serveur ne supprime plus les jetons d'appareils sur n'importe quelle erreur 400, et journalise les refus de
  Firebase dans `var/log/` (utile pour comprendre une notification qui n'arrive pas).

### Renommage
L'application s'appelle **CITRAC PROCESS** (nom sous l'icône, titre, en-tête de l'accueil, version web).
> Vous aviez écrit « PRTOCESS » : j'ai supposé une faute de frappe pour **PROCESS**. Pour un autre nom, il suffit de
> remplacer le texte dans `AndroidManifest.xml` (`android:label`), `lib/main.dart`, `lib/i18n/strings.dart`,
> `lib/screens/home_screen.dart` et `web/`.
>
> Je n'ai **volontairement pas** changé l'identifiant technique `com.citrac.citrac_stock_mobile` ni le nom du package
> Dart : les modifier casserait le lien avec Firebase (`google-services.json`) et empêcherait la mise à jour par-dessus
> l'application déjà installée.

---

## 2. Installation

### Serveur (CitracGmao)
1. Copier les fichiers de `CitracGmao_messagerie_modifs.zip` dans le projet (mêmes chemins).
2. Lancer la migration (nouvelle colonne `client_id`) :
   ```bash
   php bin/console doctrine:migrations:migrate
   php bin/console cache:clear
   ```
   La migration `Version20261120120000` (colonnes `remis_at` et `lu_at`) doit aussi avoir été exécutée : elle existait
   déjà dans votre projet, mais l'entité ne les déclarait pas. C'est maintenant fait.
3. Vérifier : `php bin/console lint:twig templates/chat` puis ouvrir la messagerie web.

### Mobile
1. Copier les fichiers de `citrac_process_mobile_modifs.zip` dans le projet Flutter (mêmes chemins).
2. ```bash
   flutter clean
   flutter pub get
   flutter analyze
   flutter build apk --release
   ```
   Aucune nouvelle dépendance n'est ajoutée.

---

## 3. Notifications quand l'application est fermée : configuration Firebase (obligatoire)

Dans le projet que vous m'avez envoyé, **il n'y a pas de `google-services.json`** et rien ne prouve que le serveur
ait ses identifiants Firebase. Sans ces deux éléments, **aucune notification ne peut arriver**, quel que soit le code :
l'application l'indique en silence et continue de fonctionner. C'est la seule étape que je ne peux pas faire pour vous.

1. Console Firebase (console.firebase.google.com) : créer un projet (ou prendre celui de CITRAC).
2. Ajouter une application **Android** avec le nom de paquet exact **`com.citrac.citrac_stock_mobile`**.
3. Télécharger `google-services.json` et le placer dans **`android/app/google-services.json`**, puis refaire
   `flutter clean` et recompiler l'APK.
4. Console Firebase → Paramètres du projet → **Comptes de service** → « Générer une nouvelle clé privée » (fichier JSON).
5. Sur le **serveur**, dans `.env.local` :
   ```
   CITRAC_FCM_PROJECT_ID=identifiant-du-projet-firebase
   CITRAC_FCM_COMPTE_SERVICE=/chemin/absolu/vers/service-account.json
   ```
   (ou le contenu du JSON encodé en base64 sur un hébergement où l'on ne peut pas déposer de fichier hors du dossier
   public). Vider le cache. Le fichier de clé ne doit **jamais** être dans un dossier accessible depuis le web.
6. Le serveur doit pouvoir joindre `oauth2.googleapis.com` et `fcm.googleapis.com` en HTTPS (extension PHP `openssl`
   active).

**Vérifier que c'est branché** : se connecter sur le téléphone puis regarder la réponse de
`POST /api/mobile/notifications/token` : `"push": true` signifie que le serveur est configuré.

### Sur le téléphone
- Android 13 et plus : accepter la demande d'autorisation des notifications au premier lancement.
- Certaines marques (Tecno, Infinix, Xiaomi, Huawei, Oppo…) coupent les applications en arrière-plan. Dans les réglages
  de l'appareil, pour CITRAC PROCESS : **autoriser le démarrage automatique** et **désactiver l'optimisation de la
  batterie**. Une application « forcée à s'arrêter » par l'utilisateur ne reçoit plus rien jusqu'à sa réouverture.

---

## 4. Essai de recette (10 minutes)

1. Deux comptes : A sur le web, B sur le téléphone avec l'application **fermée** (retirée des applications récentes).
2. A écrit à B : B reçoit la notification ; A voit **une coche**, puis **deux coches grises** quand le téléphone a
   reçu la notification ou l'application est ouverte.
3. B touche la notification : la bonne conversation s'ouvre ; chez A, les coches passent au **bleu**.
4. Sur le téléphone de B, couper les données mobiles, écrire à A : le message passe au **rouge** (« Pas de connexion »),
   remettre les données : il part tout seul, **une seule fois**.
5. Écrire dans une liste de diffusion : « Lu par 1/N » puis le compteur monte quand d'autres l'ouvrent.

## 5. Limites à connaître

- « **Remis** » est fiable quand l'application est ouverte ou quand le téléphone exécute le code de réception de la
  notification. Application fermée, Android affiche la notification lui-même sans lancer l'application : dans ce cas le
  message passe à « remis » à l'ouverture de l'application (ou de la liste des conversations).
- Dans un groupe, « lu » signifie « au moins une personne ». Le détail « 3/12 » donne le nombre de lecteurs, pas leurs noms.
- Les anciens messages n'ont pas de date de remise : ils s'affichent « envoyé » ou « lu » selon ce qui était enregistré.
- Aucune version iOS n'existe dans ce projet (pas de dossier `ios`) : la configuration Apple (APNs) n'est pas traitée.

## Fichiers modifiés
**Serveur** : `Entity/Chat/MessageChat.php`, `Repository/Chat/MessageChatRepository.php`, `LectureChatRepository.php`,
`Controller/Api/ApiChatMobileController.php`, `Controller/Chat/ChatController.php`, `Service/Chat/NotificationPush.php`,
`templates/chat/index.html.twig` ; **nouveaux** : `Service/Chat/NotifieurChat.php`,
`Migrations/Version20261123120000.php`.

**Mobile** : `models/chat_message.dart`, `screens/chat_screen.dart`, `screens/home_screen.dart`,
`services/chat_service.dart`, `services/api_client.dart`, `services/notification_service.dart`, `main.dart`,
`i18n/strings.dart`, `config.dart`, `pubspec.yaml`, `AndroidManifest.xml`, `res/values/colors.xml`, fichiers `web/`,
documents ; **nouveau** : `res/drawable/ic_stat_notification.xml`.
