<?php

declare(strict_types=1);

/**
 * LET OP - NOG TE VERIFIEREN TEGEN HET ECHTE SCHEMA
 * ==================================================
 * De tabelnamen hieronder komen 1-op-1 uit de NiceLabel-database-connectie
 * (GEEVE-SQL-2019.Slangkaarten). De kolomnamen (OrderNr, RegelNr, ...) zijn
 * nog NIET geverifieerd - dit zijn plausibele aannames zodat de applicatie
 * al draait. Vervang ze zodra je het echte schema hebt opgevraagd, zie
 * README.md sectie "Database-schema achterhalen".
 *
 * Tabellen (zoals geselecteerd in de NiceLabel data source):
 *   - "2500 Slangkaarten bij order"                      alias A_   (orderregel / hoofdgegevens)
 *   - "93004 hv 3001 Slangonderdelen Zijde A"             alias AA_  (koppelonderdelen zijde A)
 *   - "93004 hv 3001 Slangonderdelen Zijde B"             alias BB_  (koppelonderdelen zijde B)
 *   - "9501 hv 2501 order picklijst slangen"              alias SL_  (orderregels: slangen)
 *   - "9501 hv 2501 order picklijst slangonderdelen"      alias O_   (orderregels: slangonderdelen)
 *   - "9501 hv 2501 order picklijst overige"              alias OV_  (orderregels: overige artikelen)
 */

/**
 * Haalt de hoofdgegevens van een order op (klant, referentie, datum, ...).
 * Retourneert null als het ordernummer niet bestaat.
 */
function findOrderHeader(PDO $pdo, string $orderNumber): ?array
{
    $sql = "SELECT TOP 1 *
            FROM [dbo].[2500 Slangkaarten bij order]
            WHERE OrderNr = :orderNumber";

    $statement = $pdo->prepare($sql);
    $statement->execute(['orderNumber' => $orderNumber]);
    $row = $statement->fetch();

    return $row === false ? null : $row;
}

/** Orderregels: slangen. */
function findOrderHoseLines(PDO $pdo, string $orderNumber): array
{
    $sql = "SELECT *
            FROM [dbo].[9501 hv 2501 order picklijst slangen]
            WHERE OrderNr = :orderNumber
            ORDER BY RegelNr";

    $statement = $pdo->prepare($sql);
    $statement->execute(['orderNumber' => $orderNumber]);

    return $statement->fetchAll();
}

/** Orderregels: losse slangonderdelen (koppelingen e.d.). */
function findOrderHosePartLines(PDO $pdo, string $orderNumber): array
{
    $sql = "SELECT *
            FROM [dbo].[9501 hv 2501 order picklijst slangonderdelen]
            WHERE OrderNr = :orderNumber
            ORDER BY RegelNr";

    $statement = $pdo->prepare($sql);
    $statement->execute(['orderNumber' => $orderNumber]);

    return $statement->fetchAll();
}

/** Orderregels: overige artikelen. */
function findOrderOtherLines(PDO $pdo, string $orderNumber): array
{
    $sql = "SELECT *
            FROM [dbo].[9501 hv 2501 order picklijst overige]
            WHERE OrderNr = :orderNumber
            ORDER BY RegelNr";

    $statement = $pdo->prepare($sql);
    $statement->execute(['orderNumber' => $orderNumber]);

    return $statement->fetchAll();
}

/** Koppelonderdelen zijde A, per slangregel van de order. */
function findHosePartsSideA(PDO $pdo, string $orderNumber): array
{
    $sql = "SELECT *
            FROM [dbo].[93004 hv 3001 Slangonderdelen Zijde A]
            WHERE OrderNr = :orderNumber";

    $statement = $pdo->prepare($sql);
    $statement->execute(['orderNumber' => $orderNumber]);

    return $statement->fetchAll();
}

/** Koppelonderdelen zijde B, per slangregel van de order. */
function findHosePartsSideB(PDO $pdo, string $orderNumber): array
{
    $sql = "SELECT *
            FROM [dbo].[93004 hv 3001 Slangonderdelen Zijde B]
            WHERE OrderNr = :orderNumber";

    $statement = $pdo->prepare($sql);
    $statement->execute(['orderNumber' => $orderNumber]);

    return $statement->fetchAll();
}

/**
 * Verzamelt alle gegevens voor een order in een enkele structuur,
 * klaar voor weergave/print. Retourneert null als de order niet bestaat.
 */
function findOrderData(PDO $pdo, string $orderNumber): ?array
{
    $header = findOrderHeader($pdo, $orderNumber);
    if ($header === null) {
        return null;
    }

    return [
        'header'         => $header,
        'hoseLines'      => findOrderHoseLines($pdo, $orderNumber),
        'hosePartLines'  => findOrderHosePartLines($pdo, $orderNumber),
        'otherLines'     => findOrderOtherLines($pdo, $orderNumber),
        'hosePartsSideA' => findHosePartsSideA($pdo, $orderNumber),
        'hosePartsSideB' => findHosePartsSideB($pdo, $orderNumber),
    ];
}
