# Module Demande d'Achat (DA) — CITRAC GMAO

Module complet (backend + front) pour le circuit des demandes d'achat :

```
Demandeur (tout utilisateur)      Magasin (ROLE_PDR)         Direction (ROLE_DG)
--------------------------        -------------------        -------------------
cree une DA  ---------------->    enregistre + soumet ---->  valide / refuse
(NOUVELLE)                        (SOUMISE)                   (VALIDEE / REFUSEE)
                                       ^                            |
                                       |          validee           v
   suit le tracking (token)   <--  sert les articles  <----  confirmation
                                   (SERVIE)
                                       |  stock insuffisant
                                       v
                                  commande fournisseur (EN_COMMANDE) --> SERVIE
```

## 1. Fichiers fournis (a copier dans votre projet)

| Fichier | Emplacement dans votre projet |
|---|---|
| `src/Entity/DemandeAchat.php` | `src/Entity/` |
| `src/Repository/DemandeAchatRepository.php` | `src/Repository/` |
| `src/Controller/DemandeAchatController.php` | `src/Controller/` |
| `templates/demande_achat/*.html.twig` | `templates/demande_achat/` |
| `public/js/api/stock/DemandeAchat.js` | `public/js/api/stock/` |
| `sql/migration_demande_achat.sql` | a executer une fois sur la base |

## 2. Base de donnees (PostgreSQL `gmaobd`)

Il n'y a pas de migrations Doctrine dans ce projet : executez le SQL fourni.

```bash
psql -U <utilisateur> -d gmaobd -f sql/migration_demande_achat.sql
```

Le script cree la table `demande_achat` (+ index d'unicite sur `numero` et
`token`) et ajoute, si les tables existent, les cles etrangeres vers
`services` et `fournisseur` (en `ON DELETE SET NULL`). Si la cle primaire de
ces tables n'est pas `id`, adaptez les `REFERENCES` dans le script.

> Apres copie des classes : `php bin/console cache:clear`.

## 3. Statuts

| Code | Libelle |
|---|---|
| `NOUVELLE` | Nouvelle demande (creee par le demandeur) |
| `SOUMISE` | Soumise a la direction (par le magasin) |
| `VALIDEE` | Validee par la direction |
| `REFUSEE` | Refusee par la direction |
| `EN_COMMANDE` | En commande fournisseur (stock insuffisant) |
| `SERVIE` | Servie — articles sortis |
| `ANNULEE` | Annulee |

## 4. Routes

**Web**
- `GET/POST /demande-achat/nouvelle` — creer une DA (tout utilisateur authentifie)
- `GET /demande-achat/mes-demandes` — liste de SES demandes
- `GET /demande-achat/suivi/{token}` — timeline de suivi d'une DA
- `GET /magasin/demandes-achat` — file du magasinier (ROLE_PDR)
- `GET /direction/demandes-achat` — file de la direction (ROLE_DG)

**API (POST, JSON)**
- `POST /api/demande-achat/soumettre/{id}` — ROLE_PDR
- `POST /api/demande-achat/valider/{id}` — ROLE_DG
- `POST /api/demande-achat/refuser/{id}` — ROLE_DG (champ `motif` obligatoire)
- `POST /api/demande-achat/commander/{id}` — ROLE_PDR (`fournisseur_nom` ou `fournisseur`)
- `POST /api/demande-achat/servir/{id}` — ROLE_PDR (`bon_sortie_ref` facultatif)

## 5. Securite (access_control)

Les controleurs appliquent deja `denyAccessUnlessGranted(...)` (defense en
profondeur). Ajoutez en complement, dans `config/packages/security.yaml`, une
couverture *deny-by-default* coherente avec vos routes existantes :

```yaml
access_control:
    - { path: ^/api/demande-achat/(valider|refuser),   roles: ROLE_DG }
    - { path: ^/api/demande-achat/(soumettre|commander|servir), roles: ROLE_PDR }
    - { path: ^/magasin/demandes-achat,   roles: ROLE_PDR }
    - { path: ^/direction/demandes-achat, roles: ROLE_DG }
    - { path: ^/demande-achat,            roles: IS_AUTHENTICATED_FULLY }
```

> Rappel : ce projet n'a pas de pare-feu / authentification complete active.
> Durcissez `firewalls` + `access_control` avant toute mise en production
> (les routes import / upload / API restent exposees sinon).

## 6. Liens de navigation (a ajouter dans votre menu)

```twig
<a href="{{ path('da_nouvelle') }}">Nouvelle demande d'achat</a>
<a href="{{ path('da_mes_demandes') }}">Mes demandes</a>
{% if is_granted('ROLE_PDR') %}<a href="{{ path('da_magasin') }}">DA — Magasin</a>{% endif %}
{% if is_granted('ROLE_DG') %}<a href="{{ path('da_direction') }}">DA — Direction</a>{% endif %}
```

## 7. Notifications

Par defaut, la notification est **in-app** : chaque changement de statut est
visible dans les listes (magasin / direction) et dans le suivi du demandeur
(badges + timeline + compteurs). L'envoi d'e-mails (via le SMTP deja
configurable du projet) peut etre branche en option sur les transitions
de statut si vous le souhaitez.

## 8. Hypotheses retenues

- Le **demandeur** = tout utilisateur authentifie (identifie par `getUser()`),
  qui suit sa DA via un **jeton de tracking** (`token`).
- Les templates etendent `base.html.twig` et utilisent le bloc `body` ainsi
  que `asset()`. Adaptez le nom du bloc si votre layout differe.
- Le repli icone est en texte (`+` / `−`) — pas de dependance a un nom
  d'icone Lucide precis.
- Palette cacao coherente avec les vues deja livrees.

> **Non compile / non teste** : l'environnement ne dispose ni de PHP/Composer,
> ni de la base PostgreSQL. Validez en local : copie des fichiers,
> `psql ... -f migration_demande_achat.sql`, `php bin/console cache:clear`,
> puis `symfony server:start`.
