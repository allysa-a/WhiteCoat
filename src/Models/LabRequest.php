<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class LabRequest
{
  private PDO $db;

  public function __construct(PDO $db)
  {
    $this->db = $db;
  }

  public function create(array $data): int
  {
    $tests = is_array($data['selected_tests'] ?? null) ? json_encode($data['selected_tests']) : '[]';
    $stmt = $this->db->prepare('INSERT INTO lab_requests (user_id, patient_id, date_issued, selected_tests, other_tests, remarks) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
      $data['user_id'],
      $data['patient_id'] ?? null,
      $data['date_issued'] ?? null,
      $tests,
      ($data['other_tests'] ?? '') === '' ? null : $data['other_tests'],
      ($data['remarks'] ?? '') === '' ? null : $data['remarks'],
    ]);
    return (int) $this->db->lastInsertId();
  }

  public function findByUserId(int $userId, int $limit = 200): array
  {
    $limit = max(1, min($limit, 10000));
    try {
      $stmt = $this->db->prepare("SELECT lr.id, lr.user_id, lr.patient_id, lr.date_issued, lr.selected_tests, lr.other_tests, lr.remarks, lr.created_at, p.full_name AS patient_name, p.age, p.gender, p.address FROM lab_requests lr LEFT JOIN patients p ON p.id = lr.patient_id WHERE lr.user_id = ? ORDER BY lr.created_at DESC LIMIT {$limit}");
      $stmt->execute([$userId]);
      return $stmt->fetchAll(PDO::FETCH_OBJ);
    } catch (\Throwable $e) {
      if (strpos($e->getMessage(), "doesn't exist") !== false) return [];
      try {
        $stmt = $this->db->prepare("SELECT lr.id, lr.user_id, lr.patient_id, lr.date_issued, lr.selected_tests, lr.other_tests, lr.remarks, lr.created_at, p.name AS patient_name, p.age, p.gender, p.address FROM lab_requests lr LEFT JOIN patients p ON p.id = lr.patient_id WHERE lr.user_id = ? ORDER BY lr.created_at DESC LIMIT {$limit}");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
      } catch (\Throwable $e2) {
      }
    }
    return [];
  }

  public function findByPatientId(int $userId, int $patientId, int $limit = 200): array
  {
    $limit = max(1, min($limit, 10000));
    try {
      $stmt = $this->db->prepare("SELECT lr.id, lr.user_id, lr.patient_id, lr.date_issued, lr.selected_tests, lr.other_tests, lr.remarks, lr.created_at, p.full_name AS patient_name, p.age, p.gender, p.address FROM lab_requests lr LEFT JOIN patients p ON p.id = lr.patient_id WHERE lr.user_id = ? AND lr.patient_id = ? ORDER BY lr.created_at DESC LIMIT {$limit}");
      $stmt->execute([$userId, $patientId]);
      return $stmt->fetchAll(PDO::FETCH_OBJ);
    } catch (\Throwable $e) {
      try {
        $stmt = $this->db->prepare("SELECT lr.id, lr.user_id, lr.patient_id, lr.date_issued, lr.selected_tests, lr.other_tests, lr.remarks, lr.created_at, p.name AS patient_name, p.age, p.gender, p.address FROM lab_requests lr LEFT JOIN patients p ON p.id = lr.patient_id WHERE lr.user_id = ? AND lr.patient_id = ? ORDER BY lr.created_at DESC LIMIT {$limit}");
        $stmt->execute([$userId, $patientId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
      } catch (\Throwable $e2) {
      }
    }
    return [];
  }

  public function deleteById(int $userId, int $id): bool
  {
    $stmt = $this->db->prepare('DELETE FROM lab_requests WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    return $stmt->rowCount() > 0;
  }
}
