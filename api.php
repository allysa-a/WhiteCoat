<?php

declare(strict_types=1);

if (!isset($_GET['api']) || trim((string) $_GET['api']) === '') {
    $_GET['api'] = '/api';
}

require __DIR__ . '/index.php';
