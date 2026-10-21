# Programmation des plans de maintenance préventive — Installation

Cette fonctionnalité permet de **programmer des plans de maintenance préventive
systématique** (comme votre « PLAN DE MAINTENANCE 2026 ») : on définit une
intervention récurrente (checklist, zone, périodicité) et l'application :

1. calcule automatiquement toutes les **échéances** de l'année,
2. les affiche dans un **planning annuel** (grille semaines × interventions, cases colorées),
3. **génère automatiquement les fiches d'intervention** quand une échéance arrive à terme
   (manuellement via le bouton « Lancer la programmation » ou via un cron quotidien).

## 1. Fichiers à copier (en respectant l'arborescence)

```
src/Entity/Maintenance/PlanMaintenance.php
src/Entity/Maintenance/PlanMaintenanceOccurrence.php
src/Repository/Maintenance/PlanMaintenanceRepository.php
src/Repository/Maintenance/PlanMaintenanceOccurrenceRepository.php
src/Services/PlanMaintenanceScheduler.php
src/Controller/Maintenance/PlanMaintenanceController.php
src/Controller/Api/Maintenance/ApiPlanMaintenanceController.php
src/Command/GenererFichesPreventivesCommand.php
templates/maintenance/plan_maintenance/index.html.twig
```

> Rappel PSR-4 : un fichier = une classe (nom du fichier = nom de la classe),
> sinon le service n'est pas auto-câblé.

## 2. Base de données (deux nouvelles tables)

**Recommandé — Doctrine Migrations :**

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

**Alternative** (si vous gérez le SQL à la main) : exécutez
`migrations/plan_maintenance_postgres.sql` (PostgreSQL). Adaptez les schémas des
clés étrangères si vos tables `zone_exploitation` / `equipement_type` ne sont pas
en schéma `public`.

## 3. Menu (sidebar) — activer le lien « Plans préventifs »

Dans `templates/base.html.twig`, remplacez le lien inactif existant :

```twig
<a href="#" class="nav-subitem {{ app.request.attributes.get('_route') starts with 'preventive' ? 'active' }}">
    <span class="nav-label">Plans préventifs</span>
</a>
```

par :

```twig
<a href="{{ path('preventive_plan_maintenance') }}" class="nav-subitem {{ app.request.attributes.get('_route') starts with 'preventive' ? 'active' }}">
    <span class="nav-label">Plans préventifs</span>
</a>
```

> Le préfixe de route `preventive_` est déjà pris en compte par la logique
> `maintenanceActive` de votre sidebar : l'entrée Maintenance s'ouvrira toute seule.

## 4. Vider le cache

```bash
php bin/console cache:clear
```

## 5. Génération automatique quotidienne (cron)

Une commande console crée les fiches des échéances dues :

```bash
php bin/console app:plans:generer-fiches
# ou pour une date précise :
php bin/console app:plans:generer-fiches --date=2026-01-08
```

Exemple de cron (tous les jours à 6h) :

```
0 6 * * *  cd /chemin/du/projet && php bin/console app:plans:generer-fiches >> var/log/plans.log 2>&1
```

## Fonctionnement résumé

- **Périodicités** : Journalière, Hebdomadaire (+ jour de la semaine), Mensuelle,
  Trimestrielle, Semestrielle, Annuelle, avec un **intervalle** (« toutes les N »).
- À l'enregistrement d'un plan, les échéances de l'année en cours sont
  **matérialisées** (occurrences), sans doublon et sans toucher aux occurrences
  déjà réalisées.
- Le **planning annuel** reproduit votre tableau Excel : lignes = interventions,
  colonnes = semaines (« Semaine du »), cases colorées = dates d'entretien
  systématique.
- **Lancer la programmation** crée les fiches d'intervention pour toutes les
  échéances échues (statut initial « Ouvert »), reliées à la zone / au type /
  à l'équipement du plan, et marque l'occurrence comme traitée.

## Sécurité

- Toutes les routes (page + API : création / suppression / programmation /
  consultation) sont protégées par le même garde que le reste du projet :
  `if (!$this->getUser()) { return $this->redirectToRoute('app_login'); }`.
  C'est fonctionnel mais minimal : avant la mise en production, ajoutez un
  véritable pare-feu Symfony et un contrôle de rôle (ex. `ROLE_ADMIN` sur
  `savePlanMaintenance` / `deletePlanMaintenance` / `genererFichesPreventivesDues`)
  pour que ces routes `/api/*` ne soient pas accessibles à tout utilisateur
  connecté.

## Validation locale

L'environnement de génération n'a pas PHP/Composer : ces fichiers n'ont **pas**
été compilés ni rendus. Validez chez vous avec :

```bash
composer install
php bin/console cache:clear
symfony server:start
```
