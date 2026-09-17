<?php

declare(strict_types=1);

/**
 * LET OP - NOG TE VERIFIEREN TEGEN HET ECHTE SCHEMA
 * ==================================================
 * We hebben nu een echt voorbeeld van de gedrukte slangkaart gezien, maar
 * nog geen INFORMATION_SCHEMA-dump van de database. Deze file raadt daarom
 * per veld een paar plausibele kolomnamen (Nederlands, zoals de labels op
 * de kaart), en probeert ze in volgorde:
 *   - filterkolommen (WAAR op gezocht wordt): zie $ORDER_NUMBER_COLUMNS en
 *     $HOSE_KEY_COLUMNS hieronder. tryColumnsQuery() probeert elke
 *     kandidaat als SQL WHERE-kolom en gebruikt de eerste die niet op een
 *     "invalid column"-fout stuit - dus 1 keer het echte schema opgeven in
 *     die arrays (bovenaan zetten) is genoeg, geen code-verbouwing nodig.
 *   - weergavekolommen (labels op de kaart): zie pick() in index.php, die
 *     op dezelfde manier meerdere kandidaat-kolomnamen per veld afgaat.
 *
 * Structuur (bevestigd door het voorbeeld):
 *   "2500 Slangkaarten bij order" = 1 rij per SLANG (niet per order). Een
 *   order met "Aantal slangen" = 4 heeft dus 1 rij die aangeeft dat er 4x
 *   dezelfde slang gemaakt moet worden - geen 4 losse rijen.
 *   "93004 hv 3001 Slangonderdelen Zijde A/B" = koppelonderdelen, gekoppeld
 *   aan 1 specifieke slang (via Slangnummer), niet aan de hele order.
 *
 * De "9501 hv 2501 order picklijst ..." tabellen zitten wel in de
 * NiceLabel-connectie maar komen niet voor op dit label - vermoedelijk
 * gebruikt voor een andere (picklijst-)afdruk. Ze worden hier niet meer
 * bevraagd; zie git-historie als ze later toch nodig blijken.
 */

const ORDER_NUMBER_COLUMNS = ['Ordernummer', 'OrderNr', 'Order nr', 'Order'];
const HOSE_KEY_COLUMNS = ['Slangnummer', 'SlangNr', 'Slang nr'];

/**
 * Probeert een SELECT * ... WHERE [kolom] = :waarde uit te voeren met de
 * eerste kandidaat-kolomnaam die geen SQL-fout oplevert (bijv. "invalid
 * column name"). Zo hoeft maar 1 plek aangepast te worden zodra het echte
 * schema bekend is, i.p.v. overal in deze file.
 */
function tryColumnsQuery(PDO $pdo, string $table, array $candidateColumns, string $value): array
{
    $lastException = null;

    foreach ($candidateColumns as $column) {
        $sql = "SELECT * FROM [dbo].[{$table}] WHERE [{$column}] = :value";

        try {
            $statement = $pdo->prepare($sql);
            $statement->execute(['value' => $value]);
            return $statement->fetchAll();
        } catch (PDOException $exception) {
            $lastException = $exception;
            continue;
        }
    }

    throw new DatabaseConfigException(
        "Kon tabel \"{$table}\" niet filteren - geen van de verwachte kolomnamen (" .
        implode(', ', $candidateColumns) . ') bestaat in die tabel. Zet de echte ' .
        'kolomnaam vooraan in ORDER_NUMBER_COLUMNS / HOSE_KEY_COLUMNS in inc/queries.php. ' .
        'Laatste SQL-foutmelding: ' . ($lastException?->getMessage() ?? 'onbekend')
    );
}

/** Alle slangregels ("slangkaarten") van een order, 1 rij per slang. */
function findHoseCardRows(PDO $pdo, string $orderNumber): array
{
    return tryColumnsQuery($pdo, '2500 Slangkaarten bij order', ORDER_NUMBER_COLUMNS, $orderNumber);
}

/** Koppelonderdelen aan 1 zijde (A of B) van 1 specifieke slang. */
function findCouplingSide(PDO $pdo, string $sideTable, string $hoseKey): array
{
    if ($hoseKey === '') {
        return [];
    }

    return tryColumnsQuery($pdo, $sideTable, HOSE_KEY_COLUMNS, $hoseKey);
}

/**
 * Bouwt alle slangkaarten van een order op, inclusief de koppelonderdelen
 * per zijde. Retourneert een lege array als de order geen slangen heeft
 * (of niet bestaat).
 */
function findHoseCards(PDO $pdo, string $orderNumber): array
{
    $rows = findHoseCardRows($pdo, $orderNumber);
    $cards = [];

    foreach ($rows as $row) {
        $hoseKey = pick($row, HOSE_KEY_COLUMNS);

        $cards[] = [
            'row'   => $row,
            'sideA' => findCouplingSide($pdo, '93004 hv 3001 Slangonderdelen Zijde A', $hoseKey),
            'sideB' => findCouplingSide($pdo, '93004 hv 3001 Slangonderdelen Zijde B', $hoseKey),
        ];
    }

    return $cards;
}

/**
 * Zoekt een waarde in een databaserij op basis van een lijst kandidaat-
 * kolomnamen, ongevoelig voor spaties/underscores/hoofdletters. Geeft de
 * eerste kandidaat terug die een niet-lege waarde heeft.
 */
function pick(array $row, array $candidateColumns, string $default = ''): string
{
    $normalized = [];
    foreach ($row as $column => $value) {
        $normalized[normalizeColumnKey((string) $column)] = $value;
    }

    foreach ($candidateColumns as $candidate) {
        $key = normalizeColumnKey($candidate);
        if (array_key_exists($key, $normalized)) {
            $value = $normalized[$key];
            if ($value !== null && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }
    }

    return $default;
}

function normalizeColumnKey(string $column): string
{
    return strtolower(str_replace([' ', '_', '-', '/'], '', $column));
}
