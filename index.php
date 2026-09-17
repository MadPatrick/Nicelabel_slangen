<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/queries.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function assetVersion(string $relativePath): string
{
    $full = __DIR__ . '/' . $relativePath;
    $mtime = @filemtime($full);
    return $mtime !== false ? (string) $mtime : '1';
}

/** Zet een databasewaarde om naar tekst voor weergave. */
function displayValue($value): string
{
    if ($value === null) {
        return '';
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format('d-m-Y');
    }
    return trim((string) $value);
}

/** Maakt van een kolomnaam ("OrderNr") een leesbaar label ("Order Nr"). */
function humanizeColumn(string $column): string
{
    $spaced = preg_replace('/(?<!^)([A-Z])/', ' $1', $column) ?? $column;
    return ucfirst(trim($spaced));
}

$orderNumber = trim((string) ($_GET['ordernummer'] ?? ''));
$orderData = null;
$errorMessage = null;

if ($orderNumber !== '') {
    try {
        $pdo = getPdoConnection();
        $orderData = findOrderData($pdo, $orderNumber);
    } catch (DatabaseConfigException $exception) {
        $errorMessage = $exception->getMessage();
    }
}

$lineSections = [
    'hoseLines'      => 'Slangen',
    'hosePartLines'  => 'Slangonderdelen',
    'otherLines'     => 'Overige artikelen',
    'hosePartsSideA' => 'Koppelonderdelen zijde A',
    'hosePartsSideB' => 'Koppelonderdelen zijde B',
];
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Slangkaarten bij order</title>
    <link rel="stylesheet" href="assets/style.css?v=<?= h(assetVersion('assets/style.css')) ?>">
</head>
<body>
<main class="page-shell">
    <header class="page-header">
        <div class="brand-panel">
            <p class="eyebrow">Geeve &middot; Slangkaarten</p>
            <h1>Slangkaarten bij order</h1>
        </div>
    </header>

    <section class="panel search-panel">
        <div class="section-heading">
            <div>
                <span class="step">Stap 1</span>
                <h2>Ordernummer opzoeken</h2>
            </div>
        </div>
        <form class="search-row" method="get" action="index.php">
            <label class="field" for="ordernummer">
                <span>Ordernummer</span>
                <input
                    id="ordernummer"
                    name="ordernummer"
                    type="text"
                    inputmode="numeric"
                    placeholder="bijv. 123456"
                    autocomplete="off"
                    autofocus
                    value="<?= h($orderNumber) ?>"
                >
            </label>
            <button type="submit" class="submit-button">Opzoeken</button>
        </form>
    </section>

    <?php if ($errorMessage !== null): ?>
        <section class="warning-box"><?= h($errorMessage) ?></section>
    <?php elseif ($orderNumber !== '' && $orderData === null): ?>
        <section class="empty-result">Geen order gevonden met ordernummer "<?= h($orderNumber) ?>".</section>
    <?php elseif ($orderData !== null): ?>
        <section class="panel result-panel">
            <div class="section-heading">
                <div>
                    <span class="step">Stap 2</span>
                    <h2>Order <?= h($orderNumber) ?></h2>
                </div>
                <button type="button" class="print-button" onclick="window.print()">Slangkaart printen</button>
            </div>

            <div class="order-facts">
                <?php foreach ($orderData['header'] as $column => $value): ?>
                    <div>
                        <span><?= h(humanizeColumn($column)) ?></span>
                        <strong><?= h(displayValue($value)) ?: '&mdash;' ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php foreach ($lineSections as $key => $label): ?>
                <?php $rows = $orderData[$key]; ?>
                <?php if ($rows === []): continue; endif; ?>
                <div class="result-section">
                    <h3><?= h($label) ?></h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <?php foreach (array_keys($rows[0]) as $column): ?>
                                    <th><?= h(humanizeColumn($column)) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <?php foreach ($row as $value): ?>
                                        <td><?= h(displayValue($value)) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>

<?php if ($orderData !== null): ?>
<div id="printSheet" class="print-sheet" aria-hidden="true">
    <div class="print-header">
        <strong>Slangkaart &mdash; order <?= h($orderNumber) ?></strong>
        <div class="print-header-meta"><?= h(date('d-m-Y H:i')) ?></div>
    </div>

    <div class="print-facts">
        <?php foreach ($orderData['header'] as $column => $value): ?>
            <div class="print-fact">
                <span><?= h(humanizeColumn($column)) ?></span>
                <strong><?= h(displayValue($value)) ?: '&mdash;' ?></strong>
            </div>
        <?php endforeach; ?>
    </div>

    <?php foreach ($lineSections as $key => $label): ?>
        <?php $rows = $orderData[$key]; ?>
        <?php if ($rows === []): continue; endif; ?>
        <div class="print-section">
            <h3><?= h($label) ?></h3>
            <table class="print-table">
                <thead>
                    <tr>
                        <?php foreach (array_keys($rows[0]) as $column): ?>
                            <th><?= h(humanizeColumn($column)) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($row as $value): ?>
                                <td><?= h(displayValue($value)) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>

    <div class="print-footer">Gegenereerd via Slangkaarten-webapp op <?= h(date('d-m-Y H:i')) ?></div>
</div>
<?php endif; ?>
</body>
</html>
