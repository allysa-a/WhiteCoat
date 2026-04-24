<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;

class NotificationController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function googleFormResponses(): void
    {
        $sourceUrl = trim((string) (getenv('GOOGLE_FORM_RESPONSES_URL') ?: ''));
        if ($sourceUrl === '') {
            $this->json(400, [
                'message' => 'GOOGLE_FORM_RESPONSES_URL is not configured in .env',
                'notifications' => [],
            ]);
            return;
        }

        if (preg_match('#(?:forms\.gle/|docs\.google\.com/forms/)#i', $sourceUrl) === 1) {
            $this->json(422, [
                'message' => 'GOOGLE_FORM_RESPONSES_URL must be a responses data feed URL (Apps Script JSON, Google Visualization JSON, or Google Sheets values JSON), not a Google Form page URL.',
                'notifications' => [],
            ]);
            return;
        }

        $raw = $this->fetchRemotePayload($sourceUrl);
        if ($raw === null || trim($raw) === '') {
            $this->json(502, [
                'message' => 'Unable to fetch Google Form responses feed',
                'notifications' => [],
            ]);
            return;
        }

        $decoded = $this->decodePayload($raw);
        if (!is_array($decoded)) {
            $this->json(422, [
                'message' => 'Unsupported Google Form feed format. Use JSON, Google Visualization JSON, or Google Sheets values JSON.',
                'notifications' => [],
            ]);
            return;
        }

        $rows = $this->extractRows($decoded);
        $notifications = [];
        foreach ($rows as $idx => $row) {
            $normalized = $this->normalizeNotification($row, (int) $idx);
            if ($normalized !== null) {
                $notifications[] = $normalized;
            }
        }

        usort($notifications, function (array $a, array $b): int {
            $aTime = $a['submittedAt'] ? strtotime((string) $a['submittedAt']) : 0;
            $bTime = $b['submittedAt'] ? strtotime((string) $b['submittedAt']) : 0;
            return $bTime <=> $aTime;
        });

        $this->json(200, [
            'count' => count($notifications),
            'notifications' => $notifications,
        ]);
    }

    private function fetchRemotePayload(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 25);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json,text/plain,*/*']);
                curl_setopt($ch, CURLOPT_USERAGENT, 'WhiteCoat-Notifications/1.0');
                $body = curl_exec($ch);
                $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                if ($body !== false && $status >= 200 && $status < 300) {
                    return (string) $body;
                }
                if ($error !== '') {
                    error_log('Google Form feed fetch error: ' . $error);
                }
            }
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 25,
                'header' => "Accept: application/json,text/plain,*/*\r\nUser-Agent: WhiteCoat-Notifications/1.0\r\n",
            ],
        ]);

        $fallback = @file_get_contents($url, false, $context);
        if ($fallback === false) {
            return null;
        }

        return $fallback;
    }

    private function decodePayload(string $payload): ?array
    {
        $trimmed = trim($payload);

        if (preg_match('/google\.visualization\.Query\.setResponse\((.*)\);?$/s', $trimmed, $m) === 1) {
            $jsonChunk = trim((string) ($m[1] ?? ''));
            $decoded = json_decode($jsonChunk, true);
            return is_array($decoded) ? $decoded : null;
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return null;
    }

    private function extractRows(array $decoded): array
    {
        if ($this->isList($decoded)) {
            return array_values(array_filter(array_map([$this, 'toAssoc'], $decoded), static fn($row) => is_array($row)));
        }

        foreach (['notifications', 'responses', 'data', 'items', 'rows'] as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key]) && $this->isList($decoded[$key])) {
                return array_values(array_filter(array_map([$this, 'toAssoc'], $decoded[$key]), static fn($row) => is_array($row)));
            }
        }

        if (isset($decoded['table']) && is_array($decoded['table'])) {
            return $this->extractGoogleVisualizationRows($decoded['table']);
        }

        if (isset($decoded['values']) && is_array($decoded['values']) && count($decoded['values']) > 0) {
            return $this->extractGoogleSheetValuesRows($decoded['values']);
        }

        $single = $this->toAssoc($decoded);
        return $single !== null ? [$single] : [];
    }

    private function extractGoogleVisualizationRows(array $table): array
    {
        $cols = is_array($table['cols'] ?? null) ? $table['cols'] : [];
        $rows = is_array($table['rows'] ?? null) ? $table['rows'] : [];

        $headers = [];
        foreach ($cols as $index => $col) {
            $colArr = $this->toAssoc($col) ?? [];
            $header = trim((string) ($colArr['label'] ?? $colArr['id'] ?? ('Field ' . ((int) $index + 1))));
            $headers[] = $header !== '' ? $header : ('Field ' . ((int) $index + 1));
        }

        $result = [];
        foreach ($rows as $row) {
            $rowArr = $this->toAssoc($row) ?? [];
            $cells = is_array($rowArr['c'] ?? null) ? $rowArr['c'] : [];
            $mapped = [];
            foreach ($headers as $i => $header) {
                $cell = $this->toAssoc($cells[$i] ?? null) ?? [];
                $value = $cell['f'] ?? $cell['v'] ?? null;
                $mapped[$header] = $this->stringifyValue($value);
            }
            $result[] = $mapped;
        }

        return $result;
    }

    private function extractGoogleSheetValuesRows(array $values): array
    {
        $headersRow = array_shift($values);
        if (!is_array($headersRow)) {
            return [];
        }

        $headers = array_map(function ($header, int $idx): string {
            $text = trim((string) $header);
            return $text !== '' ? $text : ('Field ' . ($idx + 1));
        }, $headersRow, array_keys($headersRow));

        $rows = [];
        foreach ($values as $line) {
            if (!is_array($line)) {
                continue;
            }
            $mapped = [];
            foreach ($headers as $i => $header) {
                $mapped[$header] = $this->stringifyValue($line[$i] ?? null);
            }
            $rows[] = $mapped;
        }

        return $rows;
    }

    private function normalizeNotification(array $row, int $index): ?array
    {
        $fields = [];
        foreach ($row as $key => $value) {
            $label = $this->normalizeFieldLabel((string) $key);
            if ($label === '') {
                continue;
            }

            $normalizedValue = $this->stringifyValue($value);
            if (!array_key_exists($label, $fields)) {
                $fields[$label] = $normalizedValue;
                continue;
            }

            $existingValue = trim((string) $fields[$label]);
            $incomingValue = trim($normalizedValue);

            // Keep whichever side (dental/medical) has a real value when duplicate labels exist.
            if ($existingValue === '' && $incomingValue !== '') {
                $fields[$label] = $normalizedValue;
            }
        }

        if (count($fields) === 0) {
            return null;
        }

        $selectedFields = $this->selectPatientFields($fields);
        if (count($selectedFields) === 0) {
            return null;
        }

        $patientName = trim((string) ($selectedFields['Full Name'] ?? ''));
        if ($patientName === '') {
            $patientName = $this->pickPatientName($fields) ?: 'Unknown Patient';
        }

        $submittedAt = $this->pickSubmittedAt($fields);

        return [
            'id' => md5(json_encode([$index, $selectedFields], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)),
            'patientName' => $patientName,
            'submittedAt' => $submittedAt,
            'submittedAtLabel' => $submittedAt ? date('m/d/y h:i A', strtotime($submittedAt)) : 'Unknown time',
            'fields' => $selectedFields,
        ];
    }

    private function selectPatientFields(array $fields): array
    {
        $wanted = [
            'Full Name' => ['medicalappointmentfullname', 'dentalappointmentfullname', 'fullname', 'patientname', 'name'],
            'Gender' => ['gender', 'sex'],
            'Address' => ['address', 'homeaddress'],
            'Classification' => ['classification', 'patienttype'],
            'Type of Consultation' => [
                'dentalappointmenttypeofconsultation',
                'medicalappointmenttypeofconsultation',
                'typeofconsultation',
                'consultationtype',
                'consultation',
            ],
            'Service Needed' => [
                'selectserviceneeded',
                'medicalappointmentserviceneeded',
                'dentalappointmentserviceneeded',
                'serviceneeded',
                'service',
            ],
            'Appointment Date' => ['appointmentdate'],
            'Appointment Time' => ['appointmenttime', 'timeofappointment'],
        ];

        $selected = [];
        foreach ($wanted as $outputLabel => $candidates) {
            $selected[$outputLabel] = $this->firstMatchingFieldValue($fields, $candidates);
        }

        // Optional field: include uploaded lab result link/file value when present in the form response.
        $labResultUpload = $this->firstMatchingFieldValue($fields, [
            'uploadxrayresultifneeded',
            'uploadxrayresult',
            'uploadxray',
            'xrayresultupload',
            'laboratoryresultupload',
            'laboratoryresult',
            'laboratoryresultfile',
            'uploadlaboratoryresultifavailable',
            'labresultupload',
            'labresultfile',
            'uploadlaboratoryresult',
            'uploadlabresult',
        ]);
        if ($labResultUpload !== '') {
            $selected['Laboratory Result Upload'] = $labResultUpload;
        }

        return $selected;
    }

    private function firstMatchingFieldValue(array $fields, array $candidates): string
    {
        $normalizedCandidates = array_map(fn(string $candidate): string => $this->normalizeFieldKey($candidate), $candidates);

        foreach ($fields as $label => $value) {
            $normalizedLabel = $this->normalizeFieldKey((string) $label);
            if ($normalizedLabel === '') {
                continue;
            }

            foreach ($normalizedCandidates as $candidate) {
                if ($candidate === '') {
                    continue;
                }
                if ($normalizedLabel === $candidate || strpos($normalizedLabel, $candidate) !== false) {
                    $matchedValue = trim((string) $value);
                    if ($matchedValue === '') {
                        continue;
                    }
                    return $matchedValue;
                }
            }
        }

        return '';
    }

    private function pickPatientName(array $fields): ?string
    {
        foreach ($fields as $key => $value) {
            if (preg_match('/(^|\b)(patient\s*)?name(\b|$)/i', $key) === 1) {
                $trimmed = trim((string) $value);
                if ($trimmed !== '') {
                    return $trimmed;
                }
            }
        }

        return null;
    }

    private function pickSubmittedAt(array $fields): ?string
    {
        foreach ($fields as $key => $value) {
            if (preg_match('/timestamp|submitted|date|time/i', $key) !== 1) {
                continue;
            }
            $candidate = trim((string) $value);
            if ($candidate === '') {
                continue;
            }
            $ts = strtotime($candidate);
            if ($ts !== false) {
                return date('c', $ts);
            }
        }

        return null;
    }

    private function normalizeFieldLabel(string $raw): string
    {
        $clean = trim((string) preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $raw)));
        $clean = rtrim($clean, ':');
        if ($clean === '') {
            return '';
        }

        return ucwords($clean);
    }

    private function normalizeFieldKey(string $raw): string
    {
        $lower = strtolower($raw);
        return (string) preg_replace('/[^a-z0-9]/', '', $lower);
    }

    private function stringifyValue($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                $str = $this->stringifyValue($item);
                if ($str !== '') {
                    $parts[] = $str;
                }
            }
            return implode(', ', $parts);
        }

        return trim((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function toAssoc($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return get_object_vars($value);
        }

        return null;
    }

    private function isList(array $value): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }

        $i = 0;
        foreach ($value as $key => $_) {
            if ($key !== $i) {
                return false;
            }
            $i++;
        }

        return true;
    }

    private function json(int $code, $data): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
