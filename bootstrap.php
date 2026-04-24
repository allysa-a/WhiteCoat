<?php

$envCandidates = [
  __DIR__ . '/.env',
  __DIR__ . '/../.env',
];

foreach ($envCandidates as $envPath) {
  if (!is_file($envPath)) {
    continue;
  }

  $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    if (preg_match('/^([^=]+)=(.*)$/', $line, $m)) {
      putenv(trim($m[1]) . '=' . trim($m[2], " \t\"'"));
    }
  }

  break;
}

if (!getenv('BASE_URL')) {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/deployment/index.php')), '/');
  $basePath = ($scriptDir === '' || $scriptDir === '/') ? '' : $scriptDir;
  putenv('BASE_URL=' . $scheme . '://' . $host . $basePath);
}

if (!defined('DOCX_TO_PDF_URL')) {
  define('DOCX_TO_PDF_URL', getenv('DOCX_TO_PDF_URL') ?: '');
}

if (!defined('DOCX_TO_PDF_TOKEN')) {
  define('DOCX_TO_PDF_TOKEN', getenv('DOCX_TO_PDF_TOKEN') ?: '');
}
