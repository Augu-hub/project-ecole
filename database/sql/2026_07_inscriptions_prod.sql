-- ============================================================================
-- Migrations "Inscriptions / Réinscriptions / Frais de dossier" — script SQL prêt
-- pour exécution manuelle en production (MySQL), équivalent aux migrations Laravel :
--   - 2026_07_24_234329_add_libelle_to_fichiers_table
--   - 2026_07_24_234523_create_reinscriptions_table
--   - 2026_07_24_234651_add_payable_to_paiements_table
--   - 2026_07_24_235009_create_frais_dossiers_table
--
-- Toutes les modifications sont additives (colonnes/tables NULLABLE ou nouvelles,
-- aucune colonne ni ligne supprimée). tranche_id sur paiements est CONSERVÉ.
--
-- IMPORTANT :
--   1. Faire une sauvegarde de la base avant exécution (mysqldump).
--   2. Exécuter les 4 blocs DANS L'ORDRE ci-dessous (dépendances de clés étrangères).
--   3. Après exécution, marquer ces 4 migrations comme "déjà appliquées" (voir bas de fichier).
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 1) fichiers.libelle — libellé libre pour les documents du dossier élève
-- ----------------------------------------------------------------------------
ALTER TABLE fichiers
  ADD COLUMN libelle VARCHAR(255) NULL AFTER type_fichier;


-- ----------------------------------------------------------------------------
-- 2) reinscriptions — historique des décisions d'inscription/réinscription
-- ----------------------------------------------------------------------------
CREATE TABLE reinscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  eleve_id BIGINT UNSIGNED NOT NULL,
  annee_scolaire_id BIGINT UNSIGNED NOT NULL,
  classe_annee_precedente_id BIGINT UNSIGNED NULL,
  classe_annee_id BIGINT UNSIGNED NULL,
  type ENUM('nouveau','promu','redoublant') NOT NULL,
  moyenne_suggestion DECIMAL(5,2) NULL,
  decide_par_user_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  CONSTRAINT reinscriptions_eleve_id_foreign FOREIGN KEY (eleve_id) REFERENCES eleves(id),
  CONSTRAINT reinscriptions_annee_scolaire_id_foreign FOREIGN KEY (annee_scolaire_id) REFERENCES annee_scolaires(id),
  CONSTRAINT reinscriptions_classe_annee_precedente_id_foreign FOREIGN KEY (classe_annee_precedente_id) REFERENCES classe_annees(id),
  CONSTRAINT reinscriptions_classe_annee_id_foreign FOREIGN KEY (classe_annee_id) REFERENCES classe_annees(id),
  CONSTRAINT reinscriptions_decide_par_user_id_foreign FOREIGN KEY (decide_par_user_id) REFERENCES users(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 3) paiements.payable_type / payable_id — cible générique du paiement
--    (Tranche de scolarité aujourd'hui, FraisDossier ci-dessous, puis cantine/
--    uniformes plus tard). tranche_id reste renseigné en parallèle (double
--    écriture) : aucune lecture existante (reçu PDF, API mobile) n'est impactée.
-- ----------------------------------------------------------------------------
ALTER TABLE paiements
  ADD COLUMN payable_type VARCHAR(255) NULL AFTER tranche_id,
  ADD COLUMN payable_id BIGINT UNSIGNED NULL AFTER payable_type,
  ADD INDEX paiements_payable_type_payable_id_index (payable_type, payable_id);

-- Backfill : tous les paiements existants sont des paiements de scolarité (Tranche).
UPDATE paiements
SET payable_type = 'App\\Models\\Tranche',
    payable_id = tranche_id
WHERE tranche_id IS NOT NULL
  AND payable_type IS NULL;


-- ----------------------------------------------------------------------------
-- 4) frais_dossiers — un montant configurable par classe_annee (secondaire)
-- ----------------------------------------------------------------------------
CREATE TABLE frais_dossiers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  classe_annee_id BIGINT UNSIGNED NOT NULL,
  montant DECIMAL(10,2) NOT NULL,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY frais_dossiers_classe_annee_id_unique (classe_annee_id),
  CONSTRAINT frais_dossiers_classe_annee_id_foreign FOREIGN KEY (classe_annee_id) REFERENCES classe_annees(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


-- ============================================================================
-- Après exécution des 4 blocs ci-dessus : marquer les migrations comme jouées
-- pour que `php artisan migrate` (prochain déploiement) ne les rejoue pas.
-- Remplacer <BATCH> par le numéro de batch suivant, ex :
--   SELECT MAX(batch) + 1 FROM migrations;
-- ============================================================================
-- INSERT INTO migrations (migration, batch) VALUES
--   ('2026_07_24_234329_add_libelle_to_fichiers_table', <BATCH>),
--   ('2026_07_24_234523_create_reinscriptions_table', <BATCH>),
--   ('2026_07_24_234651_add_payable_to_paiements_table', <BATCH>),
--   ('2026_07_24_235009_create_frais_dossiers_table', <BATCH>);
