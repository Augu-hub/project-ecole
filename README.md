# Project Ecole

Plateforme de gestion scolaire du Complexe Catholique Père Aupiais (Cotonou, Bénin) : back-office administratif, espace enseignant et API pour l'application mobile des parents.

## Fonctionnalités

- **Scolarité** : années scolaires, niveaux, classes, matières, élèves, réinscriptions, import d'élèves.
- **Évaluations** : saisie des notes, cahier de notes, périodes, types de note, bulletins.
- **Finances** : frais de dossier, tranches de scolarité, paiements en ligne (FedaPay).
- **Services** : cantine, uniformes (catalogue et ventes), certificats de scolarité vérifiables.
- **Communication** : communiqués, messagerie temps réel (Laravel Reverb), notifications push (Firebase).
- **Organisation** : emplois du temps, créneaux horaires, gestion du personnel et des accès par rôle.

## Stack technique

| Couche | Outils |
| --- | --- |
| Back-end | PHP 8.2+, Laravel 12, Sanctum, Reverb |
| Front-end | Blade, Tailwind CSS, Alpine.js, Vite |
| Documents | DomPDF, TCPDF, PhpSpreadsheet |
| Intégrations | FedaPay, Firebase, Google API |
| Tests | Pest |

## Installation locale

Prérequis : PHP 8.2+, Composer, Node.js, une base MySQL (ou SQLite).

```bash
composer setup
```

Cette commande installe les dépendances, crée le `.env` à partir de `.env.example`, génère la clé, lance les migrations et compile les assets. Renseignez ensuite dans `.env` la base de données et les variables `ECOLE_*`.

Pour développer (serveur, file d'attente et Vite en parallèle) :

```bash
composer dev
php artisan reverb:start   # messagerie temps réel
```

## Tests

```bash
composer test
```

## Structure du projet

```
app/
  Http/Controllers/
    Admin/        back-office
    Api/          API de l'application parents
    Enseignant/   espace enseignant
  Models/         modèles Eloquent
  Services/       logique métier (bulletins, bordereaux, emplois du temps…)
database/         migrations, factories, seeders
deploy/           configuration serveur (Supervisor, .env de production)
resources/views/  vues Blade
routes/           web, api, channels, console
scripts/          outils annexes (génération PDF des notes)
tests/            tests Pest (Feature, Unit)
```

## Déploiement

- `deploy/supervisor/reverb-ccpa.conf` : programme Supervisor qui maintient le serveur Reverb actif.
- `deploy/.env.production` : variables d'environnement du serveur. Ce fichier contient des secrets et n'est jamais versionné.
