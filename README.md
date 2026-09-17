# Slangkaarten bij order (webapp)

Webbased vervanger voor het NiceLabel-formulier "Slangkaarten per order": een
ordernummer invullen, de orderregels uit de SQL-database ophalen en een
slangkaart/bon tonen en printen. Gebouwd in de stijl van
[Geeve_selectors](https://github.com/MadPatrick/Geeve_selectors): PHP zonder
framework, vanilla JS/CSS, geen build-stap nodig.

## Status

De layout in `index.php`/`assets/style.css` is opgebouwd aan de hand van een
echt voorbeeld van de gedrukte slangkaart (header, slangnummer-blok, "Aantal
slangen"-doos, notitie, koppeltabellen A/B + hoek-diagram, flags-tabel,
Ordercrediteur/Afleveradres/datums). Structureel belangrijk: **1 rij in
"2500 Slangkaarten bij order" = 1 slang**, niet 1 rij per order - een order
met "Aantal slangen" = 4 heeft dus 1 rij die aangeeft dat die slang 4x
gemaakt moet worden. Bij een order met meerdere verschillende slangtypen
worden er dus meerdere kaarten (1 per rij) na elkaar getoond/geprint, met
een paginabreak per kaart.

De **exacte kolomnamen zijn nog niet geverifieerd** tegen het echte schema
(zie hieronder) - `inc/queries.php` en `index.php` proberen daarom per veld
een lijst met plausibele kandidaat-kolomnamen (via `pick()` en
`tryColumnsQuery()`), zodat de app al werkt en zichzelf grotendeels
aanpast zodra de echte kolomnamen net iets anders heten. Zet de echte namen
vooraan in die kandidaat-lijsten zodra je het schema kent.

## Vereisten

- PHP 8.1+
- PDO_SQLSRV extensie (Microsoft ODBC Driver 17/18 for SQL Server)
  - **Windows/IIS**: installeer de [Microsoft Drivers for PHP for SQL
    Server](https://learn.microsoft.com/sql/connect/php/microsoft-php-driver-for-sql-server)
    en zet `extension=pdo_sqlsrv` aan in `php.ini`.
  - **Linux (Ubuntu/Debian)**: zie "SQL Server driver installeren
    (Linux)" hieronder.
- Netwerktoegang vanaf de webserver naar `GEEVE-SQL-2019` (poort 1433).

## SQL Server driver installeren (Linux)

Deze foutmelding:

> De PDO_SQLSRV driver is niet geinstalleerd op deze PHP-omgeving.

betekent dat de Microsoft-extensie nog niet in PHP zit. Op Ubuntu/Debian:

```bash
# 1. Microsoft's apt-repository toevoegen (eenmalig)
sudo apt-get update
sudo apt-get install -y curl gnupg apt-transport-https
curl https://packages.microsoft.com/keys/microsoft.asc | sudo tee /etc/apt/trusted.gpg.d/microsoft.asc
curl "https://packages.microsoft.com/config/ubuntu/$(lsb_release -rs)/prod.list" | sudo tee /etc/apt/sources.list.d/mssql-release.list
sudo apt-get update

# 2. ODBC-driver installeren
sudo ACCEPT_EULA=Y apt-get install -y msodbcsql18 unixodbc-dev

# 3. Buildtools + PECL (php-pear) installeren als die er nog niet zijn
sudo apt-get install -y php-dev php-pear build-essential

# 4. De PHP-extensies bouwen en installeren
sudo pecl install sqlsrv pdo_sqlsrv
# (pecl vraagt een paar keer om een keuze te bevestigen - Enter volstaat meestal)

# 5. Extensies aanzetten (vervang 8.x door je eigen PHP-versie, zie "php -v")
echo "extension=sqlsrv.so" | sudo tee /etc/php/8.x/mods-available/sqlsrv.ini
echo "extension=pdo_sqlsrv.so" | sudo tee /etc/php/8.x/mods-available/pdo_sqlsrv.ini
sudo phpenmod -v 8.x sqlsrv pdo_sqlsrv
```

Herstart daarna de webserver (`sudo systemctl restart apache2` /
`php-fpm` / of stop-start je `php -S ...`-commando) en controleer:

```bash
php -m | grep sqlsrv
```

Zie je `pdo_sqlsrv` en `sqlsrv` in de lijst? Dan is de driver actief en kan
`.env` ingevuld worden (zie hierboven).

Op een RHEL/CentOS/Amazon Linux-server verloopt dit net iets anders (yum
i.p.v. apt); zie de officiele Microsoft-documentatie hierboven voor die
variant.

## Geen driver-toegang op deze webserver? Gebruik een bridge

Op sommige (gedeelde) hosting mag/kun je geen PHP-extensies installeren.
In dat geval kan de webserver die bezoekers zien ook zonder PDO_SQLSRV
draaien: hij praat dan via gewone HTTP (curl - vrijwel altijd beschikbaar,
geen speciale rechten nodig) met een **bridge**: een tweede installatie van
dezelfde code, op een machine waar je wel iets mag installeren (je eigen
PC, een interne server, een VPS, etc.), die wel rechtstreeks met SQL Server
praat.

Twee installaties van dezelfde repo dus, met een andere `.env`:

**1. Op de machine met databasetoegang ("de bridge")** - installeer daar
PDO_SQLSRV (zie hierboven) en zet in die `.env`:

```
DATA_SOURCE=direct
DB_HOST=GEEVE-SQL-2019
DB_USER=webapp_slangkaarten
DB_PASSWORD=...
BRIDGE_API_KEY=een-lange-willekeurige-geheime-sleutel
```

Deze installatie hoeft niet publiek bereikbaar te zijn voor eindgebruikers
- alleen `bridge/index.php` moet bereikbaar zijn voor de webserver van
stap 2 (bijv. alleen binnen het interne netwerk, of met een firewall-regel
die enkel het IP-adres van die webserver toelaat).

**2. Op de restricted webserver ("de frontend", waar bezoekers de
ordernummers invullen)** - hier hoeft PDO_SQLSRV niet geinstalleerd te
worden, en hoeven ook geen DB-inloggegevens te staan:

```
DATA_SOURCE=bridge
BRIDGE_URL=https://interne-machine.voorbeeld/pad/naar/bridge/index.php
BRIDGE_API_KEY=een-lange-willekeurige-geheime-sleutel
```

`BRIDGE_API_KEY` moet op beide installaties exact gelijk zijn - dat is de
enige manier waarop de bridge verzoeken van de frontend herkent. Gebruik
altijd `https://` voor `BRIDGE_URL` als de bridge niet in hetzelfde
vertrouwde interne netwerk staat als de frontend, anders reist de API-key
onversleuteld over het internet.

Werkt zo alsof er 1 app draait: de gebruiker vult op de frontend een
ordernummer in, de frontend haalt de kaarten op bij de bridge, en toont/
print ze precies zoals bij een rechtstreekse databaseverbinding.

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
```

(De drie "9501 hv 2501 order picklijst ..." tabellen worden momenteel niet
door deze webapp bevraagd, zie "Database-schema achterhalen" hieronder -
laat de GRANT's daarvoor achterwege tenzij ze later alsnog nodig blijken.)

Zorg dat op de server zelf "SQL Server and Windows Authentication mode"
(gemengde modus) aan staat, anders werkt een SQL-login niet.

## Database-schema achterhalen

`inc/queries.php` filtert nu op de kolom `Ordernummer` in "2500 Slangkaarten
bij order" en op `Slangnummer` in de Zijde A/B-tabellen (met een paar
alternatieve namen als fallback, zie `ORDER_NUMBER_COLUMNS` /
`HOSE_KEY_COLUMNS`). Draai onderstaande query om alle echte kolomnamen te
zien, en stuur de uitkomst door zodat de kandidaat-lijsten in
`inc/queries.php` en `index.php` (bovenaan, de `*_CANDIDATES`-constanten)
aangescherpt kunnen worden naar de exacte namen:

```sql
SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, ORDINAL_POSITION
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME IN (
    '2500 Slangkaarten bij order',
    '93004 hv 3001 Slangonderdelen Zijde A',
    '93004 hv 3001 Slangonderdelen Zijde B'
)
ORDER BY TABLE_NAME, ORDINAL_POSITION;
```

De drie "9501 hv 2501 order picklijst ..." tabellen uit de NiceLabel-
connectie worden momenteel niet gebruikt - ze kwamen niet voor op het
voorbeeldlabel en lijken bij een andere (picklijst-)afdruk te horen.

Nuttig om te verifieren zodra er een test-order beschikbaar is:
- Klopt de aanname dat 1 rij in "2500 Slangkaarten bij order" = 1 slang
  (dus meerdere rijen per order bij meerdere slangtypen)?
- De richting/referentie van het "Hoek"-veld (0/180/270 graden - de
  huidige `renderAngleSvg()` in `index.php` tekent een taartpunt vanaf
  boven, rechtsom; dit is een benadering, nog niet geverifieerd tegen de
  originele grafiek).
- Zijn "Ordercrediteur" en "Afleveradres" al kant-en-klare multi-regel
  tekstvelden in de database (huidige aanname), of moeten ze uit losse
  naam/straat/postcode/plaats-kolommen samengesteld worden?

## Projectstructuur

```
index.php               Formulier + resultaatweergave + printlayout
assets/style.css         Vormgeving (gebaseerd op Geeve_selectors design)
inc/config.php            .env-loader + configuratie (appConfig())
inc/datasource.php        Kiest 'direct' (PDO) of 'bridge' (HTTP), zie DATA_SOURCE
inc/db.php                 PDO/SQL Server verbinding ('direct'-modus)
inc/bridge_client.php       HTTP-client naar een bridge-installatie ('bridge'-modus)
inc/queries.php             Alle SQL-queries (kolomnamen nog te verifieren)
bridge/index.php             Bridge-endpoint (draait op de machine met DB-toegang)
.env.example                  Voorbeeld-configuratie
```

## Beveiliging

- Alle queries gebruiken parameter binding (`PDO::prepare`) tegen SQL-injectie.
- De SQL-login heeft alleen `SELECT` op de 3 benodigde tabellen (least privilege).
- `.env` staat in `.gitignore` en mag nooit gecommit worden.
- Overweeg een eenvoudige login (bijv. Basic Auth via de webserver, of
  Windows-authenticatie via IIS) als de app niet alleen binnen een
  vertrouwd intern netwerk bereikbaar is.
- In bridge-opstelling: zet `bridge/index.php` niet open op het publieke
  internet zonder firewall-restrictie - de API-key beschermt tegen
  onbevoegde verzoeken, maar een extra laag (IP-allowlist, VPN, alleen
  intern netwerk) is sterk aan te raden aangezien dit endpoint direct
  bedrijfsdata uit de database teruggeeft.
