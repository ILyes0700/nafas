-- =====================================================
-- 2026-05-05 — H1 v2 : Téléconsultation in-app + salle d'attente 15 min
-- =====================================================
-- Idempotent (peut être ré-exécutée sans risque).

CREATE TABLE IF NOT EXISTS telemed_requests (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  citizen_id      INT NOT NULL,
  room            VARCHAR(80) NOT NULL,           -- nom de salle Jitsi unique
  status          ENUM('waiting','joined','closed','expired') NOT NULL DEFAULT 'waiting',
  joined_health_id INT NULL,                       -- agent santé qui a rejoint
  requested_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at      DATETIME NOT NULL,               -- requested_at + 15 min
  joined_at       DATETIME NULL,
  closed_at       DATETIME NULL,
  KEY idx_telemed_status (status),
  KEY idx_telemed_citizen (citizen_id, status),
  KEY idx_telemed_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
