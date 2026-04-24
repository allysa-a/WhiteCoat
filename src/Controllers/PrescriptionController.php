<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use App\Models\User;
use App\Models\Patient;
use App\Models\Prescription;
use App\Helpers\DocxHelper;

class PrescriptionController
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
    $reasonForReferral = trim((string) ($input['reason_for_referral'] ?? ''));
    if (!$userId) {
      $this->json(400, ['message' => 'User ID is required']);
      return;
    }
    if ($reasonForReferral === '') {
      $this->json(400, ['message' => 'Reason for referral is required']);
      return;
    }
    $userModel = new User($this->db);
    $templateUrl = $userModel->getPrescriptionUrl((int) $userId);
    if (!$templateUrl) {
      $this->json(400, ['message' => 'No prescription template uploaded. Please upload a Word .docx template in Profile (Tab 4).']);
      return;
    }
    $filename = basename(parse_url($templateUrl, PHP_URL_PATH));
    $templatePath = $this->uploadsDir . '/' . $filename;
    if (!is_file($templatePath)) {
      $this->json(404, ['message' => 'Prescription template file not found. Please re-upload your .docx template in Profile.']);
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
    $ageVal = $empty($input['age'] ?? null);
    $genderVal = $empty($input['gender'] ?? null);
    $addressVal = $empty($input['address'] ?? null);
    $dateVal = $toDate($input['dateIssued'] ?? null) ?: $empty($input['dateIssued'] ?? null);
    $medications = is_array($input['medications'] ?? null) ? $input['medications'] : [];
    $medsData = [];
    $runningNumber = 1;
    foreach ($medications as $m) {
      $med = is_array($m) ? $m : [];
      $medName = $empty($med['name'] ?? null);
      $providedNumber = $med['number'] ?? $med['index'] ?? $med['no'] ?? $med['item_no'] ?? $med['itemNo'] ?? null;
      $displayNumber = (is_numeric((string) $providedNumber) && (int) $providedNumber > 0)
        ? (int) $providedNumber
        : $runningNumber;

      $medsData[] = [
        'number' => $displayNumber,
        'index' => $displayNumber,
        'no' => $displayNumber,
        'item_no' => $displayNumber,
        'itemNo' => $displayNumber,
        'name' => $medName,
        'medication_name' => $medName,
        'medicationName' => $medName,
        'MedicationName' => $medName,
        'dosage' => $empty($med['dosage'] ?? null),
        'frequency' => $empty($med['frequency'] ?? null),
        'duration' => $empty($med['duration'] ?? null),
        'instructions' => $empty($med['instructions'] ?? null),
      ];

      $runningNumber++;
    }
    $data = [
      'name' => $nameVal,
      'patient_name' => $nameVal,
      'patientName' => $nameVal,
      'age' => $ageVal,
      'gender' => $genderVal,
      'address' => $addressVal,
      'dateIssued' => $dateVal,
      'date' => $dateVal,
      'Name' => $nameVal,
      'Age' => $ageVal,
      'Sex' => $genderVal,
      'Address' => $addressVal,
      'Date' => $dateVal,
      'medications' => $medsData,
    ];
    try {
      $docxPath = DocxHelper::fill($templatePath, $data);
    } catch (\Throwable $e) {
      $msg = $e->getMessage();
      if (stripos($msg, 'zip extension is not enabled') !== false || stripos($msg, 'ZipArchive missing') !== false || stripos($msg, 'Class "ZipArchive" not found') !== false) {
        $payload = ['message' => 'DOCX generation is unavailable: enable PHP zip extension (extension=zip) and restart PHP.'];
        if (strtolower((string) (getenv('APP_ENV') ?: 'local')) !== 'production') {
          $payload['error'] = $msg;
        }
        $this->json(500, $payload);
        return;
      }
      $payload = ['message' => 'Template error. Use patient placeholders: {name}, {age}, {gender}, {dateIssued}, {address}; and medication placeholders inside loop: {#medications} {number}. {medication_name} {dosage} {frequency} {duration} {instructions} {/medications}.'];
      if (strtolower((string) (getenv('APP_ENV') ?: 'local')) !== 'production') {
        $payload['error'] = $msg;
      }
      $this->json(400, $payload);
      return;
    }
    $docxFilename = $this->buildDownloadFilename('prescription', $nameVal, 'docx');
    $pdfFilename = $this->buildDownloadFilename('prescription', $nameVal, 'pdf');
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
    try {
      (new Prescription($this->db))->create([
        'user_id' => (int) $userId,
        'patient_name' => $nameVal,
        'age' => $ageVal,
        'gender' => $genderVal,
        'date_issued' => $dateVal ?: null,
        'address' => $addressVal,
        'patient_type' => $input['patient_type'] ?? null,
        'maintenance' => $input['maintenance'] ?? null,
        'reason_for_referral' => $input['reason_for_referral'] ?? null,
        'medications' => $medications,
      ]);
    } catch (\Throwable $e) {
      @unlink($docxPath);
      $payload = ['message' => 'Failed to save prescription history. Please try again.'];
      if (strtolower((string) (getenv('APP_ENV') ?: 'local')) !== 'production') {
        $payload['error'] = $e->getMessage();
      }
      $this->json(500, $payload);
      return;
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
    $limit = min((int) ($_GET['limit'] ?? 10000), 10000);
    $rows = (new Prescription($this->db))->findByUserId((int) $userId, $limit);
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

    $deleted = (new Prescription($this->db))->deleteById((int) $userId, (int) $id);
    if (!$deleted) {
      $this->json(404, ['message' => 'History record not found']);
      return;
    }

    $this->json(200, ['message' => 'History record deleted']);
  }

  public function patients(): void
  {
    $userId = $_GET['user_id'] ?? null;
    if (!$userId) {
      $this->json(400, ['message' => 'user_id is required']);
      return;
    }
    $limit = min((int) ($_GET['limit'] ?? 10000), 10000);
    $query = trim((string) ($_GET['q'] ?? ''));
    $rows = (new Patient($this->db))->findDistinctByUserHistory((int) $userId, $limit, $query);
    $this->json(200, $rows);
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
