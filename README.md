# IIS – systém pro správu událostí (Laravel)

Informační systém pro správu akcí: registrovaní uživatelé zakládají události, navrhují místa a kategorie,
moderátoři je schvalují, účastníci se registrují, platí vstupné a po ukončení akce hodnotí a komentují.
Semestrální projekt předmětu IIS, FIT VUT, varianta „Správa událostí“.

Tato větev obsahuje implementaci v **Laravelu 10** (PHP 8.1), která nahrazuje původní vlastní MVC.
Originální zadání a plán zůstávají v [`zadani.md`](zadani.md), [`spec-zadani.md`](spec-zadani.md)
a [`implementacni-plan.md`](implementacni-plan.md); implementační odchylky shrnuje
[`docs/laravel-migrace.md`](docs/laravel-migrace.md).

## Požadavky

| Nástroj | Verze | Poznámka |
|---|---|---|
| PHP | 8.1 a novější (testováno 8.1.34) | rozšíření `pdo_mysql`, `gd`, `mbstring`, `zip`, `openssl` |
| Composer | 2.x | jen pro instalaci závislostí, produkce `vendor/` obsahuje |
| MySQL / MariaDB | 5.7+ / 10.3+ (testováno 10.11) | `utf8mb4`, časová zóna UTC |
| Bootstrap | 5.3.3 | přiložen v `public/assets/vendor/`, žádný build step |

Node.js ani Vite nejsou potřeba – veškerý frontend je Blade + lokální Bootstrap, bez CDN.

## Lokální spuštění (PHP + MariaDB)

```bash
cp .env.example .env
composer install
php artisan key:generate

# databáze a uživatel
mysql -u root -p -e "CREATE DATABASE iis CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;"
mysql -u root -p -e "CREATE USER 'iis'@'localhost' IDENTIFIED BY 'iis_local_dev'; GRANT ALL ON iis.* TO 'iis'@'localhost';"

php artisan migrate --seed
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8000
```

Aplikace běží na <http://127.0.0.1:8000>. Přihlašovací údaje jsou v části
[testovací účty](#testovací-účty).

`php artisan serve` je určený jen pro vývoj. Pro Apache nastavte document root na `public/`
a povolte `mod_rewrite`; přiložený `public/.htaccess` řeší front controller i podadresář
(například `/IIS`).

## Spuštění přes Docker

```bash
docker compose up --build
```

- aplikace: <http://localhost:8080>
- MariaDB: `127.0.0.1:3307`, databáze `iis`, uživatel `iis`, heslo `iis_local_dev`

Kontejner `app` při startu zkopíruje `.env.example` (pokud `.env` chybí), vygeneruje `APP_KEY`,
spustí migrace, seedery (jen pokud databáze není naplněná) a cache artisan příkazů.
Proměnné `RUN_MIGRATIONS=0` a `RUN_SEEDER=0` tyto kroky vypnou; `APP_ENV=production`
seedery nespustí nikdy. Data MariaDB jsou v pojmenovaném volume `dbdata`.

## Testovací účty

Heslo u všech účtů je `heslo123`. Data vznikají seederem `database/seeders/DatabaseSeeder.php`.

| Uživatelské jméno | Role | Co lze vyzkoušet |
|---|---|---|
| `admin` | administrátor | správa uživatelů, moderace, vše ostatní |
| `moderator` | moderátor | schvalování událostí, míst a kategorií, moderace komentářů |
| `user` | uživatel, správce událostí | vlastní události, registrace, hodnocení |
| `jana`, `petr`, `eva`, `martin`, `lucie` | uživatelé | registrace, placení, komentáře, kalendář |
| `neaktivni` | deaktivovaný uživatel | ověření, že deaktivovaný účet se nepřihlásí |

## Co systém umí

- **Neregistrovaný uživatel** prochází schválené události, filtruje podle textu, kategorie (včetně
  podkategorií), města, data, ceny a dostupnosti, zobrazuje detaily míst a událostí.
- **Registrovaný uživatel** má osobní kalendář s exportem `.ics`, zakládá události (koncept nebo
  rovnou ke schválení), navrhuje nová místa a kategorie, registruje se na události a oznamuje úhradu.
- **Správce události** upravuje svou událost, odesílá ji ke schválení, potvrzuje platby účastníků,
  vidí obsazenost a ruší událost s dopadem na registrace.
- **Moderátor** schvaluje a zamítá události, místa a kategorie, spravuje katalog a skrývá komentáře.
- **Administrátor** navíc spravuje uživatele, role, aktivaci a reset hesel.

## Doménová pravidla (zkráceně)

- Role `admin` > `moderator` > `user`; správce události je `events.manager_id`, nikoli globální role.
- Událost: `draft` → `pending` → `approved` / `rejected`; zrušení z `draft`, `pending` i `approved`
  zruší aktivní registrace. „Ukončená“ se odvozuje z `ends_at`, stav `finished` neexistuje.
- Podstatná změna schválené události (název, čas, místo, kategorie, kapacita, vstupenky) vrací
  událost do `pending`; popis a fotka ne.
- Kategorie tvoří strom s maximální hloubkou 4, bez cyklů, rodič musí být `approved`.
- Registrace v transakci se zámkem `lockForUpdate()` na události: kontroluje stav, čas, duplicitu,
  kapacitu události i typu vstupenky, ukládá snapshot ceny. Do kapacity se počítají `pending`
  i `confirmed`, zrušená registrace místo uvolňuje. `capacity = NULL` znamená neomezeno.
- Hodnotit a komentovat může jen `confirmed` účastník po `ends_at`; jeden komentář a jedno
  hodnocení na uživatele a událost.
- Poslední aktivní administrátor nemůže být deaktivován, smazán ani zbaven role; admin nemůže
  deaktivovat sám sebe. Uživatel s referencemi se jen deaktivuje.

## Bezpečnost

- Laravel Auth, `password_hash` přes `Hash` (bcrypt), session timeout 30 minut (`SESSION_LIFETIME`).
- Session drží `auth_version`; middleware `EnsureSessionIsCurrent` odhlásí relaci při změně role,
  hesla nebo deaktivaci účtu.
- CSRF token u všech POST formulářů, serverové ověření.
- Autorizace v middleware (`moderator`, `admin`) a policies (`EventPolicy`, `CatalogPolicy`);
  vlastnictví záznamu se ověřuje vždy na serveru.
- Upload fotografií: `image` validace, MIME JPEG/PNG/WebP, `getimagesize`, náhodný název, max 2 MB.
- Časy v DB v UTC, zobrazení v `Europe/Prague`; peníze `DECIMAL(10,2)`, v UI `Kč`.
- Omezení pokusů o přihlášení (5 pokusů / 15 minut na účet nebo IP) v tabulce `login_attempts`.

## Testy

Testy používají samostatnou MySQL databázi `iis_test` (SQLite je podle zadání zakázaný).

```bash
mysql -u root -p -e "CREATE DATABASE iis_test CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;"
mysql -u root -p -e "GRANT ALL ON iis_test.* TO 'iis'@'localhost';"
vendor/bin/phpunit
```

Sada pokrývá autentizaci a session, životní cyklus události, registrace a kapacitu, katalog
a strom kategorií, komentáře a hodnocení, správu uživatelů, kalendář s exportem `.ics`
a vyhledávání. Negativní případy (cizí záznam, plná kapacita, neoprávněný přístup) jsou
součástí každé sady.

## Struktura projektu

```text
app/Http/Controllers/   tenké controllery
app/Http/Middleware/    auth session, role middleware
app/Http/Requests/      validace registrace
app/Models/             Eloquent modely a doménové pomocné metody
app/Policies/           serverová autorizace
app/Repositories/       vyhledávání událostí
app/Services/           business pravidla a transakce
app/Support/            datumy, peníze, slug, ICS, upload
database/migrations/    schéma databáze
database/seeders/       ukázková data pro všechny role
resources/views/        Blade šablony (layout, partials, sekce)
routes/web.php          všechny routy s českými cestami a anglickými názvy
tests/Unit|Feature/     PHPUnit testy
docs/doc.html           dokumentace k odevzdání
```

## Nasazení na server Eva

1. Nahrajte projekt mimo document root a nasměrujte document root do `public/`.
2. `composer install --no-dev --optimize-autoloader`
3. `cp .env.example .env`, nastavte `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` a údaje DB.
4. `php artisan key:generate`, `php artisan migrate --force`, `php artisan storage:link`.
5. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
6. Práva zápisu pro `storage/` a `bootstrap/cache/`; seedery na produkci nespouštějte.

Podrobný popis včetně mapování případů použití na kontrolery je v [`docs/doc.html`](docs/doc.html).
