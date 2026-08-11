<?php
declare(strict_types=1);

require_once __DIR__ . '/AuthService.php';

Auth::requireLogin();

$file = (string)($_GET['file'] ?? '');
$file = str_replace('\\', '/', $file);

if ($file === '' || str_contains($file, '..') || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}\/[A-Za-z0-9_.-]+\.(html|json)$/', $file, $matches)) {
    http_response_code(400);
    echo 'Invalid report file.';
    exit;
}

$reportsRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'reports';
$path = $reportsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);

if (!is_file($path)) {
    http_response_code(404);
    echo 'Report file not found.';
    exit;
}

$extension = strtolower($matches[1]);
header('Content-Type: ' . ($extension === 'json' ? 'application/json' : 'text/html; charset=utf-8'));
header('X-Content-Type-Options: nosniff');
readfile($path);
