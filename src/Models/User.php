<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class User
{
  private static bool $verificationColumnsEnsured = false;
  private PDO $db;

  public function __construct(PDO $db)
  {
    $this->db = $db;
    $this->ensureEmailVerificationColumns();
  }

  public function findByUsernameOrEmail(string $identifier): ?object
  {
    $user = null;
    try {
      $stmt = $this->db->prepare('SELECT user_id, username, email, password, email_verified, email_verified_at FROM users WHERE username = ? OR email = ?');
      $stmt->execute([$identifier, $identifier]);
      $user = $stmt->fetch();
    } catch (\Throwable $e) {
      $stmt = $this->db->prepare('SELECT user_id, username, email, password FROM users WHERE username = ? OR email = ?');
      $stmt->execute([$identifier, $identifier]);
      $user = $stmt->fetch();
      if ($user) {
        $user->email_verified = 1;
        $user->email_verified_at = null;
      }
    }
    if (!$user) return null;
    try {
      $stmt2 = $this->db->prepare('SELECT profile_photo FROM users WHERE user_id = ?');
      $stmt2->execute([$user->user_id]);
      $row = $stmt2->fetch();
      if ($row && $row->profile_photo !== null) $user->profile_photo = $row->profile_photo;
    } catch (\Throwable $e) {
    }
    return $user;
  }

  public function findById(int $userId): ?object
  {
    $stmt = $this->db->prepare('SELECT user_id, username, email, password FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
  }

  public function findByEmail(string $email): ?object
  {
    try {
      $stmt = $this->db->prepare('SELECT user_id, username, email, password, email_verified, email_verified_at FROM users WHERE email = ? LIMIT 1');
      $stmt->execute([$email]);
      $row = $stmt->fetch();
      return $row ?: null;
    } catch (\Throwable $e) {
      $stmt = $this->db->prepare('SELECT user_id, username, email, password FROM users WHERE email = ? LIMIT 1');
      $stmt->execute([$email]);
      $row = $stmt->fetch();
      if ($row) {
        $row->email_verified = 1;
        $row->email_verified_at = null;
      }
      return $row ?: null;
    }
  }

  public function findByUsername(string $username): ?object
  {
    try {
      $stmt = $this->db->prepare('SELECT user_id, username, email, password, email_verified, email_verified_at FROM users WHERE username = ? LIMIT 1');
      $stmt->execute([$username]);
      $row = $stmt->fetch();
      return $row ?: null;
    } catch (\Throwable $e) {
      $stmt = $this->db->prepare('SELECT user_id, username, email, password FROM users WHERE username = ? LIMIT 1');
      $stmt->execute([$username]);
      $row = $stmt->fetch();
      if ($row) {
        $row->email_verified = 1;
        $row->email_verified_at = null;
      }
      return $row ?: null;
    }
  }

  public function getPrescriptionUrl(int $userId): ?string
  {
    $stmt = $this->db->prepare('SELECT prescription FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row->prescription ?? null;
  }

  public function getMedicalCertificateUrl(int $userId): ?string
  {
    $stmt = $this->db->prepare('SELECT medical_certificate FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row->medical_certificate ?? null;
  }

  public function getProfileWithFiles(int $userId): ?object
  {
    try {
      $stmt = $this->db->prepare('SELECT user_id, username, email, profile_photo, prescription, medical_certificate, prescription_file_name, medical_certificate_file_name FROM users WHERE user_id = ?');
      $stmt->execute([$userId]);
      return $stmt->fetch() ?: null;
    } catch (\Throwable $e) {
      if (strpos($e->getMessage(), 'Unknown column') !== false) {
        $stmt = $this->db->prepare('SELECT user_id, username, email FROM users WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
      }
      throw $e;
    }
  }

  public function updateProfile(int $userId, string $username, string $email, string $hashedPassword): void
  {
    $stmt = $this->db->prepare('UPDATE users SET username = ?, email = ?, password = ? WHERE user_id = ?');
    $stmt->execute([$username, $email, $hashedPassword, $userId]);
  }

  public function updatePassword(int $userId, string $hashedPassword): void
  {
    $stmt = $this->db->prepare('UPDATE users SET password = ? WHERE user_id = ?');
    $stmt->execute([$hashedPassword, $userId]);
  }

  public function updatePrescription(int $userId, string $url, ?string $fileName = null): void
  {
    try {
      $stmt = $this->db->prepare('UPDATE users SET prescription = ?, prescription_file_name = COALESCE(?, prescription_file_name) WHERE user_id = ?');
      $stmt->execute([$url, $fileName, $userId]);
    } catch (\Throwable $e) {
      if (strpos($e->getMessage(), 'Unknown column') === false) throw $e;
      $stmt = $this->db->prepare('UPDATE users SET prescription = ? WHERE user_id = ?');
      $stmt->execute([$url, $userId]);
    }
  }

  public function updateMedicalCertificate(int $userId, string $url, ?string $fileName = null): void
  {
    try {
      $stmt = $this->db->prepare('UPDATE users SET medical_certificate = ?, medical_certificate_file_name = COALESCE(?, medical_certificate_file_name) WHERE user_id = ?');
      $stmt->execute([$url, $fileName, $userId]);
    } catch (\Throwable $e) {
      if (strpos($e->getMessage(), 'Unknown column') === false) throw $e;
      $stmt = $this->db->prepare('UPDATE users SET medical_certificate = ? WHERE user_id = ?');
      $stmt->execute([$url, $userId]);
    }
  }

  public function updateProfilePhoto(int $userId, string $url): void
  {
    $stmt = $this->db->prepare('UPDATE users SET profile_photo = ? WHERE user_id = ?');
    $stmt->execute([$url, $userId]);
  }

  public function getSignatureUrl(int $userId): ?string
  {
    try {
      $stmt = $this->db->prepare('SELECT e_signature FROM users WHERE user_id = ?');
      $stmt->execute([$userId]);
      $row = $stmt->fetch();
      return $row->e_signature ?? null;
    } catch (\Throwable $e) {
      return null;
    }
  }

  public function updateSignature(int $userId, string $url): void
  {
    $stmt = $this->db->prepare('UPDATE users SET e_signature = ? WHERE user_id = ?');
    $stmt->execute([$url, $userId]);
  }

  public function create(string $username, string $email, string $hashedPassword): int
  {
    try {
      $stmt = $this->db->prepare('INSERT INTO users (username, email, password, email_verified, email_verified_at) VALUES (?, ?, ?, 0, NULL)');
      $stmt->execute([$username, $email, $hashedPassword]);
    } catch (\Throwable $e) {
      if (strpos($e->getMessage(), 'Unknown column') === false) throw $e;
      $stmt = $this->db->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
      $stmt->execute([$username, $email, $hashedPassword]);
    }
    return (int) $this->db->lastInsertId();
  }

  public function markEmailVerified(int $userId): void
  {
    try {
      $stmt = $this->db->prepare('UPDATE users SET email_verified = 1, email_verified_at = NOW() WHERE user_id = ?');
      $stmt->execute([$userId]);
    } catch (\Throwable $e) {
      if (strpos($e->getMessage(), 'Unknown column') !== false) {
        return;
      }
      throw $e;
    }
  }

  public function isEmailVerified(int $userId): bool
  {
    try {
      $stmt = $this->db->prepare('SELECT email_verified FROM users WHERE user_id = ?');
      $stmt->execute([$userId]);
      $row = $stmt->fetch();
      if (!$row) return false;
      return ((int) ($row->email_verified ?? 0)) === 1;
    } catch (\Throwable $e) {
      if (strpos($e->getMessage(), 'Unknown column') !== false) {
        return true;
      }
      throw $e;
    }
  }

  private function ensureEmailVerificationColumns(): void
  {
    if (self::$verificationColumnsEnsured) {
      return;
    }

    $statements = [
      "ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0",
      "ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL",
    ];

    foreach ($statements as $sql) {
      try {
        $this->db->exec($sql);
      } catch (\Throwable $e) {
        $msg = (string) $e->getMessage();
        if (stripos($msg, 'Duplicate column name') !== false || stripos($msg, 'already exists') !== false) {
          continue;
        }
      }
    }

    self::$verificationColumnsEnsured = true;
  }
}
