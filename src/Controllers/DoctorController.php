<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use App\Models\User;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\MedicalCertificate;
use App\Models\LabRequest;
use App\Models\LabRequestTemplate;

class DoctorController
{
  private PDO $db;
  private string $uploadsDir;

  public function __construct(PDO $db)
  {
    $this->db = $db;
    $this->uploadsDir = dirname(__DIR__, 2) . '/uploads';
    if (!is_dir($this->uploadsDir)) {
      mkdir($this->uploadsDir, 0755, true);
    }
  }

  public function getProfile(): void
  {
    $userId = $_GET['user_id'] ?? null;
    if (!$userId) {
      $this->json(400, ['message' => 'User ID is required']);
      return;
    }
    $userModel = new User($this->db);
    $profile = $userModel->getProfileWithFiles((int) $userId);
    if (!$profile) {
      $this->json(404, ['message' => 'User not found']);
      return;
    }
    $labModel = new LabRequestTemplate($this->db);
    $lab = $labModel->getByUserId((int) $userId);
    $profile->profile_photo = $this->normalizeStoredUploadUrl($profile->profile_photo ?? null);
    $profile->prescription = $this->normalizeStoredUploadUrl($profile->prescription ?? null);
    $profile->medical_certificate = $this->normalizeStoredUploadUrl($profile->medical_certificate ?? null);
    $profile->lab_request = $this->normalizeStoredUploadUrl($lab->file_url ?? null);
    $profile->lab_request_file_name = $lab->file_name ?? null;
    $this->json(200, $profile);
  }

  private function normalizeStoredUploadUrl($value): ?string
  {
    if (!is_string($value)) return $value;
    $trimmed = trim($value);
    if ($trimmed === '') return '';

    $path = parse_url($trimmed, PHP_URL_PATH);
    if (is_string($path) && stripos($path, '/uploads/') !== false) {
      $filename = basename($path);
      if ($filename === '' || !is_file($this->uploadsDir . '/' . $filename)) {
        return '';
      }
      return $this->baseUrl() . '/uploads/' . $filename;
    }

    if (stripos($trimmed, 'uploads/') === 0) {
      $filename = basename($trimmed);
      if ($filename === '' || !is_file($this->uploadsDir . '/' . $filename)) {
        return '';
      }
      return $this->baseUrl() . '/' . ltrim($trimmed, '/');
    }

    return $trimmed;
  }

  public function updateProfile(): void
  {
    $input = $this->getJson();
    $userId = $input['user_id'] ?? null;
    if (!$userId) {
      $this->json(400, ['message' => 'User ID is required']);
      return;
    }
    $username = trim((string) ($input['username'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    if (!$username || !$email) {
      $this->json(400, ['message' => 'Username and email are required']);
      return;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $this->json(400, ['message' => 'Please provide a valid email address']);
      return;
    }
    $userModel = new User($this->db);
    $existing = $userModel->findById((int) $userId);
    if (!$existing) {
      $this->json(404, ['message' => 'User not found']);
      return;
    }
    $hash = $existing->password;
    if ($password !== '' && trim($password) !== '') {
      if (!$this->isStrongPassword($password)) {
        $this->json(400, ['message' => 'Password must be at least 16 characters and include at least one uppercase letter, one lowercase letter, one number, and one special character.']);
        return;
      }
      $hash = password_hash($password, PASSWORD_BCRYPT);
    }
    try {
      $userModel->updateProfile((int) $userId, $username, $email, $hash);
    } catch (\Throwable $e) {
      $message = (string) $e->getMessage();
      if (
        stripos($message, 'Duplicate entry') !== false ||
        stripos($message, 'UNIQUE constraint failed') !== false ||
        stripos($message, 'SQLSTATE[23000]') !== false
      ) {
        $this->json(409, ['message' => 'Username or email is already in use. Please choose another.']);
        return;
      }
      throw $e;
    }
    $this->json(200, [
      'message' => 'Profile updated successfully',
      'user' => ['user_id' => (int) $userId, 'username' => $username, 'email' => $email],
    ]);
  }

  private function handleUpload(string $field, string $userIdKey, callable $onSuccess): void
  {
    $userId = $_POST[$userIdKey] ?? null;
    if (!$userId) {
      $this->json(400, ['message' => 'User ID is required']);
      return;
    }
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
      $this->json(400, ['message' => 'No file uploaded']);
      return;
    }
    $file = $_FILES[$field];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safe = preg_replace('/\s+/', '_', $file['name']);
    $filename = $field . '_' . $userId . '_' . time() . '_' . $safe;
    $path = $this->uploadsDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $path)) {
      $this->json(500, ['message' => 'Failed to save file']);
      return;
    }
    $baseUrl = $this->baseUrl();
    $fileUrl = $baseUrl . '/uploads/' . $filename;
    $onSuccess((int) $userId, $fileUrl, $file['name'], $filename);
  }

  public function uploadPrescription(): void
  {
    $this->handleUpload('file', 'user_id', function ($userId, $fileUrl, $originalName) {
      (new User($this->db))->updatePrescription($userId, $fileUrl);
      $this->json(200, ['message' => 'Prescription uploaded successfully', 'fileName' => $originalName, 'fileUrl' => $fileUrl]);
    });
  }

  public function uploadMedicalCertificate(): void
  {
    $this->handleUpload('file', 'user_id', function ($userId, $fileUrl, $originalName) {
      (new User($this->db))->updateMedicalCertificate($userId, $fileUrl, $originalName);
      $this->json(200, ['message' => 'Medical certificate uploaded successfully', 'fileName' => $originalName, 'fileUrl' => $fileUrl]);
    });
  }

  public function uploadProfilePhoto(): void
  {
    if (empty($_FILES['file']) || !isset($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
      $this->json(400, ['message' => 'No image file uploaded']);
      return;
    }

    $file = $_FILES['file'];
    $ext = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $allowedMime = ['image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png', 'image/gif', 'image/webp'];

    $detectedMime = '';
    try {
      $finfo = new \finfo(FILEINFO_MIME_TYPE);
      $detectedMime = (string) $finfo->file($file['tmp_name']);
    } catch (\Throwable $e) {
      $detectedMime = (string) ($file['type'] ?? '');
    }

    $providedMime = strtolower((string) ($file['type'] ?? ''));
    $isAllowedByExt = in_array($ext, $allowedExt, true);
    $isAllowedByMime = in_array(strtolower($detectedMime), $allowedMime, true) || in_array($providedMime, $allowedMime, true);

    if (!$isAllowedByExt && !$isAllowedByMime) {
      $this->json(400, ['message' => 'Only image files (.jpg, .jpeg, .png, .gif, .webp) are allowed']);
      return;
    }

    $this->handleUpload('file', 'user_id', function ($userId, $fileUrl) {
      (new User($this->db))->updateProfilePhoto($userId, $fileUrl);
      $this->json(200, ['message' => 'Profile photo uploaded successfully', 'fileUrl' => $fileUrl]);
    });
  }

  public function uploadLabRequest(): void
  {
    $this->handleUpload('file', 'user_id', function ($userId, $fileUrl, $originalName) {
      (new LabRequestTemplate($this->db))->upsert($userId, $fileUrl, $originalName);
      $this->json(200, ['message' => 'Lab request template uploaded successfully', 'fileName' => $originalName, 'fileUrl' => $fileUrl]);
    });
  }

  public function uploadSignature(): void
  {
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (empty($_FILES['file']) || !in_array($_FILES['file']['type'], $allowed)) {
      $this->json(400, ['message' => 'Only image files are allowed']);
      return;
    }
    $this->handleUpload('file', 'user_id', function ($userId, $fileUrl) {
      (new User($this->db))->updateSignature($userId, $fileUrl);
      $this->json(200, ['message' => 'E-signature uploaded successfully', 'fileUrl' => $fileUrl]);
    });
  }

  public function getProfilePhoto(): void
  {
    $userId = $_GET['user_id'] ?? null;
    if (!$userId) {
      $this->json(400, ['message' => 'user_id is required']);
      return;
    }

    $profile = (new User($this->db))->getProfileWithFiles((int) $userId);
    if (!$profile || empty($profile->profile_photo)) {
      $this->json(404, ['message' => 'Profile photo not found']);
      return;
    }

    $path = parse_url((string) $profile->profile_photo, PHP_URL_PATH);
    $filename = $path ? basename($path) : '';
    if ($filename === '') {
      $this->json(404, ['message' => 'Profile photo not found']);
      return;
    }

    $filePath = $this->uploadsDir . '/' . $filename;
    if (!is_file($filePath)) {
      $this->json(404, ['message' => 'Profile photo file missing']);
      return;
    }

    $mime = 'application/octet-stream';
    if (class_exists('finfo')) {
      try {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($filePath);
        if (is_string($detected) && $detected !== '') {
          $mime = $detected;
        }
      } catch (\Throwable $e) {
        $mime = 'application/octet-stream';
      }
    }
    if ($mime === 'application/octet-stream') {
      $ext = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));
      $mimeMap = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
      ];
      if (isset($mimeMap[$ext])) {
        $mime = $mimeMap[$ext];
      }
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($filePath));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    readfile($filePath);
  }

  public function getPatientHistory(): void
  {
    $userId = $_GET['user_id'] ?? null;
    $patientId = $_GET['patient_id'] ?? null;
    if (!$userId || !$patientId) {
      $this->json(400, ['message' => 'user_id and patient_id are required']);
      return;
    }
    $limit = min((int) ($_GET['limit'] ?? 200), 500);
    $patientModel = new Patient($this->db);
    $presModel = new Prescription($this->db);
    $certModel = new MedicalCertificate($this->db);
    $labModel = new LabRequest($this->db);
    $patient = $patientModel->findById((int) $patientId);
    $prescriptions = $presModel->findByPatientId((int) $userId, (int) $patientId, $limit);
    $certificates = $certModel->findByPatientId((int) $userId, (int) $patientId, $limit);
    $labRequests = $labModel->findByPatientId((int) $userId, (int) $patientId, $limit);
    $name = $patient ? trim($patient->full_name ?? $patient->name ?? '') ?: 'Unknown' : 'Unknown';
    $this->json(200, [
      'patient' => $patient ? (object) ['id' => $patient->id, 'name' => $name, 'age' => $patient->age ?? null, 'gender' => $patient->gender ?? null, 'address' => $patient->address ?? null] : null,
      'prescriptions' => $prescriptions,
      'medicalCertificates' => $certificates,
      'labRequests' => $labRequests,
    ]);
  }

  private function baseUrl(): string
  {
    $url = getenv('BASE_URL');
    if ($url) return rtrim($url, '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host !== '') {
      return $scheme . '://' . $host;
    }
    return '';
  }

  private function isStrongPassword(string $password): bool
  {
    if (strlen($password) < 16) {
      return false;
    }

    $hasUppercase = preg_match('/[A-Z]/', $password) === 1;
    $hasLowercase = preg_match('/[a-z]/', $password) === 1;
    $hasNumber = preg_match('/[0-9]/', $password) === 1;
    $hasSpecial = preg_match('/[^A-Za-z0-9]/', $password) === 1;

    return $hasUppercase && $hasLowercase && $hasNumber && $hasSpecial;
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
}
