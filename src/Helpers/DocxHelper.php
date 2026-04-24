<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Fill .docx template with placeholders (same style as Node docxtemplater).
 * {name}, {age}, {gender}, {dateIssued}, {address}, etc.
 * Medications: {#medications}...{name} {dosage}...{/medications}
 */
class DocxHelper
{
  public static function fill(string $templatePath, array $data): string
  {
    if (!class_exists(\ZipArchive::class)) {
      if (PHP_OS_FAMILY === 'Windows') {
        return self::fillUsingPowerShellArchive($templatePath, $data);
      }
      throw new \RuntimeException('PHP zip extension is not enabled (ZipArchive missing)');
    }

    $zip = new \ZipArchive();
    if ($zip->open($templatePath, \ZipArchive::RDONLY) !== true) {
      throw new \RuntimeException('Cannot open template');
    }
    $xml = $zip->getFromName('word/document.xml');
    if ($xml === false) {
      $zip->close();
      throw new \RuntimeException('No document.xml');
    }
    $xml = self::replacePlaceholders($xml, $data);
    $zip->close();

    $tmpOut = tempnam(sys_get_temp_dir(), 'wc') . '.docx';
    $zipOut = new \ZipArchive();
    $zipOut->open($tmpOut, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    $zip2 = new \ZipArchive();
    $zip2->open($templatePath, \ZipArchive::RDONLY);
    for ($i = 0; $i < $zip2->numFiles; $i++) {
      $name = $zip2->getNameIndex($i);
      $content = $zip2->getFromIndex($i);
      if ($name === 'word/document.xml') {
        $content = $xml;
      }
      $zipOut->addFromString($name, $content);
    }
    $zip2->close();
    $zipOut->close();
    return $tmpOut;
  }

  private static function fillUsingPowerShellArchive(string $templatePath, array $data): string
  {
    if (!is_file($templatePath)) {
      throw new \RuntimeException('Cannot open template');
    }

    $tmpBase = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'wc_docx_' . bin2hex(random_bytes(6));
    $extractDir = $tmpBase . DIRECTORY_SEPARATOR . 'extract';
    $templateZip = $tmpBase . DIRECTORY_SEPARATOR . 'template.zip';
    if (!@mkdir($extractDir, 0777, true) && !is_dir($extractDir)) {
      throw new \RuntimeException('Unable to prepare temporary workspace');
    }

    try {
      if (!@copy($templatePath, $templateZip)) {
        throw new \RuntimeException('Cannot open template');
      }

      self::runPowerShellCommand(
        "Expand-Archive -LiteralPath '" . self::psQuote($templateZip) . "' -DestinationPath '" . self::psQuote($extractDir) . "' -Force"
      );

      $docXmlPath = $extractDir . DIRECTORY_SEPARATOR . 'word' . DIRECTORY_SEPARATOR . 'document.xml';
      if (!is_file($docXmlPath)) {
        throw new \RuntimeException('No document.xml');
      }

      $xml = file_get_contents($docXmlPath);
      if ($xml === false) {
        throw new \RuntimeException('No document.xml');
      }
      $xml = self::replacePlaceholders($xml, $data);
      if (@file_put_contents($docXmlPath, $xml) === false) {
        throw new \RuntimeException('Unable to write document.xml');
      }

      $tmpOut = tempnam(sys_get_temp_dir(), 'wc') . '.docx';
      $tmpZip = $tmpOut . '.zip';
      if (is_file($tmpZip)) {
        @unlink($tmpZip);
      }

      self::runPowerShellCommand(
        "Compress-Archive -Path '" . self::psQuote($extractDir . DIRECTORY_SEPARATOR . '*') . "' -DestinationPath '" . self::psQuote($tmpZip) . "' -Force"
      );

      if (!is_file($tmpZip)) {
        throw new \RuntimeException('Failed to package generated document');
      }

      if (is_file($tmpOut)) {
        @unlink($tmpOut);
      }
      if (!@rename($tmpZip, $tmpOut)) {
        throw new \RuntimeException('Failed to finalize generated document');
      }

      return $tmpOut;
    } finally {
      self::removeDirectoryRecursive($tmpBase);
    }
  }

  private static function runPowerShellCommand(string $script): void
  {
    $psBinary = getenv('SystemRoot')
      ? rtrim((string) getenv('SystemRoot'), '\\/') . '\\System32\\WindowsPowerShell\\v1.0\\powershell.exe'
      : 'powershell.exe';
    if (!is_file($psBinary)) {
      $psBinary = 'powershell.exe';
    }

    $cmd = escapeshellarg($psBinary)
      . ' -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command '
      . escapeshellarg($script);

    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);
    if ($exitCode !== 0) {
      $details = trim(implode("\n", $output));
      throw new \RuntimeException($details !== '' ? ('Failed to process DOCX archive: ' . $details) : 'Failed to process DOCX archive');
    }
  }

  private static function psQuote(string $value): string
  {
    return str_replace("'", "''", $value);
  }

  private static function removeDirectoryRecursive(string $dir): void
  {
    if (!is_dir($dir)) {
      return;
    }

    $items = @scandir($dir);
    if (!is_array($items)) {
      return;
    }

    foreach ($items as $item) {
      if ($item === '.' || $item === '..') {
        continue;
      }
      $path = $dir . DIRECTORY_SEPARATOR . $item;
      if (is_dir($path)) {
        self::removeDirectoryRecursive($path);
      } else {
        @unlink($path);
      }
    }

    @rmdir($dir);
  }

  private static function replacePlaceholders(string $xml, array $data): string
  {
    $tokens = [
      '{#medications}',
      '{/medications}',
      '{medications}',
      '{/medication}',
      '{medication}',
      '{#medication}',
    ];
    foreach (['number', 'index', 'no', 'item_no', 'itemNo', 'medication_name', 'medicationName', 'MedicationName', 'dosage', 'frequency', 'duration', 'instructions'] as $medKey) {
      $tokens[] = '{' . $medKey . '}';
      $tokens[] = '${' . $medKey . '}';
      if (!in_array($medKey, ['medicationName', 'MedicationName'], true)) {
        $tokens[] = '{' . ucfirst($medKey) . '}';
        $tokens[] = '${' . ucfirst($medKey) . '}';
      }
    }
    foreach ($data as $key => $value) {
      if (!is_string($key) || $key === '') continue;
      $tokens[] = '{' . $key . '}';
      $tokens[] = '${' . $key . '}';
    }
    $xml = self::normalizeSplitTokens($xml, $tokens);

    $esc = function ($v) {
      if ($v === null || $v === '') return '';
      return htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    };
    $escWithWordBreaks = function ($v) {
      if ($v === null || $v === '') return '';
      $escaped = htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
      return str_replace(["\r\n", "\r", "\n"], '</w:t><w:br/><w:t>', $escaped);
    };

    if (is_array($data['medications'] ?? null)) {
      $xml = self::replaceMedicationsLoop($xml, $data['medications'], $esc);
    }

    foreach ($data as $key => $value) {
      if (is_array($value) && $key === 'medications') continue;
      if (is_array($value)) continue;
      $xml = str_replace('{' . $key . '}', $escWithWordBreaks($value), $xml);
      $xml = str_replace('${' . $key . '}', $escWithWordBreaks($value), $xml);
    }
    return $xml;
  }

  private static function normalizeSplitTokens(string $xml, array $tokens): string
  {
    foreach ($tokens as $token) {
      if (!is_string($token) || $token === '') continue;
      $chars = preg_split('//u', $token, -1, PREG_SPLIT_NO_EMPTY);
      if (!$chars) continue;
      $pattern = '/' . implode('(?:<[^>]+>)*', array_map(static function ($ch) {
        return preg_quote($ch, '/');
      }, $chars)) . '/u';
      $xml = preg_replace($pattern, $token, $xml) ?? $xml;
    }
    return $xml;
  }

  private static function replaceMedicationsLoop(string $xml, array $medications, callable $esc): string
  {
    $openPattern = '\{(?:#\s*medications|#\s*medication|medications|medication)\s*\}';
    $closePattern = '\{\/\s*medications?\s*\}';
    $replaceFieldTokens = static function (string $text, string $key, string $value): string {
      $ucKey = ucfirst($key);
      $tokens = [
        '{' . $key . '}',
        '${' . $key . '}',
        '{' . $ucKey . '}',
        '${' . $ucKey . '}',
        '{' . $key,
        '{' . $ucKey,
      ];
      return str_replace($tokens, $value, $text);
    };

    $pattern = '/' . $openPattern . '(.*?)' . $closePattern . '/is';
    if (preg_match($pattern, $xml)) {
      $xml = preg_replace_callback($pattern, static function (array $match) use ($medications, $esc, $replaceFieldTokens): string {
        $block = $match[1] ?? '';
        $repl = '';
        $hasNumberToken = preg_match('/\{(?:number|index|no|item_no|itemNo|Number|Index|No|Item_no|ItemNo)\}|\$\{(?:number|index|no|item_no|itemNo|Number|Index|No|Item_no|ItemNo)\}/', $block) === 1;

        $runningNumber = 1;
        foreach ($medications as $medication) {
          $med = is_array($medication) ? $medication : [];
          $row = $block;
          $rawNumber = $med['number'] ?? $med['index'] ?? $med['no'] ?? $med['item_no'] ?? $med['itemNo'] ?? $runningNumber;
          $medNumber = $esc((string) $rawNumber);
          $row = $replaceFieldTokens($row, 'number', $medNumber);
          $row = $replaceFieldTokens($row, 'index', $medNumber);
          $row = $replaceFieldTokens($row, 'no', $medNumber);
          $row = $replaceFieldTokens($row, 'item_no', $medNumber);
          $row = str_replace(['{itemNo}', '${itemNo}', '{ItemNo}', '${ItemNo}', '{itemNo', '{ItemNo'], $medNumber, $row);

          $medNameRaw = (string) ($med['name'] ?? $med['medication_name'] ?? '');
          $medName = $esc($medNameRaw);
          $nameReplacement = $medName;
          if (!$hasNumberToken && trim($medNameRaw) !== '') {
            $nameReplacement = $esc((string) $rawNumber . '. ' . $medNameRaw);
          }
          $row = $replaceFieldTokens($row, 'name', $hasNumberToken ? $medName : $nameReplacement);
          $row = $replaceFieldTokens($row, 'medication_name', $nameReplacement);
          $row = str_replace(['{medicationName}', '${medicationName}', '{MedicationName}', '${MedicationName}', '{medicationName', '{MedicationName'], $nameReplacement, $row);

          foreach (['dosage', 'frequency', 'duration', 'instructions'] as $k) {
            $row = $replaceFieldTokens($row, $k, $esc($med[$k] ?? ''));
          }

          $repl .= $row;
          $runningNumber++;
        }

        return $repl;
      }, $xml) ?? $xml;

      return $xml;
    }

    $joinValues = static function (array $values, callable $esc): string {
      $clean = array_values(array_filter(array_map(static function ($v) {
        return trim((string) $v);
      }, $values), static function ($v) {
        return $v !== '';
      }));
      if (empty($clean)) return '';
      return implode(' | ', array_map(static function ($v) use ($esc) {
        return $esc($v);
      }, $clean));
    };

    $medNameValues = [];
    $runningNumber = 1;
    foreach ($medications as $medication) {
      if (!is_array($medication)) {
        $runningNumber++;
        continue;
      }
      $medicationName = trim((string) ($medication['name'] ?? $medication['medication_name'] ?? ''));
      if ($medicationName === '') {
        $runningNumber++;
        continue;
      }
      $rawNumber = $medication['number'] ?? $medication['index'] ?? $medication['no'] ?? $medication['item_no'] ?? $medication['itemNo'] ?? $runningNumber;
      $medNameValues[] = (string) $rawNumber . '. ' . $medicationName;
      $runningNumber++;
    }
    $medName = $joinValues($medNameValues, $esc);

    $medNumbers = [];
    $runningNumber = 1;
    foreach ($medications as $medication) {
      if (!is_array($medication)) {
        $medNumbers[] = (string) $runningNumber;
        $runningNumber++;
        continue;
      }
      $rawNumber = $medication['number'] ?? $medication['index'] ?? $medication['no'] ?? $medication['item_no'] ?? $medication['itemNo'] ?? $runningNumber;
      $medNumbers[] = (string) $rawNumber;
      $runningNumber++;
    }
    $medNumber = $joinValues($medNumbers, $esc);

    $nameTokenCount = 0;
    $xml = preg_replace_callback('/\{name\}|\$\{name\}|\{Name\}|\$\{Name\}/i', static function ($matches) use (&$nameTokenCount) {
      $nameTokenCount++;
      if ($nameTokenCount === 1) {
        return $matches[0];
      }
      return '{medication_name}';
    }, $xml) ?? $xml;

    $xml = str_replace('{medication_name}', $medName, $xml);
    $xml = str_replace('${medication_name}', $medName, $xml);
    $xml = str_replace('{medicationName}', $medName, $xml);
    $xml = str_replace('${medicationName}', $medName, $xml);
    $xml = str_replace('{MedicationName}', $medName, $xml);
    $xml = str_replace('${MedicationName}', $medName, $xml);
    foreach (['number', 'index', 'no', 'item_no'] as $k) {
      $xml = str_replace('{' . $k . '}', $medNumber, $xml);
      $xml = str_replace('${' . $k . '}', $medNumber, $xml);
      $xml = str_replace('{' . ucfirst($k) . '}', $medNumber, $xml);
      $xml = str_replace('${' . ucfirst($k) . '}', $medNumber, $xml);
      $xml = str_replace('{' . $k, $medNumber, $xml);
      $xml = str_replace('{' . ucfirst($k), $medNumber, $xml);
    }
    $xml = str_replace('{itemNo}', $medNumber, $xml);
    $xml = str_replace('${itemNo}', $medNumber, $xml);
    $xml = str_replace('{ItemNo}', $medNumber, $xml);
    $xml = str_replace('${ItemNo}', $medNumber, $xml);
    $xml = str_replace('{itemNo', $medNumber, $xml);
    $xml = str_replace('{ItemNo', $medNumber, $xml);
    foreach (['dosage', 'frequency', 'duration', 'instructions'] as $k) {
      $val = $joinValues(array_map(static function ($med) use ($k) {
        if (!is_array($med)) return '';
        return (string) ($med[$k] ?? '');
      }, $medications), $esc);
      $xml = str_replace('{' . $k . '}', $val, $xml);
      $xml = str_replace('${' . $k . '}', $val, $xml);
      $xml = str_replace('{' . ucfirst($k) . '}', $val, $xml);
      $xml = str_replace('${' . ucfirst($k) . '}', $val, $xml);
      $xml = str_replace('{' . $k, $val, $xml);
      $xml = str_replace('{' . ucfirst($k), $val, $xml);
    }
    $xml = str_replace(['{#medications}', '{/medications}', '{medications}', '{/medication}', '{medication}', '{#medication}'], '', $xml);
    return $xml;
  }

  public static function convertToPdf(string $docxPath): ?string
  {
    $outDir = dirname($docxPath);
    $base = pathinfo($docxPath, PATHINFO_FILENAME);
    $soffice = self::resolveSofficeBinary();
    if ($soffice !== null) {
      $nullSink = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
      $cmd = sprintf(
        '%s --headless --convert-to pdf --outdir %s %s >%s 2>&1',
        escapeshellarg($soffice),
        escapeshellarg($outDir),
        escapeshellarg($docxPath),
        $nullSink
      );
      exec($cmd);
      $pdfPath = $outDir . DIRECTORY_SEPARATOR . $base . '.pdf';
      if (file_exists($pdfPath)) {
        return $pdfPath;
      }
    }

    return self::convertViaRemoteApi($docxPath);
  }

  private static function convertViaRemoteApi(string $docxPath): ?string
  {
    $endpoint = self::resolveRemoteConverterUrl();
    if ($endpoint === null || !is_file($docxPath)) {
      return null;
    }

    $token = self::resolveRemoteConverterToken();
    $pdfData = null;

    if (function_exists('curl_init')) {
      $ch = curl_init($endpoint);
      if ($ch === false) {
        return null;
      }

      $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
      $cFile = curl_file_create($docxPath, $mime, basename($docxPath));
      $postFields = [
        'file' => $cFile,
        'files' => $cFile,
        'files[0]' => $cFile,
        'output_format' => 'pdf',
      ];

      $headers = ['Accept: application/pdf'];
      if ($token !== null) {
        $headers[] = 'Authorization: Bearer ' . $token;
      }

      curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
      ]);

      $resp = curl_exec($ch);
      $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
      curl_close($ch);

      if (is_string($resp) && $status >= 200 && $status < 300 && self::looksLikePdf($resp, $contentType)) {
        $pdfData = $resp;
      }
    }

    if ($pdfData === null) {
      return null;
    }

    $outDir = dirname($docxPath);
    $base = pathinfo($docxPath, PATHINFO_FILENAME);
    $pdfPath = $outDir . DIRECTORY_SEPARATOR . $base . '.pdf';
    $written = @file_put_contents($pdfPath, $pdfData);
    return ($written !== false && is_file($pdfPath)) ? $pdfPath : null;
  }

  private static function looksLikePdf(string $body, string $contentType): bool
  {
    if (stripos($contentType, 'application/pdf') !== false) {
      return true;
    }
    return strncmp($body, '%PDF-', 5) === 0;
  }

  private static function resolveRemoteConverterUrl(): ?string
  {
    $candidates = [
      defined('DOCX_TO_PDF_URL') ? (string) DOCX_TO_PDF_URL : '',
      getenv('DOCX_TO_PDF_URL') ?: '',
      getenv('DOCX_PDF_API_URL') ?: '',
    ];

    foreach ($candidates as $candidate) {
      $candidate = trim((string) $candidate);
      if ($candidate !== '' && preg_match('#^https?://#i', $candidate)) {
        return $candidate;
      }
    }

    return null;
  }

  private static function resolveRemoteConverterToken(): ?string
  {
    $candidates = [
      defined('DOCX_TO_PDF_TOKEN') ? (string) DOCX_TO_PDF_TOKEN : '',
      getenv('DOCX_TO_PDF_TOKEN') ?: '',
      getenv('DOCX_PDF_API_TOKEN') ?: '',
    ];

    foreach ($candidates as $candidate) {
      $candidate = trim((string) $candidate);
      if ($candidate !== '') {
        return $candidate;
      }
    }

    return null;
  }

  private static function resolveSofficeBinary(): ?string
  {
    $envCandidates = [
      getenv('LIBREOFFICE_PATH') ?: '',
      getenv('SOFFICE_PATH') ?: '',
    ];
    foreach ($envCandidates as $candidate) {
      $candidate = trim((string) $candidate, " \t\n\r\0\x0B\"");
      if ($candidate !== '' && is_file($candidate)) {
        return $candidate;
      }
    }

    if (PHP_OS_FAMILY === 'Windows') {
      $programFiles = getenv('ProgramFiles') ?: 'C:\\Program Files';
      $programFilesX86 = getenv('ProgramFiles(x86)') ?: 'C:\\Program Files (x86)';
      $windowsCandidates = [
        $programFiles . '\\LibreOffice\\program\\soffice.exe',
        $programFilesX86 . '\\LibreOffice\\program\\soffice.exe',
      ];
      foreach ($windowsCandidates as $candidate) {
        if (is_file($candidate)) {
          return $candidate;
        }
      }
      return 'soffice';
    }

    return 'soffice';
  }
}
