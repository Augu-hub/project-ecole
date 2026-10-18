-- ============================================================================
-- Migration "Notes à 2 décimales" — script SQL prêt pour exécution manuelle
-- en production (MySQL), équivalent à la migration Laravel :
--   - 2026_09_17_110000_allow_two_decimals_on_notes_valeur
--
-- La colonne notes.valeur passe de DECIMAL(4,1) à DECIMAL(4,2) : une note saisie
-- 12.25 n'est plus arrondie à 12.3. Aucune note existante n'est modifiée.
--
-- IMPORTANT :
--   1. Faire une sauvegarde de la base avant exécution (mysqldump).
--   2. Marquer la migration comme jouée (voir bas de fichier) pour que
--      `php artisan migrate` (prochain déploiement) ne la rejoue pas.
-- ============================================================================

ALTER TABLE notes MODIFY COLUMN valeur DECIMAL(4,2) NOT NULL;


-- ============================================================================
-- Après exécution : marquer la migration comme jouée pour que
-- `php artisan migrate` (prochain déploiement) ne la rejoue pas.
-- Remplacer <BATCH> par : SELECT MAX(batch) + 1 FROM migrations;
-- ============================================================================
-- INSERT INTO migrations (migration, batch) VALUES
--   ('2026_09_17_110000_allow_two_decimals_on_notes_valeur', <BATCH>);
