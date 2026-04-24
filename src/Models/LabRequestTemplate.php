<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class LabRequestTemplate
{
  private PDO $db;

  public function __construct(PDO $db)
  {
    $this->db = $db;
  }

  private function ensureTable(): void
  {
    $sql = "CREATE TABLE IF NOT EXISTS lab_request_templates (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL UNIQUE,
      file_url VARCHAR(512) NOT NULL,
      file_name VARCHAR(255) DEFAULT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_lab_request_templates_user_id (user_id)
    )";
    try {
      $this->db->exec($sql);
    } catch (\Throwable $e) {
    }
  }

  public function getByUserId(int $userId): ?object
  {
    try {
      $stmt = $this->db->prepare('SELECT id, user_id, file_url, file_name, created_at FROM lab_request_templates WHERE user_id = ?');
      $stmt->execute([$userId]);
      $row = $stmt->fetch();
      return $row ?: null;
    } catch (\Throwable $e) {
      if (strpos($e->getMessage(), "doesn't exist") !== false) {
        $this->ensureTable();
        $stmt = $this->db->prepare('SELECT id, user_id, file_url, file_name, created_at FROM lab_request_templates WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
      }
      return null;
    }
  }

  public function upsert(int $userId, string $fileUrl, ?string $fileName = null): void
  {
    try {
      $stmt = $this->db->prepare('SELECT id FROM lab_request_templates WHERE user_id = ?');
      $stmt->execute([$userId]);
      if ($stmt->fetch()) {
        $up = $this->db->prepare('UPDATE lab_request_templates SET file_url = ?, file_name = COALESCE(?, file_name) WHERE user_id = ?');
        $up->execute([$fileUrl, $fileName, $userId]);
      } else {
        $ins = $this->db->prepare('INSERT INTO lab_request_templates (user_id, file_url, file_name) VALUES (?, ?, ?)');
        $ins->execute([$userId, $fileUrl, $fileName]);
      }
    } catch (\Throwable $e) {
      if (strpos($e->getMessage(), "doesn't exist") !== false) {
        $this->ensureTable();
        $stmt = $this->db->prepare('SELECT id FROM lab_request_templates WHERE user_id = ?');
        $stmt->execute([$userId]);
        if ($stmt->fetch()) {
          $up = $this->db->prepare('UPDATE lab_request_templates SET file_url = ?, file_name = COALESCE(?, file_name) WHERE user_id = ?');
          $up->execute([$fileUrl, $fileName, $userId]);
        } else {
          $ins = $this->db->prepare('INSERT INTO lab_request_templates (user_id, file_url, file_name) VALUES (?, ?, ?)');
          $ins->execute([$userId, $fileUrl, $fileName]);
        }
      } else {
        throw $e;
      }
    }
  }
}
