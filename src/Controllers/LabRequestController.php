<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use App\Models\User;
use App\Models\Patient;
use App\Models\LabRequest;
use App\Models\LabRequestTemplate;
use App\Helpers\DocxHelper;

class LabRequestController
{
  private PDO $db;
  private string $uploadsDir;

  public function __construct(PDO $db)
  {
    $this->db = $db;
    $this->uploadsDir = dirname(__DIR__, 2) . '/uploads';
  }

  public function generate(): void
  {
    $input = $this->getJson();
    $userId = $input['user_id'] ?? null;
    if (!$userId) {
      $this->json(400, ['message' => 'User ID is required']);
      return;
    }
    $labModel = new LabRequestTemplate($this->db);
    $lab = $labModel->getByUserId((int) $userId);
    if (!$lab || !$lab->file_url) {
      $this->json(400, ['message' => 'No lab request template uploaded. Please upload a Word .docx template in Profile (Tab 5).']);
      return;
    }
    $filename = basename(parse_url($lab->file_url, PHP_URL_PATH));
    $templatePath = $this->uploadsDir . '/' . $filename;
    if (!is_file($templatePath)) {
      $this->json(404, ['message' => 'Lab request template file not found. Please re-upload your .docx template in Profile.']);
      return;
    }
    $empty = function ($v) {
      return $v === null || $v === '' ? '' : (string) $v;
    };
    $toDate = function ($v) {
      if ($v === null || $v === '') return '';
      $t = strtotime((string) $v);
      return $t ? date('Y-m-d', $t) : '';
    };
    $nameVal = $empty($input['name'] ?? null);
    $ageVal = isset($input['age']) && $input['age'] !== '' ? (int) $input['age'] : null;
    $genderVal = $empty($input['gender'] ?? null);
    $addressVal = $empty($input['address'] ?? null);
    $dateVal = $toDate($input['dateIssued'] ?? null) ?: $empty($input['dateIssued'] ?? null);
    $selectedTests = is_array($input['selectedTests'] ?? null) ? $input['selectedTests'] : [];
    $selectedTests = array_values(array_filter(array_map(static function ($test) {
      return trim((string) $test);
    }, $selectedTests), static function ($test) {
      return $test !== '';
    }));
    $othersVal = $empty($input['otherTests'] ?? null);
    $otherTestsList = [];
    if ($othersVal !== '') {
      $otherTestsList = array_values(array_filter(array_map(static function ($test) {
        return trim((string) $test);
      }, preg_split('/[\r\n,;]+/', $othersVal) ?: []), static function ($test) {
        return $test !== '';
      }));
    }
    $allTests = array_merge($selectedTests, $otherTestsList);
    $selectedTestsStr = '';
    if (!empty($allTests)) {
      $selectedTestsStr = '• ' . implode("\n• ", $allTests);
    }
    $impressionVal = $empty($input['impression'] ?? null);
    $remarksVal = $empty($input['remarks'] ?? null);
    $remarksParts = [];
    if ($impressionVal !== '') {
      $remarksParts[] = 'Impression: ' . $impressionVal;
    }
    if ($remarksVal !== '') {
      $remarksParts[] = $remarksVal;
    }
    $remarksForTemplate = implode("\n", $remarksParts);
    $remarksAndOthers = trim(($othersVal ? 'Others: ' . $othersVal : '') . ($remarksForTemplate ? "\nRemarks: " . $remarksForTemplate : ''));
    $data = [
      'name' => $nameVal,
      'age' => $ageVal !== null ? (string) $ageVal : '',
      'gender' => $genderVal,
      'address' => $addressVal,
      'date' => $dateVal,
      'dateIssued' => $dateVal,
      'selectedTests' => $selectedTestsStr,
      'other' => $othersVal,
      'others' => $othersVal,
      'impression' => $impressionVal,
      'Impression' => $impressionVal,
      'remark' => $remarksForTemplate,
      'remarks' => $remarksForTemplate,
      'remarksAndOthers' => $remarksAndOthers,
    ];
    try {
      $docxPath = DocxHelper::fill($templatePath, $data);
    } catch (\Throwable $e) {
      $msg = $e->getMessage();
      if (stripos($msg, 'zip extension') !== false || stripos($msg, 'ZipArchive') !== false) {
        $this->json(500, ['message' => 'DOCX generation is unavailable: enable PHP zip extension (extension=zip) and restart PHP.']);
        return;
      }
      $this->json(400, ['message' => 'Template error. Use placeholders: {name}, {age}, {gender}, {address}, {date}, {selectedTests}, {other}, {impression}, {remarks}']);
      return;
    }
    $docxFilename = $this->buildDownloadFilename('lab', $nameVal, 'docx');
    $pdfFilename = $this->buildDownloadFilename('lab', $nameVal, 'pdf');
    $skipSave = !empty($input['skipSave']);
    $patientId = $input['patient_id'] ?? null;
    if ($patientId !== null && (!is_numeric((string) $patientId) || (int) $patientId <= 0)) {
      $patientId = null;
    }
    $patientModel = new Patient($this->db);
    if ($patientId === null && ($nameVal !== '' || $ageVal !== null || $genderVal !== '' || $addressVal !== '')) {
      try {
        $patientId = $patientModel->findOrCreate([
          'full_name' => $nameVal,
          'age' => $ageVal,
          'gender' => $genderVal,
          'address' => $addressVal,
          'patient_type' => $input['patient_type'] ?? null,
        ]);
      } catch (\Throwable $e) {
      }
    }

    if ($patientId !== null) {
      $patientModel->updateDemographicsById((string) $patientId, [
        'age' => $ageVal,
        'gender' => $genderVal,
        'address' => $addressVal,
        'patient_type' => $input['patient_type'] ?? null,
      ]);
    }
    if (!$skipSave && $patientId !== null) {
      try {
        (new LabRequest($this->db))->create([
          'user_id' => (int) $userId,
          'patient_id' => (int) $patientId,
          'date_issued' => $dateVal ?: null,
          'selected_tests' => $selectedTests,
          'other_tests' => $othersVal ?: null,
          'remarks' => $remarksForTemplate ?: null,
        ]);
      } catch (\Throwable $e) {
      }
    }
    $wantPdf = ($_GET['format'] ?? '') === 'pdf' || ($input['format'] ?? '') === 'pdf';
    if ($wantPdf) {
      $pdfPath = DocxHelper::convertToPdf($docxPath);
      if ($pdfPath) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $pdfFilename . '"');
        readfile($pdfPath);
        @unlink($pdfPath);
        @unlink($docxPath);
        return;
      }
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $docxFilename . '"');
    readfile($docxPath);
    @unlink($docxPath);
  }

  public function history(): void
  {
    $userId = $_GET['user_id'] ?? null;
    if (!$userId) {
      $this->json(400, ['message' => 'user_id is required']);
      return;
    }
    $limit = min((int) ($_GET['limit'] ?? 200), 200);
    $rows = (new LabRequest($this->db))->findByUserId((int) $userId, $limit);
    $this->json(200, $rows);
  }

  public function deleteHistory(): void
  {
    $userId = $_GET['user_id'] ?? null;
    $id = $_GET['id'] ?? null;

    if (!$userId || !$id || !is_numeric((string) $id)) {
      $this->json(400, ['message' => 'user_id and numeric id are required']);
      return;
    }

    $deleted = (new LabRequest($this->db))->deleteById((int) $userId, (int) $id);
    if (!$deleted) {
      $this->json(404, ['message' => 'History record not found']);
      return;
    }

    $this->json(200, ['message' => 'History record deleted']);
  }

  private function getJson(): array
  {
    $raw = file_get_contents('php://input');
    $dec = json_decode($raw, true);
    return is_array($dec) ? $dec : [];
  }

  private function json(int $code, $data): void
  {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
  }

  private function buildDownloadFilename(string $documentType, string $patientName, string $extension): string
  {
    $safeType = $this->sanitizeFilenamePart($documentType);
    $safeName = $this->sanitizeFilenamePart($patientName);
    $baseName = trim($safeType . ' ' . ($safeName !== '' ? $safeName : 'patient'));

    return $baseName . '.' . ltrim($extension, '.');
  }

  private function sanitizeFilenamePart(string $value): string
  {
    $sanitized = preg_replace('/[<>:"\/\\|?*\x00-\x1F]+/u', ' ', $value) ?? '';
    $sanitized = preg_replace('/\s+/u', ' ', trim($sanitized)) ?? '';

    return trim($sanitized, '. ');
  }
}
