<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class Prescription
{
  private static bool $columnsEnsured = false;
  private PDO $db;
  private Patient $patient;

  public function __construct(PDO $db)
  {
    $this->db = $db;
    $this->patient = new Patient($db);
  }

  public function create(array $data): int
  {
    $this->ensureAdditionalColumns();

    $patientId = $data['patient_id'] ?? null;
    if ($patientId !== null && (!is_numeric((string) $patientId) || (int) $patientId <= 0)) {
      $patientId = null;
    }
    if ($patientId === null) {
      $patientId = $this->patient->findOrCreate([
        'full_name' => $data['patient_name'] ?? $data['name'] ?? '',
        'age' => $data['age'] ?? null,
        'gender' => $data['gender'] ?? null,
        'address' => $data['address'] ?? null,
        'patient_type' => $data['patient_type'] ?? null,
      ]);
    }

    if ($patientId !== null) {
      $this->patient->updateDemographicsById((string) $patientId, [
        'age' => $data['age'] ?? null,
        'gender' => $data['gender'] ?? null,
        'address' => $data['address'] ?? null,
        'patient_type' => $data['patient_type'] ?? null,
      ]);
    }

    $meds = is_array($data['medications'] ?? null) ? json_encode($data['medications']) : '[]';
    $reasonForReferral = trim((string) ($data['reason_for_referral'] ?? ''));
    $maintenance = trim((string) ($data['maintenance'] ?? ''));

    try {
      $stmt = $this->db->prepare('INSERT INTO prescriptions (user_id, patient_id, date_issued, diagnosis, notes, medications, reason_for_referral) VALUES (?, ?, ?, ?, ?, ?, ?)');
      $stmt->execute([
        $data['user_id'],
        $patientId,
        $data['date_issued'] ?? null,
        ($data['diagnosis'] ?? '') === '' ? null : $data['diagnosis'],
        ($data['notes'] ?? '') === '' ? null : $data['notes'],
        $meds,
        $reasonForReferral === '' ? null : $reasonForReferral,
      ]);
    } catch (\Throwable $e) {
      if (strpos($e->getMessage(), 'Unknown column') === false) {
        throw $e;
      }

      $stmt = $this->db->prepare('INSERT INTO prescriptions (user_id, patient_id, date_issued, diagnosis, notes, medications) VALUES (?, ?, ?, ?, ?, ?)');
      $stmt->execute([
        $data['user_id'],
        $patientId,
        $data['date_issued'] ?? null,
        ($data['diagnosis'] ?? '') === '' ? null : $data['diagnosis'],
        ($data['notes'] ?? '') === '' ? null : $data['notes'],
        $meds,
      ]);
    }

    if ($patientId !== null && $maintenance !== '') {
      try {
        $stmt = $this->db->prepare('UPDATE patients SET maintenance = ? WHERE id = ?');
        $stmt->execute([$maintenance, (int) $patientId]);
      } catch (\Throwable $e) {
      }
    }

    return (int) $this->db->lastInsertId();
  }

  public function findByUserId(int $userId, int $limit = 100): array
  {
    $this->ensureAdditionalColumns();

    $limit = max(1, min($limit, 10000));
    $queries = [
      "SELECT pr.id, pr.user_id, pr.patient_id, pr.date_issued, pr.medications, COALESCE(p.maintenance, '') AS maintenance, COALESCE(pr.reason_for_referral, '') AS reason_for_referral, pr.created_at, p.full_name AS patient_name, p.age, p.gender, p.address FROM prescriptions pr LEFT JOIN patients p ON p.id = pr.patient_id WHERE pr.user_id = ? ORDER BY pr.created_at DESC LIMIT {$limit}",
      "SELECT pr.id, pr.user_id, pr.patient_id, pr.date_issued, pr.medications, COALESCE(p.maintenance, '') AS maintenance, COALESCE(pr.reason_for_referral, '') AS reason_for_referral, pr.created_at, p.name AS patient_name, p.age, p.gender, p.address FROM prescriptions pr LEFT JOIN patients p ON p.id = pr.patient_id WHERE pr.user_id = ? ORDER BY pr.created_at DESC LIMIT {$limit}",
      "SELECT pr.id, pr.user_id, pr.patient_id, pr.date_issued, pr.medications, COALESCE(p.maintenance, '') AS maintenance, '' AS reason_for_referral, pr.created_at, p.full_name AS patient_name, p.age, p.gender, p.address FROM prescriptions pr LEFT JOIN patients p ON p.id = pr.patient_id WHERE pr.user_id = ? ORDER BY pr.created_at DESC LIMIT {$limit}",
      "SELECT pr.id, pr.user_id, pr.patient_id, pr.date_issued, pr.medications, COALESCE(p.maintenance, '') AS maintenance, '' AS reason_for_referral, pr.created_at, p.name AS patient_name, p.age, p.gender, p.address FROM prescriptions pr LEFT JOIN patients p ON p.id = pr.patient_id WHERE pr.user_id = ? ORDER BY pr.created_at DESC LIMIT {$limit}",
    ];
    foreach ($queries as $q) {
      try {
        $stmt = $this->db->prepare($q);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
      } catch (\Throwable $e) {
        if (strpos($e->getMessage(), 'Unknown column') === false) throw $e;
      }
    }
    return [];
  }

  public function findByPatientId(int $userId, int $patientId, int $limit = 200): array
  {
    $this->ensureAdditionalColumns();

    $limit = max(1, min($limit, 10000));
    $queries = [
      "SELECT pr.id, pr.user_id, pr.patient_id, pr.date_issued, pr.medications, COALESCE(p.maintenance, '') AS maintenance, COALESCE(pr.reason_for_referral, '') AS reason_for_referral, pr.created_at, p.full_name AS patient_name, p.age, p.gender, p.address FROM prescriptions pr LEFT JOIN patients p ON p.id = pr.patient_id WHERE pr.user_id = ? AND pr.patient_id = ? ORDER BY pr.created_at DESC LIMIT {$limit}",
      "SELECT pr.id, pr.user_id, pr.patient_id, pr.date_issued, pr.medications, COALESCE(p.maintenance, '') AS maintenance, COALESCE(pr.reason_for_referral, '') AS reason_for_referral, pr.created_at, p.name AS patient_name, p.age, p.gender, p.address FROM prescriptions pr LEFT JOIN patients p ON p.id = pr.patient_id WHERE pr.user_id = ? AND pr.patient_id = ? ORDER BY pr.created_at DESC LIMIT {$limit}",
      "SELECT pr.id, pr.user_id, pr.patient_id, pr.date_issued, pr.medications, COALESCE(p.maintenance, '') AS maintenance, '' AS reason_for_referral, pr.created_at, p.full_name AS patient_name, p.age, p.gender, p.address FROM prescriptions pr LEFT JOIN patients p ON p.id = pr.patient_id WHERE pr.user_id = ? AND pr.patient_id = ? ORDER BY pr.created_at DESC LIMIT {$limit}",
      "SELECT pr.id, pr.user_id, pr.patient_id, pr.date_issued, pr.medications, COALESCE(p.maintenance, '') AS maintenance, '' AS reason_for_referral, pr.created_at, p.name AS patient_name, p.age, p.gender, p.address FROM prescriptions pr LEFT JOIN patients p ON p.id = pr.patient_id WHERE pr.user_id = ? AND pr.patient_id = ? ORDER BY pr.created_at DESC LIMIT {$limit}",
    ];
    foreach ($queries as $q) {
      try {
        $stmt = $this->db->prepare($q);
        $stmt->execute([$userId, $patientId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
      } catch (\Throwable $e) {
        if (strpos($e->getMessage(), 'Unknown column') === false) throw $e;
      }
    }
    return [];
  }

  public function findDistinctPatientsByUserId(int $userId, int $limit = 50): array
  {
    $this->ensureAdditionalColumns();

    $limit = max(1, min($limit, 100));
    try {
      $stmt = $this->db->prepare("SELECT p.id, p.full_name AS patient_name, p.age, p.gender, p.address, p.patient_type, COALESCE(p.maintenance, '') AS maintenance, MAX(COALESCE(pr.date_issued, pr.created_at)) AS last_date FROM patients p INNER JOIN prescriptions pr ON pr.patient_id = p.id WHERE pr.user_id = ? GROUP BY p.id, p.full_name, p.age, p.gender, p.address, p.patient_type, p.maintenance ORDER BY last_date DESC LIMIT {$limit}");
      $stmt->execute([$userId]);
      return $stmt->fetchAll(PDO::FETCH_OBJ);
    } catch (\Throwable $e) {
      try {
        $stmt = $this->db->prepare("SELECT p.id, p.name AS patient_name, p.age, p.gender, p.address, COALESCE(p.maintenance, '') AS maintenance, MAX(COALESCE(pr.date_issued, pr.created_at)) AS last_date FROM patients p INNER JOIN prescriptions pr ON pr.patient_id = p.id WHERE pr.user_id = ? GROUP BY p.id, p.name, p.age, p.gender, p.address, p.maintenance ORDER BY last_date DESC LIMIT {$limit}");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
      } catch (\Throwable $e2) {
      }
    }
    return [];
  }

  public function deleteById(int $userId, int $id): bool
  {
    $stmt = $this->db->prepare('DELETE FROM prescriptions WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    return $stmt->rowCount() > 0;
  }

  private function ensureAdditionalColumns(): void
  {
    if (self::$columnsEnsured) {
      return;
    }

    $statements = [
      "ALTER TABLE patients ADD COLUMN maintenance VARCHAR(255) NULL",
      "ALTER TABLE prescriptions ADD COLUMN reason_for_referral TEXT NULL",
    ];

    foreach ($statements as $sql) {
      try {
        $this->db->exec($sql);
      } catch (\Throwable $e) {
      }
    }

    try {
      $this->db->exec("UPDATE patients p INNER JOIN (SELECT pr.patient_id, SUBSTRING_INDEX(GROUP_CONCAT(pr.maintenance ORDER BY COALESCE(NULLIF(pr.date_issued, '0000-00-00'), pr.created_at) DESC, pr.id DESC SEPARATOR '\\n'), '\\n', 1) AS latest_maintenance FROM prescriptions pr WHERE pr.maintenance IS NOT NULL AND pr.maintenance <> '' GROUP BY pr.patient_id) x ON x.patient_id = p.id SET p.maintenance = x.latest_maintenance WHERE (p.maintenance IS NULL OR p.maintenance = '')");
    } catch (\Throwable $e) {
    }

    try {
      $this->db->exec("ALTER TABLE prescriptions DROP COLUMN maintenance");
    } catch (\Throwable $e) {
    }

    self::$columnsEnsured = true;
  }
}
