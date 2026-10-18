-- ============================================================================
-- Migration "Certificat de scolarité" — script SQL prêt pour exécution manuelle
-- en production (MySQL), équivalent à la migration Laravel :
--   - 2026_07_25_054720_create_certificats_scolarite_table
--
-- Nouvelle table uniquement, aucune donnée existante modifiée.
--
-- IMPORTANT :
--   1. Faire une sauvegarde de la base avant exécution (mysqldump).
--   2. Après exécution, marquer la migration comme "déjà appliquée" (voir bas
--      de fichier) pour que `php artisan migrate` ne la rejoue pas.
--   3. Penser à définir les variables d'environnement ECOLE_NOM / ECOLE_ADRESSE /
--      ECOLE_TEL / ECOLE_DIRECTEUR dans le .env de production (config/ecole.php) —
--      sinon les valeurs par défaut du code seront utilisées sur les documents PDF.
-- ============================================================================

CREATE TABLE certificats_scolarite (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  eleve_id BIGINT UNSIGNED NOT NULL,
  classe_annee_id BIGINT UNSIGNED NOT NULL,
  numero_unique VARCHAR(30) NOT NULL,
  motif VARCHAR(255) NULL,
  genere_par_user_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY certificats_scolarite_numero_unique_unique (numero_unique),
  CONSTRAINT certificats_scolarite_eleve_id_foreign FOREIGN KEY (eleve_id) REFERENCES eleves(id),
  CONSTRAINT certificats_scolarite_classe_annee_id_foreign FOREIGN KEY (classe_annee_id) REFERENCES classe_annees(id),
  CONSTRAINT certificats_scolarite_genere_par_user_id_foreign FOREIGN KEY (genere_par_user_id) REFERENCES users(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


-- ============================================================================
-- Après exécution : marquer la migration comme jouée pour que
-- `php artisan migrate` (prochain déploiement) ne la rejoue pas.
-- Remplacer <BATCH> par : SELECT MAX(batch) + 1 FROM migrations;
-- ============================================================================
-- INSERT INTO migrations (migration, batch) VALUES
--   ('2026_07_25_054720_create_certificats_scolarite_table', <BATCH>);
