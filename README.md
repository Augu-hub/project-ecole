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

## Données de démonstration

Un jeu de données complet et réaliste permet de présenter l'application comme un établissement en activité : le Complexe Catholique Père Aupiais avec deux années scolaires (2024-2025 archivée, 2025-2026 active, trois trimestres chacune), 12 classes de la Sixième à la Terminale (une classe par niveau au collège, séries A, C et D au lycée), environ 300 élèves par an, près de 250 parents (fratries regroupées), 30 enseignants et le personnel administratif.

Tous les modules sont alimentés : notes de chaque élève dans chaque matière pour chaque trimestre des deux années (composition, devoir des matières principales, interrogation pour l'année active, conduite ; environ 29 000 notes), coefficients par niveau et série, réinscriptions (promus, redoublants, nouveaux, départs), scolarité en trois tranches, frais de dossier et paiements (espèces, FedaPay, chèque, virement, carte ; familles à jour, en retard ou en difficulté), cantine, uniformes, emploi du temps sans conflit, certificats de scolarité, communiqués, messagerie parents/personnel et journal des connexions à l'application mobile. Noms, prénoms, quartiers et numéros de téléphone (format `01XXXXXXXX`) sont béninois. Les données sont reproductibles : deux chargements produisent exactement les mêmes enregistrements.

Le chargement est volontaire (il n'est jamais lancé par `DatabaseSeeder`), refusé en production et exige une base vide :

```bash
php artisan migrate:fresh --seed --seeder=DemoSeeder
```

Il ne prend que quelques secondes. Le code se trouve dans `database/seeders/DemoSeeder.php` (orchestration) et `database/seeders/Demo/` (un seeder par module, listes de noms et programme scolaire dans `Demo/Data/`).

Comptes de démonstration (mot de passe `password` pour tous les comptes, y compris les comptes générés) :

| Rôle | Identifiant | Remarque |
|---|---|---|
| Administrateur | `admin@ccpa.test` | accès complet |
| Directeur | `directeur@ccpa.test` | |
| Censeur | `censeur@ccpa.test` | |
| Économe | `econome@ccpa.test` | aussi `comptable@ccpa.test` (rôle Économe) |
| Préfet | `prefet@ccpa.test` | chef d'équipe des surveillants |
| Surveillant | `surveillant@ccpa.test` | rattaché au préfet |
| Saisisseur de notes | `saisisseur@ccpa.test` | |
| Enseignant | `enseignant@ccpa.test` | professeur de mathématiques |
| Parent | `parent@ccpa.test` | application mobile : téléphone `0197000101`, trois enfants scolarisés |

Les autres comptes (enseignants, parents) suivent le modèle `prenom.nom@ccpa.test` et `prenom.nom@parents.ccpa.test`.
