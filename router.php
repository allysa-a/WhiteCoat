<?php

/**
 * Router for PHP built-in server: serve uploads from ../uploads, else index.php
 * Run: php -S localhost:8000 -t public public/router.php
 */
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/uploads/(.+)$#', $uri, $m)) {
  $file = dirname(__DIR__) . '/uploads/' . $m[1];
  if (is_file($file) && strpos(realpath($file), realpath(dirname(__DIR__) . '/uploads')) === 0) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    header('Content-Type: ' . finfo_file($finfo, $file));
    finfo_close($finfo);
    readfile($file);
    return true;
  }
}
require __DIR__ . '/index.php';
return true;
