<?php

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'whitecoat');

try {
  $pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
  ];
  if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
    $pdoOptions[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci';
  }

  $pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASSWORD,
    $pdoOptions
  );

  try {
    $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
  } catch (Throwable $e) {
  }
} catch (PDOException $e) {
  $appEnv = strtolower((string) (getenv('APP_ENV') ?: 'production'));
  $appDebugRaw = getenv('APP_DEBUG');
  $isDebug = $appDebugRaw !== false
    ? filter_var($appDebugRaw, FILTER_VALIDATE_BOOL)
    : ($appEnv === 'local');

  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  $response = [
    'message' => 'Database connection failed',
  ];

  if ($isDebug) {
    $response['error'] = $e->getMessage();
    $response['hint'] = 'Check deployment/database.php DB_HOST, DB_USER, DB_PASSWORD, and DB_NAME values.';
  }

  echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
  exit;
}

return $pdo;
