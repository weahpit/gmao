# Liaison ZKTECO — import des pointages dans la GMAO

## 1. Ce que contient la base fournie (att2000.mdb)

La base Access de la pointeuse a ete examinee. Structure utile :

| Element | Detail |
|---|---|
| `USERINFO` | `USERID` (cle), `Badgenumber` (le badge), `Name`, `DEFAULTDEPTID`, `CardNo` |
| `CHECKINOUT` | `USERID`, `CHECKTIME` (date + heure du passage), `CHECKTYPE` (`I` = entree), `VERIFYCODE`, `SENSORID`, `sn` |
| `DEPARTMENTS` | `DEPTID`, `DEPTNAME` (ici : `CITRAC`) |
| `Req_UserCheck` | requete enregistree dans le fichier (celle que vous utilisez) |

Remarque : dans le fichier fourni, `Badgenumber` et `Name` contiennent des
numeros (`1`, `2`, `3`) et non les noms des employes. La correspondance se fait
donc par le **badge**, pas par le nom : renseignez le champ « Code de pointage »
de la fiche employe avec la meme valeur que `Badgenumber`.

## 2. Comment l'application lit les donnees

Dans l'ordre :

1. elle interroge la **requete `Req_UserCheck`** (variable `ZKTECO_REQUETE`) ;
2. si le pilote ODBC ne sait pas lire les requetes (c'est frequent : beaucoup
   de pilotes ne voient que les tables), elle lit directement `CHECKINOUT`
   jointe a `USERINFO` sur `USERID`.

Le message affiche a la fin de l'import indique la source reellement utilisee
(« requete Req_UserCheck » ou « tables USERINFO + CHECKINOUT »).

Les colonnes sont reconnues par leur nom (`Badgenumber`, `Name`, `CHECKTIME`,
`CHECKTYPE`...), sans tenir compte des majuscules. Si votre requete renvoie
d'autres libelles, l'application s'y adapte tant qu'un champ de badge et un
champ de date sont presents.

## 3. Installation sur le poste qui voit la pointeuse

Le serveur web n'a generalement pas acces au fichier de la pointeuse. Deux
solutions :

**A. Import depuis un poste Windows du service RH** (recommande)

1. Installer le pilote « Microsoft Access Driver (*.mdb, *.accdb) ».
2. Creer une source de donnees ODBC (nom : `ZKTECO`) qui pointe sur
   `att2000.mdb`.
3. Activer l'extension `php_odbc` dans le PHP de ce poste.
4. Y lancer l'import (voir section 5).

**B. Copie du fichier puis import cote serveur**

1. Copier `att2000.mdb` sur le serveur (dossier non accessible par le web).
2. Installer `mdbtools` + `php-odbc`.
3. Renseigner `ZKTECO_ACCESS_FICHIER`.

**C. Sans pilote : export CSV** (toujours possible)

Depuis le logiciel ZKTECO, exporter les passages en CSV, puis lancer la
commande avec `--csv=chemin/du/fichier.csv`.

## 4. Variables (.env.local)

```
# Source : un DSN ODBC ou un fichier .mdb/.accdb
ZKTECO_ACCESS_DSN=ZKTECO
#ZKTECO_ACCESS_FICHIER=/var/zkteco/att2000.mdb

# Requete utilisee en priorite (laisser vide pour lire directement les tables)
ZKTECO_REQUETE=Req_UserCheck

# Noms reels releves dans votre base
ZKTECO_TABLE_USERS=USERINFO
ZKTECO_TABLE_POINTAGES=CHECKINOUT
ZKTECO_COL_USERID=USERID
ZKTECO_COL_BADGE=Badgenumber
ZKTECO_COL_NOM=Name
ZKTECO_COL_CHECKTIME=CHECKTIME
ZKTECO_COL_CHECKTYPE=CHECKTYPE

# Horaires de reference pour les retards et heures supplementaires
ZKTECO_HEURE_ARRIVEE=08:00
ZKTECO_HEURE_DEPART=17:00
```

## 5. Utilisation

```
# 1. Verifier la connexion, les tables et les colonnes de la requete
php bin/console app:pointage:zkteco --diagnostic

# 2. Simuler un import (aucune ecriture en base)
php bin/console app:pointage:zkteco --du=2026-11-01 --au=2026-11-20 --simulation

# 3. Importer reellement
php bin/console app:pointage:zkteco --du=2026-11-01 --au=2026-11-20

# Variantes
php bin/console app:pointage:zkteco --sans-requete --du=2026-11-01 --au=2026-11-20
php bin/console app:pointage:zkteco --csv=var/zkteco/export.csv --du=2026-11-01
```

Options : `--diagnostic`, `--du`, `--au`, `--csv`, `--sans-requete`,
`--simulation`, `--tolerance` (minutes de retard ignorees, 5 par defaut),
`--lot` (pointages enregistres par paquet, 200 par defaut).

## 6. Ce que fait l'import

- Regroupe les passages par employe et par jour.
- Premier passage = arrivee, dernier passage = depart (un seul passage dans la
  journee ne fabrique pas de depart).
- Calcule le retard par rapport a `ZKTECO_HEURE_ARRIVEE` (tolerance deduite)
  et les heures supplementaires apres `ZKTECO_HEURE_DEPART`.
- Cree ou met a jour le pointage du jour (aucun doublon).
- Signale les badges sans employe correspondant, avec la liste des numeros.

## 7. Planification quotidienne (cron)

```
30 7 * * * cd /chemin/vers/gmao && php bin/console app:pointage:zkteco --du=$(date +\%F) --au=$(date +\%F) >> var/log/pointage.log 2>&1
```

## 8. En cas de probleme

| Message | Cause probable | Solution |
|---|---|---|
| « Le pilote PHP odbc n'est pas active » | extension absente | activer `php_odbc`, redemarrer PHP. En attendant : `--csv` |
| « fichier Access introuvable » | chemin ou droits | verifier `ZKTECO_ACCESS_FICHIER` et les droits de lecture |
| « requete non lisible par le pilote ODBC » | pilote limité aux tables | l'import bascule seul sur USERINFO/CHECKINOUT |
| « badge(s) sans employe correspondant » | `code de pointage` vide | renseigner la fiche employe |
| Aucun passage trouve | periode vide ou date stockee autrement | verifier `--diagnostic`, essayer `--csv` |
