<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class MedicalCertificate
{
  private PDO $db;
  private Patient $patient;

  public function __construct(PDO $db)
  {
    $this->db = $db;
    $this->patient = new Patient($db);
  }

  public function create(array $data): int
  {
    $patientId = $data['patient_id'] ?? null;
    if ($patientId !== null && (!is_numeric((string) $patientId) || (int) $patientId <= 0)) {
      $patientId = null;
    }
    if ($patientId === null) {
      try {
        $patientId = $this->patient->findOrCreate([
          'full_name' => $data['patient_name'] ?? $data['name'] ?? '',
          'age' => $data['age'] ?? null,
          'gender' => $data['gender'] ?? null,
          'address' => $data['address'] ?? null,
          'patient_type' => $data['patient_type'] ?? null,
        ]);
      } catch (\Throwable $e) {
        $patientId = null;
      }
    }

    if ($patientId !== null) {
      $this->patient->updateDemographicsById((string) $patientId, [
        'age' => $data['age'] ?? null,
        'gender' => $data['gender'] ?? null,
        'address' => $data['address'] ?? null,
        'patient_type' => $data['patient_type'] ?? null,
      ]);
    }

    if ($patientId !== null) {
      try {
        $stmt = $this->db->prepare('INSERT INTO medical_certificates (user_id, patient_id, date_issued, impression, remarks) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
          $data['user_id'],
          $patientId,
          $data['date_issued'] ?? null,
          ($data['impression'] ?? '') === '' ? null : $data['impression'],
          ($data['remarks'] ?? '') === '' ? null : $data['remarks'],
        ]);
        return (int) $this->db->lastInsertId();
      } catch (\Throwable $e) {
        if (strpos($e->getMessage(), 'Unknown column') === false) throw $e;
      }
    }
    $name = trim($data['patient_name'] ?? $data['name'] ?? '');
    $stmt = $this->db->prepare('INSERT INTO medical_certificates (user_id, patient_name, age, gender, date_issued, address, impression, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
      $data['user_id'],
      $name,
      ($data['age'] ?? '') === '' ? null : $data['age'],
      ($data['gender'] ?? '') === '' ? null : $data['gender'],
      $data['date_issued'] ?? null,
      ($data['address'] ?? '') === '' ? null : $data['address'],
      ($data['impression'] ?? '') === '' ? null : $data['impression'],
      ($data['remarks'] ?? '') === '' ? null : $data['remarks'],
    ]);
    return (int) $this->db->lastInsertId();
  }

  public function findByUserId(int $userId, int $limit = 100): array
  {
    $limit = max(1, min($limit, 10000));
    $queries = [
      "SELECT mc.id, mc.user_id, mc.patient_id, mc.date_issued, mc.impression, mc.remarks, mc.created_at, p.full_name AS patient_name, p.age, p.gender, p.address FROM medical_certificates mc LEFT JOIN patients p ON p.id = mc.patient_id WHERE mc.user_id = ? ORDER BY mc.created_at DESC LIMIT {$limit}",
      "SELECT mc.id, mc.user_id, mc.patient_id, mc.date_issued, mc.impression, mc.remarks, mc.created_at, p.name AS patient_name, p.age, p.gender, p.address FROM medical_certificates mc LEFT JOIN patients p ON p.id = mc.patient_id WHERE mc.user_id = ? ORDER BY mc.created_at DESC LIMIT {$limit}",
      "SELECT id, user_id, COALESCE(patient_name, name, '') AS patient_name, age, gender, date_issued, address, impression, remarks, created_at FROM medical_certificates WHERE user_id = ? ORDER BY created_at DESC LIMIT {$limit}",
    ];
    foreach ($queries as $q) {
      try {
        $stmt = $this->db->prepare($q);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
      } catch (\Throwable $e) {
      }
    }
    return [];
  }

  public function findByPatientId(int $userId, int $patientId, int $limit = 200): array
  {
    $limit = max(1, min($limit, 10000));
    $queries = [
      "SELECT mc.id, mc.user_id, mc.patient_id, mc.date_issued, mc.impression, mc.remarks, mc.created_at, p.full_name AS patient_name, p.age, p.gender, p.address FROM medical_certificates mc LEFT JOIN patients p ON p.id = mc.patient_id WHERE mc.user_id = ? AND mc.patient_id = ? ORDER BY mc.created_at DESC LIMIT {$limit}",
      "SELECT mc.id, mc.user_id, mc.patient_id, mc.date_issued, mc.impression, mc.remarks, mc.created_at, p.name AS patient_name, p.age, p.gender, p.address FROM medical_certificates mc LEFT JOIN patients p ON p.id = mc.patient_id WHERE mc.user_id = ? AND mc.patient_id = ? ORDER BY mc.created_at DESC LIMIT {$limit}",
      "SELECT id, user_id, patient_id, COALESCE(patient_name, name, '') AS patient_name, age, gender, date_issued, address, impression, remarks, created_at FROM medical_certificates WHERE user_id = ? AND patient_id = ? ORDER BY created_at DESC LIMIT {$limit}",
    ];
    foreach ($queries as $q) {
      try {
        $stmt = $this->db->prepare($q);
        $stmt->execute([$userId, $patientId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
      } catch (\Throwable $e) {
      }
    }
    return [];
  }

  public function deleteById(int $userId, int $id): bool
  {
    $stmt = $this->db->prepare('DELETE FROM medical_certificates WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    return $stmt->rowCount() > 0;
  }
}
