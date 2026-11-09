# IIS – systém pro správu událostí

Informační systém pro správu akcí: uživatelé zakládají události, navrhují místa a kategorie, moderátoři je schvalují, účastníci se registrují a po akci hodnotí. Semestrální projekt předmětu IIS, FIT VUT, varianta „Správa událostí“.

## Rychlé odkazy

| Dokument | K čemu slouží |
|---|---|
| [`implementacni-plan.md`](implementacni-plan.md) | rozsah, datový model, routy, rozdělení práce, termíny, testy, nasazení, odevzdání |
| [`AGENTS.md`](AGENTS.md) | závazná pravidla pro vývoj a práci s AI |
| [`zadani.md`](zadani.md) | obecné zadání předmětu |
| [`spec-zadani.md`](spec-zadani.md) | specifické zadání naší varianty |
| [Issues](https://github.com/lukas-dudek/IIS/issues) | konkrétní úkoly, chyby a rozhodnutí |

## Stav projektu

Aktuální stav k 9. 11. 2026: lokální implementace P0 je hotová; P1
(moderační fronty, varování na kolizi, ICS, bezpečnostní hardening) je hotová.
Nasazení na Evu a nahrání videa zbývají.

| Oblast | Stav |
|---|---|
| analýza, role, datový model, plán | hotovo |
| pravidla pro vývoj a AI | hotovo |
| jádro, routy, UI | hotovo lokálně |
| databáze a ukázková data | hotovo |
| P0 funkce | hotovo lokálně |
| P1 (moderace, kolize, ICS, hardening) | hotovo lokálně |
| P2 | záměrně nedělané |
| testy | 77 testů, 279 asercí |
| dokumentace | `docs/doc.html`, `docs/obhajoba.md`, diagramy |
| nasazení na Evu | v přípravě |
| video | scénář hotový, nahrání zbývá |

## Tým

| Člen | Role | Odpovědnost |
|---|---|---|
| Lukáš Dudek | vedoucí, backend | jádro, autentizace, správa uživatelů, katalog míst a kategorií, moderace UI, deployment, bezpečnost, dokumentace |
| Honzík | backend + frontend | události, vyhledávání, registrace a vstupenky, platby, kalendář, komentáře a hodnocení, seed data |

Oba reviewujeme cizí změny. Vlastnictví souborů a pravidla pro zásah do cizí oblasti jsou v [`AGENTS.md`](AGENTS.md).

## Co systém umí

- **Neregistrovaný uživatel** prochází schválené události, místa a kategorie, vyhledává a filtruje.
- **Registrovaný uživatel** se registruje na akce, spravuje profil, zakládá události, navrhuje místa a kategorie, vidí osobní kalendář.
- **Správce události** upravuje svoji událost, odesílá ji ke schválení, vidí účastníky a potvrzuje úhrady.
- **Moderátor** schvaluje události, místa a kategorie, spravuje katalog a moderuje komentáře.
- **Administrátor** má práva moderátora a navíc spravuje uživatele a role.

Podrobná matice oprávnění a toky jsou v plánu, kapitoly 4 a 5.

## Technologie

| Vrstva | Volba |
|---|---|
| Backend | PHP 8.1, vlastní malé MVC, PDO, žádný framework ani ORM |
| Databáze | MySQL/MariaDB na serveru Eva, InnoDB, `utf8mb4` |
| Frontend | HTML5, CSS, Bootstrap 5.3 lokálně, vanilla JavaScript |
| Testy | PHPUnit 10 pouze lokálně, ruční E2E a bezpečnostní scénáře |
| Prostředí | Apache na Evě, `.htaccess`, UTF-8 |
| Verzování | Git + GitHub, privátní repozitář |

Runtime aplikace nemá Composer závislosti. Bootstrap je jediná třetí knihovna, bude nahraný na Evě a do odevzdávaného zipu nepatří; jeho verze bude v `doc.html`.

## Struktura repozitáře

```text
.
├── README.md
├── AGENTS.md
├── implementacni-plan.md
├── zadani.md
├── spec-zadani.md
├── .editorconfig
├── .gitignore
├── .github/
│   ├── pull_request_template.md
│   └── ISSUE_TEMPLATE/
├── app/
│   ├── bootstrap.php
│   ├── Core/                  router, request, response, DB, session, auth, CSRF, validace
│   ├── Controllers/           tenké controllery
│   ├── Repositories/          explicitní SQL a mapování
│   ├── Services/              transakce a business pravidla
│   ├── Support/               helpery pro datum, slug, ICS
│   └── Views/
│       ├── layouts/           společný layout
│       ├── partials/          navigace, formulářové prvky, flash
│       └── errors/            403, 404, 500
├── bin/                       check.sh (php -l, zakázané vzory, PHPUnit)
├── config/
│   ├── config.example.php
│   └── routes.php
├── database/
│   ├── schema.sql
│   └── seed.sql
├── docs/
│   ├── diagrams/              ER, use case, architektura
│   ├── doc.html               dokumentace k odevzdání
│   └── obhajoba.md            vysvětlení klíčových částí (průběžně)
├── public/                    document root
│   ├── index.php              front controller
│   ├── router.php             jen lokální server
│   ├── .htaccess              rewrite, UTF-8, hlavičky
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   └── vendor/            Bootstrap, pouze na Evě
│   └── uploads/               uživatelské obrázky, mimo spouštění PHP
├── storage/
│   ├── logs/
│   └── tmp/
├── tests/
│   ├── bootstrap.php
│   ├── Unit/
│   └── Integration/
└── vendor/                    Composer, pouze lokálně, necommitovat
```

Struktura odpovídá aktuální implementaci; detaily jsou v `docs/doc.html`.

## Jak začít

1. Přečti [`implementacni-plan.md`](implementacni-plan.md) a [`AGENTS.md`](AGENTS.md).
2. Pracuj v krátké větvi `feat/<issue>-<nazev>` nebo `fix/...`, otevři PR a nech ho reviewovat druhým.

### Lokální spuštění

```bash
mysql -u root -p -e "CREATE DATABASE iis CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci; CREATE DATABASE iis_test CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;"
mysql -u root -p iis < database/schema.sql
mysql -u root -p iis < database/seed.sql
mysql -u root -p iis_test < database/schema.sql
mysql -u root -p iis_test < database/seed.sql

cp config/config.example.php config/config.php   # doplnit db.password a app.url
php -S 127.0.0.1:8000 -t public public/router.php
```

### Testy a kontrola

```bash
composer install
IIS_DB_PASSWORD=*** vendor/bin/phpunit
PHP_BIN=php ./bin/check.sh
```

`IIS_DB_PASSWORD` musí odpovídat `db.password` v `config/config.php`.

## Pravidla spolupráce

- `main` je vždy funkční a nasaditelná větev; do `main` se nepushuje přímo.
- Jeden soubor má jednoho vlastníka. Změnu v cizí oblasti řeš issue nebo PR, ne přepisem.
- Do gitu nikdy nepatří `config/config.php`, logy, produkční uploady ani osobní údaje.
- UI je česky, kód a databáze anglicky. Žádné emoji, anglické placeholdery ani lorem ipsum.
- Bez nové závislosti, ORM, frameworku nebo změny schématu bez dohody obou.
- Každá změna routy, UC nebo schématu znamená aktualizaci mapování v `docs/doc.html`.
- Každý kód musíme umět vysvětlit. AI je nástroj, ne omluva.

## Milníky

| Milník | Datum | Výstup |
|---|---|---|
| M0 | 2. 11. 2026 | schéma v1, skeleton, deploy spike, issues |
| M1 | 8. 11. 2026 | auth, role, katalog, vytvoření události |
| M2 | 15. 11. 2026 | všechny P0 toky funkční lokálně |
| Feature freeze | 16. 11. 2026 | jen opravy a dokumentace |
| M3 | 18. 11. 2026 | release candidate, QA, dokumentace, video |
| M4 | 21. 11. 2026 | odevzdání v IS |
| Deadline | 22. 11. 2026 23:59 | poslední možnost odevzdání |
| Provoz | do 13. 12. 2026 | aplikace musí zůstat dostupná |

## Nejbližší krok

- Nasadit aplikaci na Evu a doplnit veřejnou URL do `docs/doc.html`.
- Nahrát 5minutové video a vložit odkaz do `docs/doc.html`.
- Provést finální smoke test z čistého prohlížeče a připravit odevzdávací zip bez `.git`, `vendor/`, `config/config.php` a logů.
