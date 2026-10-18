-- ============================================================================
-- Migrations "Emploi du temps des enseignants" — script SQL prêt pour exécution
-- manuelle en production (MySQL), équivalent aux migrations Laravel :
--   - 2026_07_25_062144_create_creneaux_horaires_table
--   - 2026_07_25_062146_create_emplois_du_temps_table
--
-- Nouvelles tables uniquement, aucune donnée existante modifiée.
--
-- IMPORTANT :
--   1. Faire une sauvegarde de la base avant exécution (mysqldump).
--   2. Exécuter les 2 blocs DANS L'ORDRE ci-dessous (emplois_du_temps référence
--      creneaux_horaires).
--   3. Après exécution, penser à configurer les créneaux horaires réels de
--      l'établissement depuis Admin > Emploi du temps > Créneaux horaires
--      (aucune donnée n'est pré-remplie, volontairement, pour ne pas deviner
--      les horaires réels de l'école).
-- ============================================================================

CREATE TABLE creneaux_horaires (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  heure_debut TIME NOT NULL,
  heure_fin TIME NOT NULL,
  ordre SMALLINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


CREATE TABLE emplois_du_temps (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  classe_annee_id BIGINT UNSIGNED NOT NULL,
  matiere_id BIGINT UNSIGNED NOT NULL,
  jour_semaine ENUM('lundi','mardi','mercredi','jeudi','vendredi','samedi') NOT NULL,
  creneau_horaire_id BIGINT UNSIGNED NOT NULL,
  salle VARCHAR(255) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  CONSTRAINT emplois_du_temps_classe_annee_id_foreign FOREIGN KEY (classe_annee_id) REFERENCES classe_annees(id),
  CONSTRAINT emplois_du_temps_matiere_id_foreign FOREIGN KEY (matiere_id) REFERENCES matieres(id),
  CONSTRAINT emplois_du_temps_creneau_horaire_id_foreign FOREIGN KEY (creneau_horaire_id) REFERENCES creneaux_horaires(id),
  INDEX emplois_du_temps_classe_annee_id_jour_semaine_index (classe_annee_id, jour_semaine),
  INDEX emplois_du_temps_creneau_horaire_id_index (creneau_horaire_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


-- ============================================================================
-- Après exécution : marquer les migrations comme jouées pour que
-- `php artisan migrate` (prochain déploiement) ne les rejoue pas.
-- Remplacer <BATCH> par : SELECT MAX(batch) + 1 FROM migrations;
-- ============================================================================
-- INSERT INTO migrations (migration, batch) VALUES
--   ('2026_07_25_062144_create_creneaux_horaires_table', <BATCH>),
--   ('2026_07_25_062146_create_emplois_du_temps_table', <BATCH>);
