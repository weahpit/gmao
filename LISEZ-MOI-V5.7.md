# CITRAC GMAO — V5.7

## Contenu et précautions

Mise à jour préparée depuis votre dernier `CitracGmao.zip` (sources `src/` et `templates/`). Ne remplacez pas votre installation par un ancien ZIP.

- Export de la fiche dossier en **CSV UTF-8**, séparateur `;`, à la place du bouton Excel.
- PDF avec **TCPDF**, en-tête CITRAC et pagination sur chaque page, tableaux du dossier et photos d’empotage jointes en pages séparées. Aucun appel Dompdf dans le nouveau contrôleur.
- Lecture de la **requête Access enregistrée `Req_UserCheck`** sur le poste Windows, sans modification de la base active. Envoi des seuls passages qui n’ont pas encore été explicitement acquittés par l’API HTTPS.
- File SQLite locale persistante, dédoublonnage serveur, reprise après coupure et tâche planifiée toutes les cinq minutes.
- Votre photographie en filigrane doux sur la connexion, thème cacao conservé.
- Deux scripts d’enregistrement maintenus avec lecture de l’attribut HTML `action`, pas de la propriété DOM masquée par un champ `name=action`.

**Archive de fusion, pas une application autonome** : votre ZIP ne contient ni `composer.json`, ni `config/packages/security.yaml`, ni l’ensemble de `public/`. Gardez vos fichiers existants, bibliothèques, médias, APK, `.env.local`, pièces jointes et base de données. Seuls les nouveaux assets et les deux scripts ciblés se trouvent dans `public/`. Ces deux scripts proviennent des correctifs précédents disponibles dans cet espace de travail ; les sources PHP/Twig viennent bien du dernier ZIP. Les anciennes archives restent intactes.

## 1. Installation côté GMAO en ligne

1. Sauvegardez les fichiers et la base PostgreSQL ; testez d’abord sur une copie de votre installation.
2. Fusionnez `src/`, `templates/` et `public/` avec votre projet existant, sans effacer les autres fichiers de ces dossiers.
3. Installez TCPDF depuis la racine de votre véritable projet :

```sh
composer require tecnickcom/tcpdf:^6.8
```

Le fichier Composer absent du ZIP n’a pas été inventé ou remplacé. Ne désinstallez pas une dépendance utilisée ailleurs sans vérification ; cette fiche PDF n’utilise pas `dompdf/dompdf`.

4. Assurez-vous que vos migrations incluent le namespace `DoctrineMigrations` et le dossier `src/Migrations` (comme les migrations déjà présentes). Exécutez :

```sh
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:clear
php bin/console lint:twig templates
php bin/console lint:container
php bin/console debug:router api_presences_zkteco
php bin/console debug:router logistique_dossier_export
```

La nouvelle migration crée `rh_zkteco_passage` si nécessaire, ajoute les métadonnées Access et les index. Elle ne supprime aucun historique et son retour arrière destructif est volontairement interdit. Elle est réservée à PostgreSQL. Les anciennes empreintes déjà stockées restent conservées ; une première reprise V5.7 peut créer une empreinte plus précise pour certains anciens passages, sans créer un second pointage journalier.

5. Générez un secret imprévisible sur votre serveur :

```sh
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Ajoutez à **votre `.env.local` non public**, ou aux variables d’environnement de l’hébergeur :

```dotenv
ZKTECO_API_TOKEN=REMPLACER_PAR_LE_SECRET_GENERE
ZKTECO_HEURE_ARRIVEE=07:30
ZKTECO_HEURE_DEPART=16:30
ZKTECO_TOLERANCE=5
```

6. **Étape indispensable si votre firewall principal impose une connexion** : fusionnez l’exemple `config-exemples/zkteco-securite.yaml` dans `config/packages/security.yaml`. Placez le firewall limité à `^/api/presences/zkteco$` **avant `main` et avant tout firewall qui intercepte `/api/`**. Ne remplacez pas la sécurité du reste du site. Pour cette seule route, le contrôleur applique lui-même le secret Bearer et HTTPS ; aucun compte humain ni cookie de session requis. Le fichier d’exemple n’est volontairement pas activé automatiquement : la configuration réelle n’était pas fournie.
7. Vérifiez que l’hébergeur transmet `Authorization`, que HTTPS est actif et que Symfony reconnaît HTTPS. Derrière un reverse proxy, configurez les adresses de proxy de confiance et les en-têtes transférés de votre hébergeur ; ne faites pas confiance à tous les clients sans restriction.
8. Contrôlez les limites serveur : l’API accepte au plus 1 000 passages et 1 000 000 octets par appel. Les accès doivent être HTTPS et porter `Authorization: Bearer …`.

### Test de connectivité sans donnée personnelle

Dans PowerShell sur le poste Windows, utilisez un lot vide avant tout envoi réel :

```powershell
$Jeton = Read-Host 'Jeton API' -AsSecureString
$Clair = [System.Net.NetworkCredential]::new('', $Jeton).Password
try {
    Invoke-RestMethod -Method Post -Uri 'https://votre-domaine.tld/api/presences/zkteco' -Headers @{Authorization=('Bearer ' + $Clair)} -ContentType 'application/json' -Body '{"source":"CITRAC-ZKTECO-SANPEDRO","passages":[]}'
} finally { $Clair = $null; $Jeton = $null }
```

Réponse attendue : JSON `recus: 0` et `acquittes: []`. Une page HTML ou une redirection vers la connexion indique généralement un firewall mal positionné. HTTP 401 : secret différent ; HTTP 503 : API non configurée ; HTTP 500 : consulter la référence et `var/log/`. Ne placez jamais le secret dans une URL, une capture ou un dépôt Git.

## 2. Installation sur le poste Windows de ZKTeco

La base active reste : `C:\Program Files (x86)\ZKTeco\att2000.mdb`.

1. Copiez uniquement `passerelle-windows/` dans un dossier de travail, par exemple `C:\CITRAC\ZktecoSync` (**pas dans le dossier de la base et pas dans le répertoire public du site**).
2. Installez Python 3.11 ou plus et un pilote ODBC Microsoft Access compatible. L’architecture de **Python doit correspondre au pilote**, pas simplement au Windows ou au chemin `Program Files (x86)`. Si ACE est 32 bits, utilisez Python 32 bits. Ne forcez pas une installation ACE incompatible avec Office ; faites vérifier ce choix par votre administrateur. Le nom d’un ancien pilote peut être `Microsoft Access Driver (*.mdb)` : adaptez `pilote_odbc` si nécessaire.
3. Installez la dépendance avec le même Python qui servira dans la tâche :

```powershell
python -m pip install -r C:\CITRAC\ZktecoSync\requirements.txt
python -c "import pyodbc; print(pyodbc.drivers())"
```

4. Copiez `config.exemple.json` en `config.json`. Remplacez **`api_url` par le vrai domaine HTTPS de la GMAO, suivi de `/api/presences/zkteco`**. Gardez la même `source` à chaque exécution. Vérifiez le chemin de la base, le nom de la requête et le pilote. Aucun vrai domaine n’a été deviné.
5. Sous le compte Windows qui exécutera la tâche, configurez le **même secret** que sur le serveur :

```powershell
python C:\CITRAC\ZktecoSync\synchroniser.py --config C:\CITRAC\ZktecoSync\config.json --configurer-jeton
```

Le secret est chiffré par Windows DPAPI pour ce compte, jamais placé en clair dans le JSON ou les arguments de la tâche. Une variable d’environnement `ZKTECO_API_TOKEN` est aussi possible, mais elle passe en priorité sur le fichier DPAPI.
6. Protégez le dossier local par les droits NTFS : accès limité au compte de service et aux administrateurs. **La SQLite contient les noms, badges et passages en clair** ; le chiffrement DPAPI concerne uniquement le jeton. Ne publiez pas le dossier, ne le synchronisez pas dans un partage ouvert et sauvegardez l’état de façon sécurisée.
7. Essayez d’abord la lecture seule sans API :

```powershell
python C:\CITRAC\ZktecoSync\synchroniser.py --config C:\CITRAC\ZktecoSync\config.json --sans-envoi
```

Puis un envoi réel :

```powershell
python C:\CITRAC\ZktecoSync\synchroniser.py --config C:\CITRAC\ZktecoSync\config.json
```

8. Installez la tâche sous **exactement le même compte Windows**, avec son véritable exécutable Python :

```powershell
& C:\CITRAC\ZktecoSync\Installer-Tache.ps1 -PythonExe 'C:\Chemin\Vers\python.exe'
Start-ScheduledTask -TaskName 'CITRAC - Pointages ZKTeco'
Get-ScheduledTaskInfo -TaskName 'CITRAC - Pointages ZKTeco'
```

Le script demande le mot de passe du compte Windows pour permettre l’exécution même sans session ouverte. Il ne demande pas le secret API. Respectez la politique d’exécution PowerShell de votre entreprise, ne la désactivez pas. Une installation par l’interface du Planificateur est possible avec les mêmes paramètres : toutes les 5 minutes, exécutable Python, script et `--config`, dossier de travail, ne pas démarrer une deuxième instance, exécution sans session ouverte. Le poste doit rester allumé ; le déclencheur fourni est valable dix ans.

## 3. Fonctionnement et garanties

- Relecture de la requête complète, **par paquets de 500** : elle détecte aussi un passage ancien ajouté tardivement. Il n’y a pas de marqueur « dernière date » qui ferait perdre ces lignes. Une très grosse base entraînera cependant un parcours complet à chaque lancement ; mesurez sa durée avant de réduire l’intervalle de planification.
- Aucun `INSERT`, `UPDATE` ou `DELETE` dans Access : connexion demandée en lecture seule, unique requête SQL `SELECT`.
- La requête doit être une requête de sélection accessible sans paramètres ni formulaire ouvert. Si elle utilise des fonctions VBA/formulaires, vérifiez son fonctionnement ODBC sur le poste ; la passerelle ne contourne pas ces dépendances.
- Colonnes lues : `USERID, Badgenumber, Name, Gender, CHECKTIME, CHECKTYPE, SENSORID`. `CHECKTIME_DAY` et `CHECKTIME_TIME` sont dérivables de `CHECKTIME` et ne sont pas requises.
- Clé stable SHA-256 : source, badge, horodatage à la seconde, utilisateur, type normalisé en majuscules et capteur. `I` et `i` ont la même identité. Deux passages différant par capteur, utilisateur ou type ne sont pas confondus ; deux lignes strictement identiques à la seconde ne peuvent pas être distinguées sans identifiant source supplémentaire.
- Les noms et le genre sont transmis et conservés avec le passage ; ils ne créent pas automatiquement un salarié. Un nom modifié pour un passage déjà acquitté n’est pas renvoyé automatiquement.
- SQLite en mode WAL, écritures synchrones et verrou interprocessus. Les lignes restent en attente jusqu’à la liste explicite `acquittes` renvoyée **après la transaction serveur**. Un HTTP 200 sans cette liste n’est pas accepté.
- Après coupure entre validation serveur et acquittement local, le lot est renvoyé ; la clé unique serveur empêche sa duplication. Pas de redirection HTTP suivie, vérification TLS activée, secret absent des journaux.
- Les passages acquittés restent dans SQLite pour éviter leur renvoi. Ne supprimez pas `etat.sqlite` à chaque exécution. Si le fichier est perdu, une reprise complète ne duplique pas les nouvelles empreintes côté serveur, mais relit et renvoie l’historique.
- Codes de sortie : `0` exécution correcte, `1` problème de configuration/envoi/jeton, `2` lecture partielle ou lignes invalides. Les erreurs réseau ne déclenchent pas une boucle agressive : reprise au prochain lancement. 20 lots de 300 par exécution par défaut ; le reliquat attend la suivante.
- `--envoyer-seulement` réessaie la file sans lire Access. `--csv fichier.csv` permet un import ponctuel du modèle, **ce n’est pas le mode automatique**. `--sans-envoi` ne fait aucun appel réseau.
- En cas de lecture Access interrompue, les paquets déjà validés restent dans SQLite et l’envoi de l’ancienne file peut continuer. Les lignes invalides restent dans Access et seront revérifiées au prochain lancement ; leur numéro de lecture est journalisé sans identité personnelle.

### Rapprochement RH

Renseignez le **code de pointage** ou le **matricule** de chaque employé avec `Badgenumber`. Le serveur cherche exactement une correspondance. Zéro ou plusieurs salariés : passage enregistré, badge signalé non rapproché. Le nom fourni ne sert jamais à un rapprochement automatique risqué.

Après correction d’un badge, rejouez l’état local pour recalculer les pointages, sans créer de nouveaux passages identiques :

```powershell
python C:\CITRAC\ZktecoSync\synchroniser.py --config C:\CITRAC\ZktecoSync\config.json --rejouer --envoyer-seulement
```

Ce rejeu peut nécessiter plusieurs exécutions normales si l’historique dépasse la limite de lots. Le journal indique le **nombre** de badges non rapprochés ; la réponse API contient leur liste, à manipuler de manière confidentielle.

La règle quotidienne existante est conservée : **premier passage = arrivée ; dernier passage distinct = départ**, un seul passage ne crée pas un départ artificiel. `CHECKTYPE` est conservé, mais ne sert pas à deviner un départ ; votre modèle fourni comporte uniquement `I/i`. Les journées de nuit, horaires individuels, pauses et calculs avancés de paie ne sont pas ajoutés. Les heures manuelles plus larges déjà présentes sont conservées selon la logique existante ; le commentaire du pointage est remplacé par la provenance ZKTeco. Vérifiez les cas de correction manuelle avant mise en production.

## 4. CSV, PDF et filigrane

- CSV de fiche : format long `SECTION, LIBELLE, VALEUR 1 … VALEUR 5`, UTF-8 avec BOM, `;`, retours CRLF. Inclut données, indicateurs, suivi, certificats, traitements, conteneurs/lots, formules/CV, origine, documents et références photos. Les débuts de formule (`= + - @`) sont neutralisés pour l’ouverture dans un tableur. Un CSV ne peut pas incorporer de photographie.
- PDF : tableaux et contenu du gabarit de fiche existant ; chaque photo d’empotage est insérée comme véritable image, en gardant ses proportions, avec son nom échappé via les cellules TCPDF. Une photo manquante déclenche une erreur d’export journalisée, elle n’est pas silencieusement omise. Les **pièces photographiques historiques restent des références**, comme dans le gabarit d’origine ; les pièces Office/PDF du dossier ne sont pas fusionnées au document.
- En-tête textuel CITRAC et pied de page récurrents. Le logo CITRAC n’était pas fourni dans ce ZIP ; aucun logo fictif n’a été dessiné.
- Après fusion, faites Ctrl+F5. Le filigrane utilise `public/images/citrac-login-filigrane.jpg`, issu de votre photographie et allégé sans modifier l’original. Son intensité se règle par `--citrac-filigrane-opacite` dans `public/css/citrac-login-filigrane.css` (0,14 par défaut). Retirer son lien CSS dans `loginBase.html.twig` annule cet habillage.
- Le correctif `getAttribute('action')` est fourni dans `ajax-v5.js` et `embarquement-ajax.js`, avec un numéro de cache mis à jour. Il évite les URL contenant `[object HTMLInputElement]` ; il ne retire aucune protection serveur.

## 5. Contrôles réalisés et limites

**Réalisés ici** : 13 tests Python hors réseau réussis (relecture, passage ancien, état durable, normalisation I/i, capteur distinct, acquittement manquant/partiel/étranger, panne réseau, redirection refusée), contrôle du CSV fourni (1 000 lignes valides, 1 000 empreintes distinctes ; deuxième mise en file : zéro ajout), analyse syntaxique des trois nouveaux/modifiés fichiers PHP avec un parseur, vérification syntaxique des deux scripts JavaScript, contrôle du contenu et de l’intégrité du ZIP.

**Non réalisés** : exécution PHP/Symfony, compilation Twig, migration PostgreSQL, génération/rendu réel TCPDF, appel à votre domaine, lecture de la base Access active, chiffrement DPAPI et exécution de la tâche sur Windows. Le thème complet et ses feuilles de style d’origine manquent dans l’upload : le rendu réel de connexion doit être contrôlé dans votre installation. Ces points nécessitent vos environnements réels ; aucun déploiement ni envoi effectif à votre serveur n’est annoncé.

### Recette minimale

1. Tester CSV et PDF avec un dossier représentatif : valeurs, tableaux, accents, toutes les photos, pagination et en-tête CITRAC.
2. Vérifier la connexion sur ordinateur et mobile, absence de double encadrement et lisibilité sur le filigrane.
3. Tester l’API vide, puis un petit lot historique ; comparer le badge et les horaires avec Access et la page RH.
4. Réexécuter : aucun passage neuf, aucun double pointage journalier. Débrancher le réseau puis reprendre : la file doit se vider après retour réseau.
5. Fermer la session Windows et contrôler la tâche après dix minutes. Corriger un badge inconnu et tester le rejeu.
6. Vérifier les journaux de la passerelle et `var/log/` sans publier les secrets ni les données RH.
