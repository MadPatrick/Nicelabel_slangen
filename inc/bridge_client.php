<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php'; // voor DatabaseConfigException

/**
 * Haalt slangkaarten op via een losse "bridge"-installatie van deze
 * applicatie (die wel databasetoegang heeft), i.p.v. rechtstreeks met
 * PDO_SQLSRV. Gebruikt curl, dat vrijwel altijd beschikbaar is - dus
 * geschikt voor een webserver waar geen speciale drivers geinstalleerd
 * mogen/kunnen worden.
 */
function findHoseCardsViaBridge(string $orderNumber): array
{
    if (!function_exists('curl_init')) {
        throw new DatabaseConfigException(
            'De curl-extensie ontbreekt in PHP; die is nodig om de bridge te bereiken.'
        );
    }

    $config = appConfig();
    $bridge = $config['bridge'];

    if (($bridge['url'] ?? '') === null || $bridge['url'] === '') {
        throw new DatabaseConfigException(
            'BRIDGE_URL is niet geconfigureerd. Zet in .env de URL naar jouw bridge-installatie ' .
            '(zie README.md, sectie "Geen driver-toegang op deze webserver? Gebruik een bridge").'
        );
    }
    if (($bridge['apiKey'] ?? '') === null || $bridge['apiKey'] === '') {
        throw new DatabaseConfigException('BRIDGE_API_KEY is niet geconfigureerd (zie .env.example).');
    }

    $separator = str_contains($bridge['url'], '?') ? '&' : '?';
    $url = $bridge['url'] . $separator . 'ordernummer=' . urlencode($orderNumber);

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['X-Api-Key: ' . $bridge['apiKey']],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($response === false) {
        throw new DatabaseConfigException("Kan de bridge niet bereiken ({$url}): {$curlError}");
    }

    $data = json_decode((string) $response, true);
    if (!is_array($data)) {
        throw new DatabaseConfigException('Ongeldig antwoord van de bridge (geen geldige JSON).');
    }

    if (isset($data['error'])) {
        throw new DatabaseConfigException('Foutmelding van de bridge: ' . (string) $data['error']);
    }

    if ($httpCode !== 200 || !isset($data['cards']) || !is_array($data['cards'])) {
        throw new DatabaseConfigException("Onverwacht antwoord van de bridge (HTTP {$httpCode}).");
    }

    return $data['cards'];
}
