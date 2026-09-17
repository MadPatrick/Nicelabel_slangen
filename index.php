<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/datasource.php';

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

/**
 * Veldlabels + kandidaat-kolomnamen voor de kaart. Zie inc/queries.php
 * voor uitleg over pick() en waarom dit met kandidaat-lijsten werkt i.p.v.
 * vaste kolomnamen (schema nog niet geverifieerd).
 */
const CARD_DETAIL_FIELDS = [
    ['label' => 'Referentie',      'candidates' => ['Referentie']],
    ['label' => 'Uw Referentie',   'candidates' => ['Uw Referentie', 'UwReferentie']],
    ['label' => 'Omschrijving',    'candidates' => ['Omschrijving']],
    ['label' => 'Slang type',      'candidates' => ['Slang type', 'Slangtype', 'Type']],
    ['label' => 'Lengte / Prijs',  'candidates' => ['Lengte / Prijs', 'Lengte/Prijs', 'Lengte']],
    ['label' => 'Snijlengte',      'candidates' => ['Snijlengte']],
];

const CARD_FLAG_SLOTS = [
    ['Labelen', ['Labelen']], ['Testen/spoelen', ['Testen/spoelen', 'Testen spoelen']], ['DNV Certificaat', ['DNV Certificaat']],
    ['Graveren', ['Graveren']], ['Pin prikken', ['Pin prikken']], null,
    ['Testen', ['Testen']], ['Proppen', ['Proppen']], null,
];

const KLANT_CANDIDATES = ['Klant', 'Klantnaam', 'Debiteurnaam', 'Naam debiteur'];
const AANTAL_CANDIDATES = ['Aantal slangen', 'AantalSlangen', 'Aantal'];
const NOTITIE_CANDIDATES = ['Notitie', 'Notite', 'Opmerking', 'Opmerkingen'];
const HOEK_CANDIDATES = ['Hoek', 'Draaihoek'];
const ORDERCREDITEUR_CANDIDATES = ['Ordercrediteur'];
const AFLEVERADRES_CANDIDATES = ['Afleveradres'];
const ORDERDATUM_CANDIDATES = ['Orderdatum'];
const AANGEMAAKT_CANDIDATES = ['Aangemaakt'];
const GEWIJZIGD_CANDIDATES = ['Laatste gewijzigd', 'Laatst gewijzigd'];

const ARTIKELNUMMER_CANDIDATES = ['Artikelnummer', 'ArtikelNr', 'Artikel'];
const QTY_CANDIDATES = ['Qty', 'Aantal'];

/** Simpele SVG-weergave van de draaihoek tussen de twee koppelzijden. */
function renderAngleSvg(?float $degrees): string
{
    if ($degrees === null) {
        return '';
    }

    $degrees = max(0.0, min(360.0, $degrees));
    $radius = 42;
    $cx = 50;
    $cy = 50;
    $startAngle = -90.0;
    $endAngle = $startAngle + $degrees;
    $largeArc = $degrees > 180 ? 1 : 0;

    $toXY = static function (float $angleDeg) use ($cx, $cy, $radius): array {
        $rad = deg2rad($angleDeg);
        return [$cx + $radius * cos($rad), $cy + $radius * sin($rad)];
    };

    [$startX, $startY] = $toXY($startAngle);
    [$endX, $endY] = $toXY($endAngle);

    $wedge = $degrees > 0
        ? sprintf(
            'M %1$s %2$s L %3$s %4$s A %5$s %5$s 0 %6$s 1 %7$s %8$s Z',
            $cx,
            $cy,
            round($startX, 2),
            round($startY, 2),
            $radius,
            $largeArc,
            round($endX, 2),
            round($endY, 2)
        )
        : '';

    $degreesLabel = floor($degrees) === $degrees
        ? number_format($degrees, 0, ',', '')
        : number_format($degrees, 1, ',', '');

    return '<svg class="angle-diagram" viewBox="0 0 100 116" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
        . '<circle cx="50" cy="50" r="' . $radius . '" fill="none" stroke="#999" stroke-width="1.2"></circle>'
        . ($wedge !== '' ? '<path d="' . $wedge . '" fill="#111"></path>' : '')
        . '<line x1="8" y1="50" x2="92" y2="50" stroke="#ccc" stroke-width="0.8"></line>'
        . '<line x1="50" y1="8" x2="50" y2="92" stroke="#ccc" stroke-width="0.8"></line>'
        . '<text x="50" y="108" text-anchor="middle" font-size="11" fill="#111">Hoek ' . h($degreesLabel) . '</text>'
        . '</svg>';
}

/** Rendert de key/value-blokjes bovenaan een koppelzijde-tabel (A of B). */
function renderCouplingTable(string $label, array $rows): string
{
    $html = '<div class="coupling-block"><h4>' . h($label) . '</h4>';

    if ($rows === []) {
        $html .= '<p class="coupling-empty">Geen onderdelen</p>';
    } else {
        $html .= '<table class="coupling-table"><thead><tr><th>Artikelnummer</th><th>Qty</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr><td>' . h(pick($row, ARTIKELNUMMER_CANDIDATES)) . '</td><td>' . h(pick($row, QTY_CANDIDATES)) . '</td></tr>';
        }
        $html .= '</tbody></table>';
    }

    return $html . '</div>';
}

/** Rendert 1 volledige slangkaart (gebruikt zowel op scherm als in print). */
function renderHoseCard(array $card, string $orderNumber): string
{
    $row = $card['row'];

    $slangnummer = pick($row, HOSE_KEY_COLUMNS);
    $klant = pick($row, KLANT_CANDIDATES);
    $aantal = pick($row, AANTAL_CANDIDATES);
    $notitie = pick($row, NOTITIE_CANDIDATES);
    $hoekRaw = pick($row, HOEK_CANDIDATES);
    $hoek = $hoekRaw !== '' ? (float) str_replace(',', '.', $hoekRaw) : null;
    $ordercrediteur = pick($row, ORDERCREDITEUR_CANDIDATES);
    $afleveradres = pick($row, AFLEVERADRES_CANDIDATES);
    $orderdatum = pick($row, ORDERDATUM_CANDIDATES);
    $aangemaakt = pick($row, AANGEMAAKT_CANDIDATES);
    $gewijzigd = pick($row, GEWIJZIGD_CANDIDATES);

    ob_start();
    ?>
    <div class="hose-card">
        <div class="card-top">
            <div class="card-brand">GEEVE <span>HYDRAULICS</span></div>
            <div class="card-order-meta">
                <div><span>Ordernummer</span><strong><?= h($orderNumber) ?></strong></div>
                <?php if ($klant !== ''): ?><div class="card-klant"><?= h($klant) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="card-body">
            <div class="card-details">
                <div class="card-detail-row card-slangnummer">
                    <span>Slangnummer</span>
                    <strong><?= h($slangnummer) ?: '&mdash;' ?></strong>
                </div>
                <?php foreach (CARD_DETAIL_FIELDS as $field): ?>
                    <div class="card-detail-row">
                        <span><?= h($field['label']) ?></span>
                        <strong><?= h(pick($row, $field['candidates'])) ?: '&mdash;' ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="card-qty-box">
                <span>Aantal slangen</span>
                <strong><?= h($aantal) ?: '&mdash;' ?></strong>
            </div>
        </div>

        <?php if ($notitie !== ''): ?>
            <div class="card-note">
                <span>Notitie</span>
                <div><?= nl2br(h($notitie)) ?></div>
            </div>
        <?php endif; ?>

        <div class="card-couplings">
            <?= renderCouplingTable('A', $card['sideA']) ?>
            <?= renderCouplingTable('B', $card['sideB']) ?>
            <div class="angle-block"><?= renderAngleSvg($hoek) ?></div>
        </div>

        <div class="card-flags">
            <?php foreach (CARD_FLAG_SLOTS as $slot): ?>
                <?php if ($slot === null): ?>
                    <div class="card-flag card-flag-empty"></div>
                <?php else: ?>
                    <div class="card-flag"><span><?= h($slot[0]) ?></span><strong><?= h(pick($row, $slot[1])) ?: '&mdash;' ?></strong></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="card-footer">
            <div class="card-footer-addresses">
                <div class="card-address-block">
                    <span>Ordercrediteur</span>
                    <div><?= $ordercrediteur !== '' ? nl2br(h($ordercrediteur)) : '&mdash;' ?></div>
                </div>
                <div class="card-address-block">
                    <span>Afleveradres</span>
                    <div><?= $afleveradres !== '' ? nl2br(h($afleveradres)) : '&mdash;' ?></div>
                </div>
            </div>
            <div class="card-footer-meta">
                <div class="card-meta-block">
                    <span>Orderdatum</span>
                    <div><?= h($orderdatum) ?: '&mdash;' ?></div>
                </div>
                <div class="card-meta-block">
                    <span>Aangemaakt</span>
                    <div><?= h($aangemaakt) ?: '&mdash;' ?></div>
                </div>
                <div class="card-meta-block">
                    <span>Laatste gewijzigd</span>
                    <div><?= h($gewijzigd) ?: '&mdash;' ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

$orderNumber = trim((string) ($_GET['ordernummer'] ?? ''));
$hoseCards = [];
$errorMessage = null;

if ($orderNumber !== '') {
    try {
        $hoseCards = findHoseCardsForOrder($orderNumber);
    } catch (DatabaseConfigException $exception) {
        $errorMessage = $exception->getMessage();
    }
}
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
                    placeholder="bijv. 36019177"
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
    <?php elseif ($orderNumber !== '' && $hoseCards === []): ?>
        <section class="empty-result">Geen slangkaarten gevonden voor ordernummer "<?= h($orderNumber) ?>".</section>
    <?php elseif ($hoseCards !== []): ?>
        <section class="panel result-panel">
            <div class="section-heading">
                <div>
                    <span class="step">Stap 2</span>
                    <h2>Order <?= h($orderNumber) ?> &middot; <?= count($hoseCards) ?> slangkaart<?= count($hoseCards) === 1 ? '' : 'en' ?></h2>
                </div>
                <button type="button" class="print-button" onclick="window.print()">Slangkaarten printen</button>
            </div>

            <?php foreach ($hoseCards as $card): ?>
                <?= renderHoseCard($card, $orderNumber) ?>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>

<?php if ($hoseCards !== []): ?>
<div id="printSheet" aria-hidden="true">
    <?php foreach ($hoseCards as $card): ?>
        <?= renderHoseCard($card, $orderNumber) ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>
</body>
</html>
