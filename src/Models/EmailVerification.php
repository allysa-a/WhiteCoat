<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class EmailVerification
{
    private PDO $db;
    private static bool $tableEnsured = false;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->ensureTable();
    }

    public function createToken(int $userId, string $tokenHash, int $ttlSeconds): void
    {
        $stmt = $this->db->prepare('INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (?, ?, FROM_UNIXTIME(UNIX_TIMESTAMP() + ?))');
        $stmt->execute([$userId, $tokenHash, $ttlSeconds]);
    }

    public function consumeValidToken(string $tokenHash): ?object
    {
        $stmt = $this->db->prepare('SELECT id, user_id, token_hash, expires_at, used_at FROM email_verifications WHERE token_hash = ? AND used_at IS NULL AND expires_at >= NOW() ORDER BY id DESC LIMIT 1');
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $up = $this->db->prepare('UPDATE email_verifications SET used_at = NOW() WHERE id = ?');
        $up->execute([$row->id]);

        return $row;
    }

    public function consumeValidCodeByUserId(int $userId, string $codeHash): ?object
    {
        $stmt = $this->db->prepare('SELECT id, user_id, token_hash, expires_at, used_at FROM email_verifications WHERE user_id = ? AND token_hash = ? AND used_at IS NULL AND expires_at >= NOW() ORDER BY id DESC LIMIT 1');
        $stmt->execute([$userId, $codeHash]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $up = $this->db->prepare('UPDATE email_verifications SET used_at = NOW() WHERE id = ?');
        $up->execute([$row->id]);

        return $row;
    }

    public function invalidatePendingByUserId(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE email_verifications SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
        $stmt->execute([$userId]);
    }

    private function ensureTable(): void
    {
        if (self::$tableEnsured) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS email_verifications (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      token_hash VARCHAR(128) NOT NULL,
      expires_at DATETIME NOT NULL,
      used_at DATETIME NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_email_verifications_user_id (user_id),
      INDEX idx_email_verifications_token_hash (token_hash)
    )";

        $this->db->exec($sql);
        self::$tableEnsured = true;
    }
}
