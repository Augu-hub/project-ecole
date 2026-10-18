-- ============================================================================
-- Migration "Messagerie" — script SQL prêt pour exécution manuelle
-- en production (MySQL), équivalent aux migrations Laravel :
--   - 2026_05_16_140952_create_conversations_table
--   - 2026_05_16_141024_create_messages_table
--
-- Nouvelles tables uniquement, aucune donnée existante modifiée.
--
-- IMPORTANT :
--   1. Faire une sauvegarde de la base avant exécution (mysqldump).
--   2. Marquer les migrations comme jouées (voir bas de fichier) pour que
--      `php artisan migrate` (prochain déploiement) ne les rejoue pas.
-- ============================================================================

CREATE TABLE conversations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  parent_id BIGINT UNSIGNED NOT NULL,
  admin_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  CONSTRAINT conversations_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
  CONSTRAINT conversations_admin_id_foreign FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_conv (parent_id, admin_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  conversation_id BIGINT UNSIGNED NOT NULL,
  sender_id BIGINT UNSIGNED NOT NULL,
  sender_type ENUM('parent', 'admin') NOT NULL,
  body TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT messages_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT messages_sender_id_foreign FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_conv_created (conversation_id, created_at)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


-- ============================================================================
-- Après exécution : marquer les migrations comme jouées pour que
-- `php artisan migrate` (prochain déploiement) ne les rejoue pas.
-- Remplacer <BATCH> par : SELECT MAX(batch) + 1 FROM migrations;
-- ============================================================================
-- INSERT INTO migrations (migration, batch) VALUES
--   ('2026_05_16_140952_create_conversations_table', <BATCH>),
--   ('2026_05_16_141024_create_messages_table', <BATCH>);
