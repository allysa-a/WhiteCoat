<?php

declare(strict_types=1);

$autoloadPath = is_file(__DIR__ . '/vendor/autoload.php')
  ? __DIR__ . '/vendor/autoload.php'
  : __DIR__ . '/../vendor/autoload.php';
require $autoloadPath;

$bootstrapPath = is_file(__DIR__ . '/bootstrap.php')
  ? __DIR__ . '/bootstrap.php'
  : (is_file(__DIR__ . '/config/bootstrap.php') ? __DIR__ . '/config/bootstrap.php' : __DIR__ . '/../config/bootstrap.php');
require $bootstrapPath;

$appEnv = strtolower((string) (getenv('APP_ENV') ?: 'production'));
$appDebugRaw = getenv('APP_DEBUG');
$isDebug = $appDebugRaw !== false
  ? filter_var($appDebugRaw, FILTER_VALIDATE_BOOL)
  : ($appEnv === 'local');

if (!function_exists('detectMimeType')) {
  function detectMimeType(string $filePath): string
  {
    $mime = 'application/octet-stream';
    if (class_exists('finfo')) {
      try {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($filePath);
        if (is_string($detected) && $detected !== '') {
          $mime = $detected;
        }
      } catch (Throwable $e) {
        $mime = 'application/octet-stream';
      }
    }

    if ($mime === 'application/octet-stream') {
      $ext = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));
      $mimeMap = [
        'html' => 'text/html; charset=utf-8',
        'htm' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'mjs' => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'pdf' => 'application/pdf',
      ];
      if (isset($mimeMap[$ext])) {
        $mime = $mimeMap[$ext];
      }
    }

    return $mime;
  }
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($scriptBase !== '' && $scriptBase !== '/' && strpos($requestPath, $scriptBase) === 0) {
  $requestPath = substr($requestPath, strlen($scriptBase)) ?: '/';
}

$queryApiPath = isset($_GET['api']) ? trim((string) $_GET['api']) : '';
if ($queryApiPath !== '') {
  $apiPath = $queryApiPath;
  $apiQuery = '';
  $qPos = strpos($queryApiPath, '?');
  if ($qPos !== false) {
    $apiPath = substr($queryApiPath, 0, $qPos);
    $apiQuery = substr($queryApiPath, $qPos + 1);
  }

  if ($apiQuery !== '') {
    $parsedApiQuery = [];
    parse_str($apiQuery, $parsedApiQuery);
    foreach ($parsedApiQuery as $key => $val) {
      if (!array_key_exists((string) $key, $_GET)) {
        $_GET[(string) $key] = $val;
      }
    }
  }

  $requestPath = '/' . ltrim((string) $apiPath, '/');
}

$isApiRequest = (bool) preg_match('#^/(?:api|index\.php/api)(?:/|$)#', $requestPath);

if (!$isApiRequest) {
  $relativePath = ltrim($requestPath, '/');
  if ($relativePath === '') {
    $relativePath = 'index.html';
  }

  $candidateFile = realpath(__DIR__ . '/' . $relativePath);
  $publicBase = realpath(__DIR__);

  if ($candidateFile !== false && $publicBase !== false && strpos($candidateFile, $publicBase) === 0 && is_file($candidateFile)) {
    $ext = strtolower((string) pathinfo($candidateFile, PATHINFO_EXTENSION));
    if ($ext !== 'php') {
      $mime = detectMimeType($candidateFile);
      header('Content-Type: ' . $mime);
      header('Content-Length: ' . (string) filesize($candidateFile));
      readfile($candidateFile);
      exit;
    }
  }

  $spaIndex = __DIR__ . '/index.html';
  if (is_file($spaIndex)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($spaIndex);
    exit;
  }
}

$corsAllowOrigin = trim((string) (getenv('CORS_ALLOW_ORIGIN') ?: '*'));
$requestOrigin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));

if ($corsAllowOrigin === '*') {
  header('Access-Control-Allow-Origin: *');
} else {
  $allowedOrigins = array_values(array_filter(array_map('trim', explode(',', $corsAllowOrigin))));
  if ($requestOrigin !== '' && in_array($requestOrigin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Vary: Origin');
  } elseif (count($allowedOrigins) > 0) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigins[0]);
    header('Vary: Origin');
  }
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($requestMethod === 'OPTIONS') {
  http_response_code(204);
  exit;
}

$databasePath = is_file(__DIR__ . '/database.php')
  ? __DIR__ . '/database.php'
  : (is_file(__DIR__ . '/config/database.php') ? __DIR__ . '/config/database.php' : __DIR__ . '/../config/database.php');
$pdo = require $databasePath;

$method = $requestMethod;
$path = $requestPath;

if ($method === 'GET' && preg_match('#^/uploads/(.+)$#', $path, $m)) {
  $uploadsBase = realpath(__DIR__ . '/uploads');
  if ($uploadsBase === false) {
    $uploadsBase = realpath(dirname(__DIR__) . '/uploads');
  }
  if ($uploadsBase !== false) {
    $candidate = realpath($uploadsBase . DIRECTORY_SEPARATOR . ltrim((string) $m[1], '/'));
    if ($candidate !== false && strpos($candidate, $uploadsBase) === 0 && is_file($candidate)) {
      $mime = detectMimeType($candidate);
      header('Content-Type: ' . $mime);
      header('Content-Length: ' . (string) filesize($candidate));
      readfile($candidate);
      exit;
    }
  }
}

// Frontend calls e.g. /api/auth/login, /api/doctor/profile — strip /api so we match /auth/login, /doctor/profile
$path = preg_replace('#^/(?:index\.php/)?api#', '', $path) ?: '/';
$path = rtrim($path, '/') ?: '/';
if (preg_match('#^/doctor/patient/(\d+)/history$#', $path, $m)) {
  $_GET['patient_id'] = $m[1];
  $path = '/doctor/patient/history';
}

$router = [
  'GET /' => function () {
    header('Content-Type: text/plain');
    echo 'WhiteCoat API Running...';
  },
  'POST /auth/login' => [\App\Controllers\AuthController::class, 'login'],
  'POST /auth/register' => [\App\Controllers\AuthController::class, 'register'],
  'GET /auth/verify-email' => [\App\Controllers\AuthController::class, 'verifyEmail'],
  'POST /auth/verify-email-otp' => [\App\Controllers\AuthController::class, 'verifyEmailOtp'],
  'POST /auth/resend-verification' => [\App\Controllers\AuthController::class, 'resendVerification'],
  'POST /auth/forgot-password' => [\App\Controllers\AuthController::class, 'forgotPassword'],
  'GET /auth/reset-password/validate' => [\App\Controllers\AuthController::class, 'validateResetPasswordToken'],
  'POST /auth/reset-password' => [\App\Controllers\AuthController::class, 'resetPassword'],
  'GET /doctor/profile' => [\App\Controllers\DoctorController::class, 'getProfile'],
  'GET /doctor/profile-photo' => [\App\Controllers\DoctorController::class, 'getProfilePhoto'],
  'PUT /doctor/profile' => [\App\Controllers\DoctorController::class, 'updateProfile'],
  'POST /doctor/upload/prescription' => [\App\Controllers\DoctorController::class, 'uploadPrescription'],
  'POST /doctor/upload/medical-certificate' => [\App\Controllers\DoctorController::class, 'uploadMedicalCertificate'],
  'POST /doctor/upload/profile-photo' => [\App\Controllers\DoctorController::class, 'uploadProfilePhoto'],
  'POST /doctor/upload/lab-request' => [\App\Controllers\DoctorController::class, 'uploadLabRequest'],
  'POST /doctor/upload/signature' => [\App\Controllers\DoctorController::class, 'uploadSignature'],
  'POST /doctor/prescription/generate' => [\App\Controllers\PrescriptionController::class, 'generate'],
  'GET /doctor/prescription/history' => [\App\Controllers\PrescriptionController::class, 'history'],
  'DELETE /doctor/prescription/history' => [\App\Controllers\PrescriptionController::class, 'deleteHistory'],
  'GET /doctor/patients' => [\App\Controllers\PrescriptionController::class, 'patients'],
  'POST /doctor/medical-certificate/generate' => [\App\Controllers\MedicalCertificateController::class, 'generate'],
  'POST /doctor/issuance/generate' => [\App\Controllers\MedicalCertificateController::class, 'generate'],
  'GET /doctor/medical-certificate/history' => [\App\Controllers\MedicalCertificateController::class, 'history'],
  'DELETE /doctor/medical-certificate/history' => [\App\Controllers\MedicalCertificateController::class, 'deleteHistory'],
  'POST /doctor/lab-request/generate' => [\App\Controllers\LabRequestController::class, 'generate'],
  'GET /doctor/lab-request/history' => [\App\Controllers\LabRequestController::class, 'history'],
  'DELETE /doctor/lab-request/history' => [\App\Controllers\LabRequestController::class, 'deleteHistory'],
  'GET /doctor/patient/history' => [\App\Controllers\DoctorController::class, 'getPatientHistory'],
  'GET /doctor/analytics' => [\App\Controllers\AnalyticsController::class, 'getAnalytics'],
  'GET /doctor/analytics/export' => [\App\Controllers\AnalyticsController::class, 'exportAnalytics'],
  'GET /doctor/notifications/google-form' => [\App\Controllers\NotificationController::class, 'googleFormResponses'],
];

$key = $method . ' ' . $path;
if (!isset($router[$key])) {
  http_response_code(404);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['message' => 'Not found'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
  exit;
}

$handler = $router[$key];
try {
  if (is_callable($handler)) {
    $handler();
  } else {
    $obj = new $handler[0]($pdo);
    $obj->{$handler[1]}();
  }
} catch (Throwable $e) {
  error_log($e->getMessage() . "\n" . $e->getTraceAsString());
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  $response = ['message' => 'Server error'];
  if ($isDebug) {
    $response['error'] = $e->getMessage();
    $response['file'] = $e->getFile() . ':' . $e->getLine();
  }
  echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
