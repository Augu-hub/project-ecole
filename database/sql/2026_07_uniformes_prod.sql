-- ============================================================================
-- Migration "Uniformes" — script SQL prêt pour exécution manuelle en production
-- (MySQL), équivalent aux migrations Laravel :
--   - 2026_07_26_100033_create_uniformes_table
--   - 2026_07_26_100034_create_uniforme_ventes_table
--
-- Nouvelles tables uniquement, aucune donnée existante modifiée.
--
-- IMPORTANT :
--   1. Faire une sauvegarde de la base avant exécution (mysqldump).
--   2. Après exécution, penser à configurer le catalogue réel (types, tailles,
--      prix, stock) depuis Admin > Uniformes (ou exécuter le seeder de
--      démonstration `UniformeSeeder`, qui ne contient que des données
--      indicatives à ajuster).
--   3. Marquer les migrations comme jouées (voir bas de fichier) pour que
--      `php artisan migrate` (prochain déploiement) ne les rejoue pas.
-- ============================================================================

CREATE TABLE uniformes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(100) NOT NULL,
  taille VARCHAR(20) NOT NULL,
  prix DECIMAL(10,2) NOT NULL,
  quantite_stock INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY uniformes_type_taille_unique (type, taille)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE uniforme_ventes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  uniforme_id BIGINT UNSIGNED NOT NULL,
  eleve_id BIGINT UNSIGNED NOT NULL,
  quantite INT UNSIGNED NOT NULL,
  prix_unitaire DECIMAL(10,2) NOT NULL,
  montant_total DECIMAL(10,2) NOT NULL,
  date_vente DATE NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  CONSTRAINT uniforme_ventes_uniforme_id_foreign FOREIGN KEY (uniforme_id) REFERENCES uniformes(id),
  CONSTRAINT uniforme_ventes_eleve_id_foreign FOREIGN KEY (eleve_id) REFERENCES eleves(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


-- ============================================================================
-- Après exécution : marquer les migrations comme jouées pour que
-- `php artisan migrate` (prochain déploiement) ne les rejoue pas.
-- Remplacer <BATCH> par : SELECT MAX(batch) + 1 FROM migrations;
-- ============================================================================
-- INSERT INTO migrations (migration, batch) VALUES
--   ('2026_07_26_100033_create_uniformes_table', <BATCH>),
--   ('2026_07_26_100034_create_uniforme_ventes_table', <BATCH>);
