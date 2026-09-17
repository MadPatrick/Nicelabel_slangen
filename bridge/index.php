<?php

declare(strict_types=1);

/**
 * Bridge-endpoint: draai dit (samen met de rest van deze repo) op een
 * machine waar je WEL de SQL Server-driver mag installeren (eigen PC,
 * interne server, etc.). Deze machine heeft een eigen .env met de echte
 * databasegegevens (DATA_SOURCE=direct + DB_*). De publieke/restricted
 * webserver praat hier alleen via HTTP mee (DATA_SOURCE=bridge + BRIDGE_*
 * in zijn eigen .env), zonder zelf een SQL Server-driver nodig te hebben.
 *
 * Zie README.md, sectie "Geen driver-toegang op deze webserver? Gebruik
 * een bridge".
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/queries.php';

header('Content-Type: application/json; charset=utf-8');

function respondError(int $statusCode, string $message): never
{
    http_response_code($statusCode);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = appConfig();
$expectedKey = $config['bridge']['apiKey'] ?? null;

if ($expectedKey === null || $expectedKey === '') {
    respondError(500, 'BRIDGE_API_KEY is niet geconfigureerd op deze bridge (zie .env).');
}

$providedKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!hash_equals((string) $expectedKey, (string) $providedKey)) {
    respondError(401, 'Ongeldige of ontbrekende API-key.');
}

$orderNumber = trim((string) ($_GET['ordernummer'] ?? ''));
if ($orderNumber === '') {
    respondError(400, 'Parameter "ordernummer" ontbreekt.');
}

try {
    $pdo = getPdoConnection();
    $cards = findHoseCards($pdo, $orderNumber);
    echo json_encode(['cards' => $cards], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (DatabaseConfigException $exception) {
    respondError(500, $exception->getMessage());
}
