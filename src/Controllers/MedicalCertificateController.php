<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use App\Models\User;
use App\Models\MedicalCertificate;
use App\Helpers\DocxHelper;

class MedicalCertificateController
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
    $userModel = new User($this->db);
    $templateUrl = $userModel->getMedicalCertificateUrl((int) $userId);
    if (!$templateUrl) {
      $this->json(400, ['message' => 'No medical certificate template uploaded. Please upload a Word .docx template in Profile (Tab 4).']);
      return;
    }
    $filename = basename(parse_url($templateUrl, PHP_URL_PATH));
    $templatePath = $this->uploadsDir . '/' . $filename;
    if (!is_file($templatePath)) {
      $this->json(404, ['message' => 'Medical certificate template file not found. Please re-upload your .docx template in Profile.']);
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
    $impressionVal = $empty($input['impression'] ?? null);
    $remarksVal = $empty($input['remarks'] ?? null);
    $data = [
      'name' => $nameVal,
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
      'impression' => $impressionVal,
      'remarks' => $remarksVal,
      'Impression' => $impressionVal,
      'Remarks' => $remarksVal,
    ];
    try {
      $docxPath = DocxHelper::fill($templatePath, $data);
    } catch (\Throwable $e) {
      $msg = $e->getMessage();
      if (stripos($msg, 'zip extension') !== false || stripos($msg, 'ZipArchive') !== false) {
        $this->json(500, ['message' => 'DOCX generation is unavailable: enable PHP zip extension (extension=zip) and restart PHP.']);
        return;
      }
      $this->json(400, ['message' => 'Template error. Use placeholders: {name}, {age}, {gender}, {dateIssued}, {address}, {impression}, {remarks}']);
      return;
    }
    $docxFilename = $this->buildDownloadFilename('medical certificate', $nameVal, 'docx');
    $pdfFilename = $this->buildDownloadFilename('medical certificate', $nameVal, 'pdf');
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
      (new MedicalCertificate($this->db))->create([
        'user_id' => (int) $userId,
        'patient_id' => $input['patient_id'] ?? null,
        'patient_name' => $nameVal,
        'age' => $ageVal,
        'gender' => $genderVal,
        'date_issued' => $dateVal ?: null,
        'address' => $addressVal,
        'impression' => $impressionVal,
        'remarks' => $remarksVal,
        'patient_type' => $input['patient_type'] ?? null,
      ]);
    } catch (\Throwable $e) {
      @unlink($docxPath);
      $this->json(500, ['message' => 'Failed to save medical certificate history. Please try again.']);
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
    $limit = min((int) ($_GET['limit'] ?? 100), 100);
    $rows = (new MedicalCertificate($this->db))->findByUserId((int) $userId, $limit);
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

    $deleted = (new MedicalCertificate($this->db))->deleteById((int) $userId, (int) $id);
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
