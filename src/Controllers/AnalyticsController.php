<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;

class AnalyticsController
{
  private PDO $db;

  public function __construct(PDO $db)
  {
    $this->db = $db;
  }

  public function getAnalytics(): void
  {
    $userId = $_GET['user_id'] ?? null;
    if (!$userId) {
      $this->json(400, ['message' => 'user_id is required']);
      return;
    }
    $range = $_GET['range'] ?? 'weekly';
    $patientType = $_GET['patient_type'] ?? null;
    $userId = (int) $userId;

    [$startDate, $endDate] = $this->resolveDateRange((string) $range);
    $normalizedRequestedType = $patientType ? $this->normalizePatientType((string) $patientType) : null;
    $types = ['Non-Teaching' => [], 'Teaching' => [], 'Learner' => [], 'School Head' => [], 'Related-Teaching' => []];

    foreach (['prescriptions' => 'pr', 'medical_certificates' => 'mc', 'lab_requests' => 'lr'] as $table => $alias) {
      $rows = $this->fetchAnalyticsRows($table, $alias, $userId, $startDate, $endDate);
      foreach ($rows as $row) {
        $patientId = isset($row->patient_id) ? (int) $row->patient_id : 0;
        if ($patientId <= 0) {
          continue;
        }
        $type = $this->normalizePatientType((string) ($row->patient_type ?? ''));
        if ($normalizedRequestedType !== null && strcasecmp($type, $normalizedRequestedType) !== 0) {
          continue;
        }
        if (!isset($types[$type])) {
          $types[$type] = [];
        }
        $types[$type][$patientId] = true;
      }
    }

    $patientTypes = ['Non-Teaching', 'Teaching', 'Learner', 'School Head', 'Related-Teaching'];
    $breakdown = [];
    $total = 0;
    foreach ($patientTypes as $pt) {
      $count = isset($types[$pt]) ? count($types[$pt]) : 0;
      $total += $count;
      $breakdown[] = ['patient_type' => $pt, 'count' => $count];
    }
    if (isset($types['Unknown'])) {
      $c = count($types['Unknown']);
      $total += $c;
      $breakdown[] = ['patient_type' => 'Unknown', 'count' => $c];
    }
    foreach ($breakdown as &$b) {
      $b['percentage'] = $total > 0 ? (int) round($b['count'] / $total * 100) : 0;
    }
    $this->json(200, [
      'range' => $range,
      'startDate' => $startDate . 'T00:00:00.000Z',
      'endDate' => $endDate . 'T23:59:59.000Z',
      'total' => $total,
      'breakdown' => $breakdown,
    ]);
  }

  public function exportAnalytics(): void
  {
    $userId = $_GET['user_id'] ?? null;
    if (!$userId) {
      $this->json(400, ['message' => 'user_id is required']);
      return;
    }

    $range = $_GET['range'] ?? 'weekly';
    $patientType = $_GET['patient_type'] ?? null;
    $userId = (int) $userId;
    [$startDate, $endDate] = $this->resolveDateRange((string) $range);

    $patientTypeValues = $patientType ? $this->getPatientTypeQueryValues((string) $patientType) : [];
    if ($patientTypeValues === ['']) {
      $patientTypeFilter = 'AND (p.patient_type IS NULL OR p.patient_type = \'\')';
    } elseif (count($patientTypeValues) > 0) {
      $placeholders = implode(', ', array_fill(0, count($patientTypeValues), '?'));
      $patientTypeFilter = "AND p.patient_type IN ({$placeholders})";
    } else {
      $patientTypeFilter = '';
    }

    $params = [$userId, $startDate, $endDate];
    if (count($patientTypeValues) > 0 && $patientTypeValues !== ['']) {
      $params = array_merge($params, $patientTypeValues);
    }
    $fallbackParams = $params;

    $dataRows = [];

    try {
      $sql = "SELECT pr.date_issued, pr.created_at, pr.medications, p.full_name AS patient_name, p.age, p.gender, p.address, COALESCE(p.patient_type, '') AS patient_type FROM prescriptions pr INNER JOIN patients p ON p.id = pr.patient_id WHERE pr.user_id = ? AND DATE(COALESCE(NULLIF(pr.date_issued, '0000-00-00'), pr.created_at)) BETWEEN ? AND ? $patientTypeFilter ORDER BY pr.created_at DESC";
      $stmt = $this->db->prepare($sql);
      $stmt->execute($params);
      while ($r = $stmt->fetch(PDO::FETCH_OBJ)) {
        $meds = '';
        $decoded = json_decode((string) ($r->medications ?? '[]'), true);
        if (is_array($decoded)) {
          $meds = implode('; ', array_map(static function ($x) {
            $name = trim((string) ($x['name'] ?? ''));
            $dosage = trim((string) ($x['dosage'] ?? ''));
            $frequency = trim((string) ($x['frequency'] ?? ''));
            return trim($name . ' ' . $dosage . ' ' . $frequency);
          }, $decoded));
        }

        $dataRows[] = [
          '_sort' => strtotime((string) ($r->date_issued ?: $r->created_at)) ?: 0,
          'Record Type' => 'Prescription',
          'Date Issued' => $this->formatExportDate($r->date_issued ?? null, $r->created_at ?? null),
          'Patient Name' => $this->csvText($r->patient_name ?? ''),
          'Age' => $this->csvText($r->age ?? ''),
          'Gender' => $this->csvText($r->gender ?? ''),
          'Address' => $this->csvText($r->address ?? ''),
          'Patient Type' => $this->csvText($r->patient_type ?? ''),
          'Details' => $this->csvText($meds),
          'Other Tests' => '',
          'Impression' => '',
          'Remarks' => '',
        ];
      }
    } catch (\Throwable $e) {
    }

    try {
      $sql = "SELECT mc.date_issued, mc.created_at, mc.impression, mc.remarks, p.full_name AS patient_name, p.age, p.gender, p.address, COALESCE(p.patient_type, '') AS patient_type FROM medical_certificates mc INNER JOIN patients p ON p.id = mc.patient_id WHERE mc.user_id = ? AND DATE(COALESCE(NULLIF(mc.date_issued, '0000-00-00'), mc.created_at)) BETWEEN ? AND ? $patientTypeFilter ORDER BY mc.created_at DESC";
      $stmt = $this->db->prepare($sql);
      $stmt->execute($params);
      while ($r = $stmt->fetch(PDO::FETCH_OBJ)) {
        $dataRows[] = [
          '_sort' => strtotime((string) ($r->date_issued ?: $r->created_at)) ?: 0,
          'Record Type' => 'Medical Certificate',
          'Date Issued' => $this->formatExportDate($r->date_issued ?? null, $r->created_at ?? null),
          'Patient Name' => $this->csvText($r->patient_name ?? ''),
          'Age' => $this->csvText($r->age ?? ''),
          'Gender' => $this->csvText($r->gender ?? ''),
          'Address' => $this->csvText($r->address ?? ''),
          'Patient Type' => $this->csvText($r->patient_type ?? ''),
          'Details' => '',
          'Other Tests' => '',
          'Impression' => $this->csvText($r->impression ?? ''),
          'Remarks' => $this->csvText($r->remarks ?? ''),
        ];
      }
    } catch (\Throwable $e) {
      try {
        $sql = "SELECT mc.date_issued, mc.created_at, mc.impression, mc.remarks, p.name AS patient_name, p.age, p.gender, p.address, COALESCE(p.patient_type, '') AS patient_type FROM medical_certificates mc INNER JOIN patients p ON p.id = mc.patient_id WHERE mc.user_id = ? AND DATE(COALESCE(NULLIF(mc.date_issued, '0000-00-00'), mc.created_at)) BETWEEN ? AND ? $patientTypeFilter ORDER BY mc.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($fallbackParams);
        while ($r = $stmt->fetch(PDO::FETCH_OBJ)) {
          $dataRows[] = [
            '_sort' => strtotime((string) ($r->date_issued ?: $r->created_at)) ?: 0,
            'Record Type' => 'Medical Certificate',
            'Date Issued' => $this->formatExportDate($r->date_issued ?? null, $r->created_at ?? null),
            'Patient Name' => $this->csvText($r->patient_name ?? ''),
            'Age' => $this->csvText($r->age ?? ''),
            'Gender' => $this->csvText($r->gender ?? ''),
            'Address' => $this->csvText($r->address ?? ''),
            'Patient Type' => $this->csvText($r->patient_type ?? ''),
            'Details' => '',
            'Other Tests' => '',
            'Impression' => $this->csvText($r->impression ?? ''),
            'Remarks' => $this->csvText($r->remarks ?? ''),
          ];
        }
      } catch (\Throwable $e2) {
      }
    }

    try {
      $sql = "SELECT lr.date_issued, lr.created_at, lr.selected_tests, lr.other_tests, lr.remarks, p.full_name AS patient_name, p.age, p.gender, p.address, COALESCE(p.patient_type, '') AS patient_type FROM lab_requests lr INNER JOIN patients p ON p.id = lr.patient_id WHERE lr.user_id = ? AND DATE(COALESCE(NULLIF(lr.date_issued, '0000-00-00'), lr.created_at)) BETWEEN ? AND ? $patientTypeFilter ORDER BY lr.created_at DESC";
      $stmt = $this->db->prepare($sql);
      $stmt->execute($params);
      while ($r = $stmt->fetch(PDO::FETCH_OBJ)) {
        $decodedTests = is_string($r->selected_tests ?? '') ? json_decode($r->selected_tests, true) : [];
        $testsStr = is_array($decodedTests) ? implode(', ', $decodedTests) : (string) ($r->selected_tests ?? '');

        $dataRows[] = [
          '_sort' => strtotime((string) ($r->date_issued ?: $r->created_at)) ?: 0,
          'Record Type' => 'Lab Request',
          'Date Issued' => $this->formatExportDate($r->date_issued ?? null, $r->created_at ?? null),
          'Patient Name' => $this->csvText($r->patient_name ?? ''),
          'Age' => $this->csvText($r->age ?? ''),
          'Gender' => $this->csvText($r->gender ?? ''),
          'Address' => $this->csvText($r->address ?? ''),
          'Patient Type' => $this->csvText($r->patient_type ?? ''),
          'Details' => $this->csvText($testsStr),
          'Other Tests' => $this->csvText($r->other_tests ?? ''),
          'Impression' => '',
          'Remarks' => $this->csvText($r->remarks ?? ''),
        ];
      }
    } catch (\Throwable $e) {
      try {
        $sql = "SELECT lr.date_issued, lr.created_at, lr.selected_tests, lr.other_tests, lr.remarks, p.name AS patient_name, p.age, p.gender, p.address, COALESCE(p.patient_type, '') AS patient_type FROM lab_requests lr INNER JOIN patients p ON p.id = lr.patient_id WHERE lr.user_id = ? AND DATE(COALESCE(NULLIF(lr.date_issued, '0000-00-00'), lr.created_at)) BETWEEN ? AND ? $patientTypeFilter ORDER BY lr.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($fallbackParams);
        while ($r = $stmt->fetch(PDO::FETCH_OBJ)) {
          $decodedTests = is_string($r->selected_tests ?? '') ? json_decode($r->selected_tests, true) : [];
          $testsStr = is_array($decodedTests) ? implode(', ', $decodedTests) : '';

          $dataRows[] = [
            '_sort' => strtotime((string) ($r->date_issued ?: $r->created_at)) ?: 0,
            'Record Type' => 'Lab Request',
            'Date Issued' => $this->formatExportDate($r->date_issued ?? null, $r->created_at ?? null),
            'Patient Name' => $this->csvText($r->patient_name ?? ''),
            'Age' => $this->csvText($r->age ?? ''),
            'Gender' => $this->csvText($r->gender ?? ''),
            'Address' => $this->csvText($r->address ?? ''),
            'Patient Type' => $this->csvText($r->patient_type ?? ''),
            'Details' => $this->csvText($testsStr),
            'Other Tests' => $this->csvText($r->other_tests ?? ''),
            'Impression' => '',
            'Remarks' => $this->csvText($r->remarks ?? ''),
          ];
        }
      } catch (\Throwable $e2) {
      }
    }

    usort($dataRows, static function (array $a, array $b): int {
      $nameA = strtolower(trim((string) ($a['Patient Name'] ?? '')));
      $nameB = strtolower(trim((string) ($b['Patient Name'] ?? '')));
      $nameCompare = $nameA <=> $nameB;
      if ($nameCompare !== 0) {
        return $nameCompare;
      }

      return ($b['_sort'] ?? 0) <=> ($a['_sort'] ?? 0);
    });

    if ($patientType) {
      $selectedTypeNormalized = $this->normalizePatientType(trim((string) $patientType));
      $dataRows = array_values(array_filter($dataRows, function (array $row) use ($selectedTypeNormalized): bool {
        return $this->normalizePatientType(trim((string) ($row['Patient Type'] ?? ''))) === $selectedTypeNormalized;
      }));
    }

    $filterSlug = $patientType ? strtolower(str_replace(' ', '_', trim((string) $patientType))) : 'all_types';
    $filename = 'analytics_export_' . $range . '_' . $filterSlug . '_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $fp = fopen('php://output', 'w');
    fwrite($fp, "\xEF\xBB\xBF");
    $delimiter = ',';
    $enclosure = '"';
    $escape = '\\';
    $writeCsv = static function ($handle, array $row) use ($delimiter, $enclosure, $escape): void {
      fputcsv($handle, $row, $delimiter, $enclosure, $escape);
    };

    $writeCsv($fp, ['Record Type', 'Date Issued', 'Patient Name', 'Age', 'Gender', 'Address', 'Patient Type', 'Details', 'Other Tests', 'Impression', 'Remarks']);
    foreach ($dataRows as $row) {
      $writeCsv($fp, [
        $row['Record Type'] ?? '',
        $row['Date Issued'] ?? '',
        $row['Patient Name'] ?? '',
        $row['Age'] ?? '',
        $row['Gender'] ?? '',
        $row['Address'] ?? '',
        $row['Patient Type'] ?? '',
        $row['Details'] ?? '',
        $row['Other Tests'] ?? '',
        $row['Impression'] ?? '',
        $row['Remarks'] ?? '',
      ]);
    }
    fclose($fp);
  }

  private function json(int $code, $data): void
  {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
  }

  private function resolveDateRange(string $range): array
  {
    $today = new \DateTimeImmutable('today');

    switch ($range) {
      case 'monthly':
        $start = $today->modify('first day of this month');
        $end = $today->modify('last day of this month');
        break;
      case 'annually':
        $start = $today->setDate((int) $today->format('Y'), 1, 1);
        $end = $today->setDate((int) $today->format('Y'), 12, 31);
        break;
      case 'weekly':
      default:
        $dayOfWeek = (int) $today->format('N');
        $start = $today->modify('-' . ($dayOfWeek - 1) . ' days');
        $end = $start->modify('+6 days');
        break;
    }

    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
  }

  private function fetchAnalyticsRows(string $table, string $alias, int $userId, string $startDate, string $endDate): array
  {
    $queries = [
      "SELECT DISTINCT COALESCE(p.patient_type, '') AS patient_type, $alias.patient_id FROM patients p INNER JOIN $table $alias ON $alias.patient_id = p.id WHERE $alias.user_id = ? AND DATE(COALESCE(NULLIF($alias.date_issued, '0000-00-00'), $alias.created_at)) BETWEEN ? AND ?",
      "SELECT DISTINCT COALESCE(p.patient_type, '') AS patient_type, $alias.patient_id FROM patients p INNER JOIN $table $alias ON $alias.patient_id = p.id WHERE $alias.user_id = ? AND DATE($alias.date_issued) BETWEEN ? AND ?",
      "SELECT DISTINCT '' AS patient_type, $alias.patient_id FROM $table $alias WHERE $alias.user_id = ? AND DATE(COALESCE(NULLIF($alias.date_issued, '0000-00-00'), $alias.created_at)) BETWEEN ? AND ?",
      "SELECT DISTINCT '' AS patient_type, $alias.patient_id FROM $table $alias WHERE $alias.user_id = ? AND DATE($alias.date_issued) BETWEEN ? AND ?",
    ];

    foreach ($queries as $sql) {
      try {
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
      } catch (\Throwable $e) {
      }
    }

    return [];
  }

  private function normalizePatientType(string $patientType): string
  {
    $value = strtolower(trim($patientType));
    if ($value === 'non-teaching' || $value === 'non teaching' || $value === 'nonteaching') {
      return 'Non-Teaching';
    }
    if ($value === 'teaching') {
      return 'Teaching';
    }
    if ($value === 'learner') {
      return 'Learner';
    }
    if ($value === 'school head' || $value === 'school-head' || $value === 'schoolhead') {
      return 'School Head';
    }
    if ($value === 'related-teaching' || $value === 'related teaching' || $value === 'relatedteaching') {
      return 'Related-Teaching';
    }
    return 'Unknown';
  }

  private function getPatientTypeQueryValues(string $patientType): array
  {
    switch ($this->normalizePatientType($patientType)) {
      case 'Non-Teaching':
        return ['Non-Teaching'];
      case 'Teaching':
        return ['Teaching'];
      case 'Learner':
        return ['Learner'];
      case 'School Head':
        return ['School Head'];
      case 'Related-Teaching':
        return ['Related Teaching', 'Related-Teaching'];
      case 'Unknown':
      default:
        return [''];
    }
  }

  private function formatExportDate($dateIssued, $createdAt): string
  {
    $issued = trim((string) ($dateIssued ?? ''));
    if ($issued !== '' && $issued !== '0000-00-00') {
      return $issued;
    }

    $created = trim((string) ($createdAt ?? ''));
    if ($created === '') {
      return '';
    }

    return substr($created, 0, 10);
  }

  private function csvText($value): string
  {
    $text = (string) ($value ?? '');
    $text = str_replace(["\r\n", "\n", "\r", "\t"], ' ', $text);
    return trim(preg_replace('/\s+/', ' ', $text) ?? '');
  }
}
