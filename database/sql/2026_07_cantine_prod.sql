-- ============================================================================
-- Migration "Cantine" — script SQL prêt pour exécution manuelle en production
-- (MySQL), équivalent à la migration Laravel :
--   - 2026_07_25_072134_create_cantine_offres_table
--
-- Nouvelle table uniquement, aucune donnée existante modifiée.
--
-- IMPORTANT :
--   1. Faire une sauvegarde de la base avant exécution (mysqldump).
--   2. Après exécution, penser à configurer les tarifs cantine réels depuis
--      Admin > Cantine > Tarifs (ou exécuter le seeder `CantineOffreSeeder`
--      qui contient les tarifs donnés pour l'année en cours : ticket variable
--      par mois + petit-déjeuner à 5 000 FCFA/mois).
--   3. Marquer la migration comme jouée (voir bas de fichier) pour que
--      `php artisan migrate` (prochain déploiement) ne la rejoue pas.
-- ============================================================================

CREATE TABLE cantine_offres (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  annee_scolaire_id BIGINT UNSIGNED NOT NULL,
  mois TINYINT UNSIGNED NOT NULL,
  type ENUM('ticket','petit_dejeuner') NOT NULL,
  montant DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY cantine_offres_annee_scolaire_id_mois_type_unique (annee_scolaire_id, mois, type),
  CONSTRAINT cantine_offres_annee_scolaire_id_foreign FOREIGN KEY (annee_scolaire_id) REFERENCES annee_scolaires(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


-- ============================================================================
-- Après exécution : marquer la migration comme jouée pour que
-- `php artisan migrate` (prochain déploiement) ne la rejoue pas.
-- Remplacer <BATCH> par : SELECT MAX(batch) + 1 FROM migrations;
-- ============================================================================
-- INSERT INTO migrations (migration, batch) VALUES
--   ('2026_07_25_072134_create_cantine_offres_table', <BATCH>);
