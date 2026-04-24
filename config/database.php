<?php

$host = 'localhost';
$user = 'whitecoat';
$pass = 'whitecoat123';
$name = 'depedlapulapu_whitecoat';

try {
  $pdo = new PDO(
    "mysql:host=$host;dbname=$name;charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]
  );
} catch (PDOException $e) {
  http_response_code(500);
  header('Content-Type: application/json');
  echo json_encode([
    'message' => 'Database connection failed',
    'error' => $e->getMessage(),
    'hint' => 'Check config/database.php credentials and ensure MySQL is running with the selected database.',
  ]);
  exit;
}

return $pdo;
