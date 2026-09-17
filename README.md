# Slangkaarten bij order (webapp)

Webbased vervanger voor het NiceLabel-formulier "Slangkaarten per order": een
ordernummer invullen, de orderregels uit de SQL-database ophalen en een
slangkaart/bon tonen en printen. Gebouwd in de stijl van
[Geeve_selectors](https://github.com/MadPatrick/Geeve_selectors): PHP zonder
framework, vanilla JS/CSS, geen build-stap nodig.

## Status

Dit is een werkende scaffold, maar de **exacte kolomnamen van de database
zijn nog niet geverifieerd** (zie hieronder). De app rendert nu alle kolommen
die de query teruggeeft generiek (label = kolomnaam, waarde = celinhoud).
Zodra het echte schema en de gewenste labelopmaak bekend zijn, kunnen
`inc/queries.php` (welke kolommen/tabellen) en `index.php` (volgorde,
labels, welke velden op de bon horen) verder verfijnd worden naar een 1-op-1
kopie van de originele NiceLabel-slangkaart.

## Vereisten

- PHP 8.1+
- PDO_SQLSRV extensie (Microsoft ODBC Driver 17/18 for SQL Server)
  - **Windows/IIS**: installeer de [Microsoft Drivers for PHP for SQL
    Server](https://learn.microsoft.com/sql/connect/php/microsoft-php-driver-for-sql-server)
    en zet `extension=pdo_sqlsrv` aan in `php.ini`.
  - **Linux**: installeer `msodbcsql18` (Microsoft apt/yum repo) en daarna
    `sudo pecl install sqlsrv pdo_sqlsrv`, dan de extensies aanzetten in
    `php.ini`.
- Netwerktoegang vanaf de webserver naar `GEEVE-SQL-2019` (poort 1433).

## Installatie

1. `cp .env.example .env`
2. Vul `.env` met de gegevens van de nieuwe SQL-login (zie hieronder).
3. Start lokaal met: `php -S localhost:8000`
4. Open `http://localhost:8000/index.php`, vul een ordernummer in.

In productie: zet de map op een PHP-webserver (IIS of Apache/nginx) en zorg
dat `.env` buiten de webroot staat of niet-uitleesbaar is voor de browser
(bijv. via een `.htaccess`-regel of door hem een niveau boven de docroot te
zetten en het pad in `inc/config.php` aan te passen).

## SQL-login aanmaken (read-only, alleen voor deze webapp)

Voer dit uit op `GEEVE-SQL-2019` (met een account dat rechten mag toekennen).
Pas het wachtwoord aan en gebruik dat in `.env`:

```sql
USE [Slangkaarten];
CREATE LOGIN [webapp_slangkaarten] WITH PASSWORD = 'VUL-EEN-STERK-WACHTWOORD-IN';
CREATE USER [webapp_slangkaarten] FOR LOGIN [webapp_slangkaarten];

GRANT SELECT ON [dbo].[2500 Slangkaarten bij order] TO [webapp_slangkaarten];
GRANT SELECT ON [dbo].[93004 hv 3001 Slangonderdelen Zijde A] TO [webapp_slangkaarten];
GRANT SELECT ON [dbo].[93004 hv 3001 Slangonderdelen Zijde B] TO [webapp_slangkaarten];
GRANT SELECT ON [dbo].[9501 hv 2501 order picklijst slangen] TO [webapp_slangkaarten];
GRANT SELECT ON [dbo].[9501 hv 2501 order picklijst slangonderdelen] TO [webapp_slangkaarten];
GRANT SELECT ON [dbo].[9501 hv 2501 order picklijst overige] TO [webapp_slangkaarten];
```

Zorg dat op de server zelf "SQL Server and Windows Authentication mode"
(gemengde modus) aan staat, anders werkt een SQL-login niet.

## Database-schema achterhalen

`inc/queries.php` gaat er nu van uit dat elke tabel een kolom `OrderNr`
heeft om op te filteren (en `RegelNr` om regels te sorteren). Draai
onderstaande query per tabel (of in 1x met de `IN (...)`-lijst) om de echte
kolomnamen te zien, en stuur de uitkomst door zodat de query's aangescherpt
kunnen worden:

```sql
SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, ORDINAL_POSITION
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME IN (
    '2500 Slangkaarten bij order',
    '93004 hv 3001 Slangonderdelen Zijde A',
    '93004 hv 3001 Slangonderdelen Zijde B',
    '9501 hv 2501 order picklijst slangen',
    '9501 hv 2501 order picklijst slangonderdelen',
    '9501 hv 2501 order picklijst overige'
)
ORDER BY TABLE_NAME, ORDINAL_POSITION;
```

Handig om ook te delen, indien beschikbaar:
- De originele "SQL query" / join-configuratie uit het NiceLabel Data
  Source-scherm (als daar een custom join/filter is ingesteld i.p.v. de
  automatische tabelrelaties van NiceLabel).
- Een screenshot of PDF-export van het "Slangkaart"-label zelf, zodat de
  print-layout (`#printSheet` in `index.php` / `assets/style.css`) 1-op-1
  gemaakt kan worden i.p.v. de huidige generieke tabelweergave.

## Projectstructuur

```
index.php            Formulier + resultaatweergave + printlayout
assets/style.css      Vormgeving (gebaseerd op Geeve_selectors design)
inc/config.php         .env-loader + configuratie
inc/db.php             PDO/SQL Server verbinding
inc/queries.php        Alle SQL-queries (kolomnamen nog te verifieren)
.env.example            Voorbeeld-configuratie
```

## Beveiliging

- Alle queries gebruiken parameter binding (`PDO::prepare`) tegen SQL-injectie.
- De SQL-login heeft alleen `SELECT` op de 6 benodigde tabellen (least privilege).
- `.env` staat in `.gitignore` en mag nooit gecommit worden.
- Overweeg een eenvoudige login (bijv. Basic Auth via de webserver, of
  Windows-authenticatie via IIS) als de app niet alleen binnen een
  vertrouwd intern netwerk bereikbaar is.
