-- ============================================================================
-- Migrations "Fondations + sécurité" — script SQL prêt pour exécution manuelle
-- en production (MySQL), équivalent aux migrations Laravel :
--   - 2026_07_24_215142_add_cycle_to_niveaux_table
--   - 2026_07_24_215528_add_enseignant_id_to_classe_matieres_table
--   - 2026_07_24_220507_add_classe_annee_id_to_notes_table
--
-- Toutes les modifications sont additives (colonnes NULLABLE, aucune colonne
-- ni ligne supprimée) : aucune donnée existante n'est perdue ou modifiée en
-- dehors du backfill explicite documenté en étape 3.
--
-- IMPORTANT :
--   1. Faire une sauvegarde de la base avant exécution (mysqldump).
--   2. Exécuter les 3 blocs DANS L'ORDRE ci-dessous (dépendances de clés étrangères).
--   3. Après exécution, marquer ces 3 migrations comme "déjà appliquées" dans la
--      table `migrations` pour que `php artisan migrate` ne tente pas de les
--      rejouer (voir requêtes en bas de fichier).
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 1) niveaux.cycle — distinction maternelle / primaire / secondaire
--    Colonne nullable : tous les niveaux existants restent "non catégorisés"
--    jusqu'à ce qu'un admin les édite depuis Admin > Pédagogie > Niveaux.
-- ----------------------------------------------------------------------------
ALTER TABLE niveaux
  ADD COLUMN cycle ENUM('maternelle','primaire','secondaire') NULL AFTER nom;


-- ----------------------------------------------------------------------------
-- 2) classe_matieres.enseignant_id — lien classe/matière -> enseignant assigné
--    Nullable : les associations existantes restent "non assignées" jusqu'à
--    ce qu'un admin assigne un enseignant depuis Admin > Coefficients.
-- ----------------------------------------------------------------------------
ALTER TABLE classe_matieres
  ADD COLUMN enseignant_id BIGINT UNSIGNED NULL AFTER coefficient,
  ADD CONSTRAINT classe_matieres_enseignant_id_foreign
    FOREIGN KEY (enseignant_id) REFERENCES enseignants(id) ON DELETE SET NULL;


-- ----------------------------------------------------------------------------
-- 3) notes.classe_annee_id — classe/année dans laquelle chaque note a été
--    prise (au lieu de la déduire de la classe ACTUELLE de l'élève, ce qui
--    fausse les coefficients affichés sur les bulletins après une promotion).
--
--    Backfill : on suppose que chaque note existante a été prise dans la
--    classe actuelle de l'élève au moment de l'exécution de ce script — c'est
--    exactement le comportement implicite déjà en vigueur aujourd'hui, donc
--    AUCUNE régression visible sur les bulletins actuels.
--    ⚠️ Ce backfill ne corrige PAS rétroactivement les bulletins d'élèves déjà
--    promus avant l'exécution de ce script (leurs anciennes notes seront quand
--    même rattachées à leur classe ACTUELLE, comme avant). Seules les nouvelles
--    saisies de notes après ce script seront correctement rattachées à la
--    classe/année réelle de saisie.
-- ----------------------------------------------------------------------------
ALTER TABLE notes
  ADD COLUMN classe_annee_id BIGINT UNSIGNED NULL AFTER eleve_id,
  ADD CONSTRAINT notes_classe_annee_id_foreign
    FOREIGN KEY (classe_annee_id) REFERENCES classe_annees(id) ON DELETE SET NULL;

UPDATE notes n
JOIN eleves e ON e.id = n.eleve_id
SET n.classe_annee_id = e.classe_annee_id
WHERE n.classe_annee_id IS NULL;


-- ============================================================================
-- Après exécution des 3 blocs ci-dessus : marquer les migrations comme jouées
-- pour que `php artisan migrate` (prochain déploiement) ne les rejoue pas.
-- Remplacer <BATCH> par le numéro de batch suivant, ex :
--   SELECT MAX(batch) + 1 FROM migrations;
-- ============================================================================
-- INSERT INTO migrations (migration, batch) VALUES
--   ('2026_07_24_215142_add_cycle_to_niveaux_table', <BATCH>),
--   ('2026_07_24_215528_add_enseignant_id_to_classe_matieres_table', <BATCH>),
--   ('2026_07_24_220507_add_classe_annee_id_to_notes_table', <BATCH>);
