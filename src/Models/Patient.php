<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class Patient
{
  private PDO $db;
  private static bool $uniqueNameChecked = false;

  public function __construct(PDO $db)
  {
    $this->db = $db;
  }

  public function findById(int $id): ?object
  {
    try {
      $stmt = $this->db->prepare('SELECT id, full_name, age, gender, address, patient_type, created_at FROM patients WHERE id = ?');
      $stmt->execute([$id]);
      $row = $stmt->fetch();
      if ($row) return $row;
    } catch (\Throwable $e) {
      if (strpos($e->getMessage(), 'Unknown column') === false) throw $e;
    }
    try {
      $stmt = $this->db->prepare('SELECT id, name AS full_name, age, gender, address, patient_type, created_at FROM patients WHERE id = ?');
      $stmt->execute([$id]);
      return $stmt->fetch() ?: null;
    } catch (\Throwable $e) {
      if (strpos($e->getMessage(), 'Unknown column') !== false) {
        $stmt = $this->db->prepare('SELECT id, name AS full_name, age, gender, address, created_at FROM patients WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
      }
      return null;
    }
  }

  public function findOrCreate(array $data): int
  {
    $this->ensureUniqueNameValidation();

    $nameVal = trim($data['full_name'] ?? $data['patient_name'] ?? $data['name'] ?? '') ?: '';
    $nameVal = preg_replace('/\s+/u', ' ', $nameVal) ?: $nameVal;
    $age = isset($data['age']) && $data['age'] !== '' ? (string) $data['age'] : null;
    $gender = isset($data['gender']) && $data['gender'] !== '' ? (string) $data['gender'] : null;
    $address = isset($data['address']) && $data['address'] !== '' ? (string) $data['address'] : null;
    $patientType = isset($data['patient_type']) && $data['patient_type'] !== '' ? (string) $data['patient_type'] : null;

    foreach (['full_name', 'name'] as $nameCol) {
      try {
        $stmt = $this->db->prepare("SELECT id FROM patients WHERE $nameCol = ? ORDER BY (id REGEXP '^[0-9]+$') DESC, CAST(id AS UNSIGNED) DESC LIMIT 1");
        $stmt->execute([$nameVal]);
        $row = $stmt->fetch();
        if ($row) {
          $resolvedId = $this->resolveNumericPatientId((string) $row->id, $nameCol, $nameVal, $age, $gender, $address, $patientType);
          $this->updateDemographicsById((string) $resolvedId, [
            'age' => $age,
            'gender' => $gender,
            'address' => $address,
            'patient_type' => $patientType,
          ]);
          $this->collapseNameDuplicates($nameCol, $nameVal, (string) $resolvedId);
          return $resolvedId;
        }
        $insertedId = $this->insertPatientRow($nameCol, $nameVal, $age, $gender, $address, $patientType);
        $this->collapseNameDuplicates($nameCol, $nameVal, (string) $insertedId);
        return $insertedId;
      } catch (\Throwable $e) {
        if (strpos($e->getMessage(), 'Unknown column') !== false) continue;
        throw $e;
      }
    }
    throw new \RuntimeException('patients table must have name or full_name column');
  }

  public function updateDemographicsById(string $patientId, array $data): void
  {
    $id = trim($patientId);
    if ($id === '') {
      return;
    }

    $updates = [];
    $params = [];

    if (array_key_exists('age', $data) && $data['age'] !== null && $data['age'] !== '') {
      $updates[] = 'age = ?';
      $params[] = (int) $data['age'];
    }
    if (array_key_exists('gender', $data) && $data['gender'] !== null && trim((string) $data['gender']) !== '') {
      $updates[] = 'gender = ?';
      $params[] = trim((string) $data['gender']);
    }
    if (array_key_exists('address', $data) && $data['address'] !== null && trim((string) $data['address']) !== '') {
      $updates[] = 'address = ?';
      $params[] = trim((string) $data['address']);
    }
    $hasPatientType = false;
    if (array_key_exists('patient_type', $data) && $data['patient_type'] !== null && trim((string) $data['patient_type']) !== '') {
      $updates[] = 'patient_type = ?';
      $params[] = trim((string) $data['patient_type']);
      $hasPatientType = true;
    }

    if (empty($updates)) {
      return;
    }

    try {
      $sql = 'UPDATE patients SET ' . implode(', ', $updates) . ' WHERE id = ?';
      $stmt = $this->db->prepare($sql);
      $stmt->execute([...$params, $id]);
      return;
    } catch (\Throwable $e) {
      if (!$hasPatientType || strpos($e->getMessage(), 'Unknown column') === false) {
        return;
      }
    }

    $fallbackUpdates = [];
    $fallbackParams = [];
    if (array_key_exists('age', $data) && $data['age'] !== null && $data['age'] !== '') {
      $fallbackUpdates[] = 'age = ?';
      $fallbackParams[] = (int) $data['age'];
    }
    if (array_key_exists('gender', $data) && $data['gender'] !== null && trim((string) $data['gender']) !== '') {
      $fallbackUpdates[] = 'gender = ?';
      $fallbackParams[] = trim((string) $data['gender']);
    }
    if (array_key_exists('address', $data) && $data['address'] !== null && trim((string) $data['address']) !== '') {
      $fallbackUpdates[] = 'address = ?';
      $fallbackParams[] = trim((string) $data['address']);
    }

    if (empty($fallbackUpdates)) {
      return;
    }

    try {
      $sql = 'UPDATE patients SET ' . implode(', ', $fallbackUpdates) . ' WHERE id = ?';
      $stmt = $this->db->prepare($sql);
      $stmt->execute([...$fallbackParams, $id]);
    } catch (\Throwable $e) {
    }
  }

  public function findDistinctByUserHistory(int $userId, int $limit = 50, string $query = ''): array
  {
    $limit = max(1, min($limit, 500));
    $search = trim($query);
    $hasSearch = $search !== '';
    $like = '%' . $search . '%';

    $whereFullName = $hasSearch ? 'WHERE p.full_name LIKE ?' : '';
    $whereName = $hasSearch ? 'WHERE p.name LIKE ?' : '';
    $params = $hasSearch ? [$like] : [];

    $queries = [
      ['sql' => "SELECT p.id, p.full_name AS patient_name, p.age, p.gender, p.address, COALESCE(p.patient_type, '') AS patient_type, COALESCE(p.maintenance, '') AS maintenance, p.created_at AS last_date FROM patients p {$whereFullName} ORDER BY p.full_name ASC LIMIT {$limit}", 'params' => $params],
      ['sql' => "SELECT p.id, p.full_name AS patient_name, p.age, p.gender, p.address, COALESCE(p.patient_type, '') AS patient_type, '' AS maintenance, p.created_at AS last_date FROM patients p {$whereFullName} ORDER BY p.full_name ASC LIMIT {$limit}", 'params' => $params],
      ['sql' => "SELECT p.id, p.name AS patient_name, p.age, p.gender, p.address, COALESCE(p.patient_type, '') AS patient_type, COALESCE(p.maintenance, '') AS maintenance, p.created_at AS last_date FROM patients p {$whereName} ORDER BY p.name ASC LIMIT {$limit}", 'params' => $params],
      ['sql' => "SELECT p.id, p.name AS patient_name, p.age, p.gender, p.address, COALESCE(p.patient_type, '') AS patient_type, '' AS maintenance, p.created_at AS last_date FROM patients p {$whereName} ORDER BY p.name ASC LIMIT {$limit}", 'params' => $params],
    ];

    foreach ($queries as $query) {
      try {
        $stmt = $this->db->prepare($query['sql']);
        $stmt->execute($query['params']);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
      } catch (\Throwable $e) {
        if (strpos($e->getMessage(), 'Unknown column') === false) {
          throw $e;
        }
      }
    }

    return [];
  }

  private function resolveNumericPatientId(string $rawId, string $nameCol, string $nameVal, ?string $age, ?string $gender, ?string $address, ?string $patientType): int
  {
    if ($this->isPositiveNumericId($rawId)) {
      return (int) $rawId;
    }

    try {
      $stmt = $this->db->prepare("SELECT id FROM patients WHERE $nameCol = ? AND id REGEXP '^[0-9]+$' ORDER BY CAST(id AS UNSIGNED) DESC LIMIT 1");
      $stmt->execute([$nameVal]);
      $existingNumeric = $stmt->fetch();
      if ($existingNumeric && $this->isPositiveNumericId((string) $existingNumeric->id)) {
        $this->removePatientRowById($rawId);
        return (int) $existingNumeric->id;
      }
    } catch (\Throwable $e) {
    }

    $promoted = $this->promotePatientIdToNumeric($rawId);
    if ($promoted !== null) {
      return $promoted;
    }

    return $this->insertPatientRowWithExplicitId($nameCol, $nameVal, $age, $gender, $address, $patientType);
  }

  private function insertPatientRow(string $nameCol, string $nameVal, ?string $age, ?string $gender, ?string $address, ?string $patientType): int
  {
    try {
      $ins = $this->db->prepare("INSERT INTO patients ($nameCol, age, gender, address, patient_type) VALUES (?, ?, ?, ?, ?)");
      $ins->execute([$nameVal, $age, $gender, $address, $patientType]);
      $lastId = (string) $this->db->lastInsertId();
      if ($this->isPositiveNumericId($lastId)) {
        return (int) $lastId;
      }
      return $this->insertPatientRowWithExplicitId($nameCol, $nameVal, $age, $gender, $address, $patientType);
    } catch (\Throwable $e) {
      if (strpos($e->getMessage(), 'Unknown column') !== false) {
        try {
          $ins = $this->db->prepare("INSERT INTO patients ($nameCol, age, gender, address) VALUES (?, ?, ?, ?)");
          $ins->execute([$nameVal, $age, $gender, $address]);
          $lastId = (string) $this->db->lastInsertId();
          if ($this->isPositiveNumericId($lastId)) {
            return (int) $lastId;
          }
          return $this->insertPatientRowWithExplicitId($nameCol, $nameVal, $age, $gender, $address, null);
        } catch (\Throwable $inner) {
          if ($this->needsExplicitIdInsert($inner)) {
            return $this->insertPatientRowWithExplicitId($nameCol, $nameVal, $age, $gender, $address, null);
          }
          throw $inner;
        }
      }

      if ($this->needsExplicitIdInsert($e)) {
        return $this->insertPatientRowWithExplicitId($nameCol, $nameVal, $age, $gender, $address, $patientType);
      }

      throw $e;
    }
  }

  private function insertPatientRowWithExplicitId(string $nameCol, string $nameVal, ?string $age, ?string $gender, ?string $address, ?string $patientType): int
  {
    $id = $this->nextNumericPatientId();

    try {
      $ins = $this->db->prepare("INSERT INTO patients (id, $nameCol, age, gender, address, patient_type) VALUES (?, ?, ?, ?, ?, ?)");
      $ins->execute([$id, $nameVal, $age, $gender, $address, $patientType]);
      return (int) $id;
    } catch (\Throwable $e) {
      if (strpos($e->getMessage(), 'Unknown column') !== false) {
        $ins = $this->db->prepare("INSERT INTO patients (id, $nameCol, age, gender, address) VALUES (?, ?, ?, ?, ?)");
        $ins->execute([$id, $nameVal, $age, $gender, $address]);
        return (int) $id;
      }
      throw $e;
    }
  }

  private function promotePatientIdToNumeric(string $rawId): ?int
  {
    $sourceId = trim($rawId);
    if ($sourceId === '') {
      return null;
    }
    if ($this->isPositiveNumericId($sourceId)) {
      return (int) $sourceId;
    }

    $newId = $this->nextNumericPatientId();
    try {
      $stmt = $this->db->prepare('UPDATE patients SET id = ? WHERE id = ?');
      $stmt->execute([$newId, $sourceId]);
      if ($stmt->rowCount() > 0) {
        return (int) $newId;
      }
    } catch (\Throwable $e) {
    }

    return null;
  }

  private function collapseNameDuplicates(string $nameCol, string $nameVal, string $keepId): void
  {
    $keep = trim($keepId);
    if ($keep === '') {
      return;
    }

    try {
      $stmt = $this->db->prepare("SELECT id FROM patients WHERE $nameCol = ? AND id <> ?");
      $stmt->execute([$nameVal, $keep]);
      $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
      foreach ($rows as $row) {
        $dupId = (string) ($row->id ?? '');
        if ($dupId === '') {
          continue;
        }

        if ($this->isPositiveNumericId($dupId) && $this->isPositiveNumericId($keep)) {
          $dupNumeric = (int) $dupId;
          $keepNumeric = (int) $keep;
          foreach (['prescriptions', 'medical_certificates', 'lab_requests'] as $table) {
            try {
              $sql = "UPDATE {$table} SET patient_id = ? WHERE patient_id = ?";
              $up = $this->db->prepare($sql);
              $up->execute([$keepNumeric, $dupNumeric]);
            } catch (\Throwable $e) {
            }
          }
        }

        $this->removePatientRowById($dupId);
      }
    } catch (\Throwable $e) {
    }
  }

  private function removePatientRowById(string $id): void
  {
    $target = trim($id);
    if ($target === '') {
      return;
    }
    try {
      $del = $this->db->prepare('DELETE FROM patients WHERE id = ?');
      $del->execute([$target]);
    } catch (\Throwable $e) {
    }
  }

  private function nextNumericPatientId(): string
  {
    $stmt = $this->db->query("SELECT COALESCE(MAX(CAST(id AS UNSIGNED)), 0) AS max_id FROM patients WHERE id REGEXP '^[0-9]+$'");
    $row = $stmt->fetch();
    $max = (int) ($row->max_id ?? 0);
    return (string) ($max + 1);
  }

  private function isPositiveNumericId(string $value): bool
  {
    return preg_match('/^[0-9]+$/', $value) === 1 && (int) $value > 0;
  }

  private function needsExplicitIdInsert(\Throwable $e): bool
  {
    $msg = $e->getMessage();
    return strpos($msg, "Duplicate entry '' for key 'PRIMARY'") !== false
      || strpos($msg, "Field 'id' doesn't have a default value") !== false
      || strpos($msg, "Incorrect integer value: ''") !== false;
  }

  private function ensureUniqueNameValidation(): void
  {
    if (self::$uniqueNameChecked) {
      return;
    }

    foreach (['full_name', 'name'] as $nameCol) {
      try {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'patients' AND COLUMN_NAME = ?");
        $stmt->execute([$nameCol]);
        if ((int) $stmt->fetchColumn() === 0) {
          continue;
        }

        $indexName = $nameCol === 'full_name' ? 'uniq_patients_full_name' : 'uniq_patients_name';
        $sql = "ALTER TABLE patients ADD UNIQUE INDEX {$indexName} ({$nameCol})";
        $this->db->exec($sql);
      } catch (\Throwable $e) {
      }
    }

    self::$uniqueNameChecked = true;
  }
}
