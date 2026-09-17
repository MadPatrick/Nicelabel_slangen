<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/queries.php';
require_once __DIR__ . '/bridge_client.php';

/**
 * Enige plek die index.php (en de bridge zelf) aanroept om slangkaarten op
 * te halen - verbergt of dat rechtstreeks via SQL Server gaat ('direct')
 * of via een losse bridge-installatie ('bridge'), zie inc/config.php.
 */
function findHoseCardsForOrder(string $orderNumber): array
{
    $config = appConfig();

    if ($config['dataSource'] === 'bridge') {
        return findHoseCardsViaBridge($orderNumber);
    }

    $pdo = getPdoConnection();
    return findHoseCards($pdo, $orderNumber);
}
