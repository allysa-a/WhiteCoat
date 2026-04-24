<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class PasswordReset
{
    private PDO $db;
    private static bool $tableEnsured = false;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->ensureTable();
    }

    public function createToken(int $userId, string $tokenHash, string $otpHash, int $ttlSeconds): void
    {
        $stmt = $this->db->prepare('INSERT INTO password_resets (user_id, token_hash, otp_hash, expires_at) VALUES (?, ?, ?, FROM_UNIXTIME(UNIX_TIMESTAMP() + ?))');
        $stmt->execute([$userId, $tokenHash, $otpHash, $ttlSeconds]);
    }

    public function findValidToken(string $tokenHash): ?object
    {
        $stmt = $this->db->prepare('SELECT id, user_id, token_hash, otp_hash, expires_at, used_at FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at >= NOW() ORDER BY id DESC LIMIT 1');
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function consumeValidToken(string $tokenHash): ?object
    {
        $row = $this->findValidToken($tokenHash);
        if (!$row) {
            return null;
        }

        $this->markUsedById((int) $row->id);

        return $row;
    }

    public function consumeValidOtpByUserId(int $userId, string $otpHash): ?object
    {
        $stmt = $this->db->prepare('SELECT id, user_id, token_hash, otp_hash, expires_at, used_at FROM password_resets WHERE user_id = ? AND otp_hash = ? AND used_at IS NULL AND expires_at >= NOW() ORDER BY id DESC LIMIT 1');
        $stmt->execute([$userId, $otpHash]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $this->markUsedById((int) $row->id);
        return $row;
    }

    public function markUsedById(int $id): void
    {
        $up = $this->db->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
        $up->execute([$id]);
    }

    public function invalidatePendingByUserId(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
        $stmt->execute([$userId]);
    }

    private function ensureTable(): void
    {
        if (self::$tableEnsured) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS password_resets (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      token_hash VARCHAR(128) NOT NULL,
      otp_hash VARCHAR(128) NOT NULL,
      expires_at DATETIME NOT NULL,
      used_at DATETIME NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_password_resets_user_id (user_id),
      INDEX idx_password_resets_token_hash (token_hash)
    )";

        $this->db->exec($sql);

        try {
            $this->db->exec('ALTER TABLE password_resets ADD COLUMN otp_hash VARCHAR(128) NOT NULL AFTER token_hash');
        } catch (\Throwable $e) {
            $msg = (string) $e->getMessage();
            if (stripos($msg, 'Duplicate column name') === false && stripos($msg, 'already exists') === false) {
                throw $e;
            }
        }

        self::$tableEnsured = true;
    }
}
