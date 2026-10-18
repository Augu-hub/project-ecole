-- ============================================================================
-- Migration "Journal des connexions" — script SQL prêt pour exécution manuelle
-- en production (MySQL), équivalent à la migration Laravel :
--   - 2026_08_01_211338_create_connexion_logs_table
--
-- Nouvelle table uniquement, aucune donnée existante modifiée.
--
-- IMPORTANT :
--   1. Faire une sauvegarde de la base avant exécution (mysqldump).
--   2. Marquer la migration comme jouée (voir bas de fichier) pour que
--      `php artisan migrate` (prochain déploiement) ne la rejoue pas.
-- ============================================================================

CREATE TABLE connexion_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT connexion_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT connexion_logs_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES parents(id),
  KEY connexion_logs_parent_id_created_at_index (parent_id, created_at)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


-- ============================================================================
-- Après exécution : marquer la migration comme jouée pour que
-- `php artisan migrate` (prochain déploiement) ne la rejoue pas.
-- Remplacer <BATCH> par : SELECT MAX(batch) + 1 FROM migrations;
-- ============================================================================
-- INSERT INTO migrations (migration, batch) VALUES
--   ('2026_08_01_211338_create_connexion_logs_table', <BATCH>);
