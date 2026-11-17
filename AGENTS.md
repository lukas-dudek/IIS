# AGENTS.md

Tento soubor je závazný pro AI asistenty i pro nás dva. Platí pro celý repozitář. Pokud je instrukce v rozporu se zadáním (`zadani.md`, `spec-zadani.md`), platí zadání. Pokud je v rozporu s `implementacni-plan.md`, platí plán, dokud ho vědomě nezměníme.

## 1. Než začneš

1. Přečti `zadani.md`, `spec-zadani.md` a příslušnou část `implementacni-plan.md`.
2. Podívej se na existující soubory, routy, helpery a styl. Nezakládej druhý vzor vedle existujícího.
3. Zjisti, kdo je vlastník oblasti (kapitola 4). Do cizích souborů nezasahuj bez domluvy.
4. Pokud zadání nebo kontrakt chybí, zastav se a napiš, co přesně chybí. Nedomýšlej sloupce, routy ani práva.

## 2. Co je projekt

Informační systém pro správu událostí: uživatelé zakládají akce, navrhují místa a kategorie, moderátoři je schvalují, účastníci se registrují, platí vstupné a po akci hodnotí a komentují. Administrátor spravuje uživatele a má práva moderátora.

Aplikace musí být použitelná, bezpečná, konzistentní a obhajitelná. Nestačí, že něco „nějak funguje“ v demo scénáři.

## 3. Pevná rozhodnutí

- Větev `laravel` (aktuální implementace): PHP 8.1, **Laravel 10**, Eloquent, migrace, Blade,
  MySQL/MariaDB, Apache, Bootstrap 5.3 lokálně, vanilla JS. Rozhodnutí o frameworku je vědomá změna
  původního plánu, zadání framework výslovně připouští; odchylky shrnuje `docs/laravel-migrace.md`.
- Větev `feat/loop-p0` obsahuje původní vlastní MVC (PDO, `config/routes.php`, `database/schema.sql`).
  Obě větve se nemíchají; pravidla níže platí pro obě, rozdíly v nástrojích jsou popsané u konkrétních bodů.
- Žádný build step a žádné CDN. Bootstrap je lokální soubor. Na větvi `laravel` je jediná runtime
  závislost Composer (framework) a `vendor/` se do odevzdávaného archivu nedává.
- Kód anglicky, UI česky. Tabulky a sloupce anglicky.
- Časy v DB UTC, zobrazení `Europe/Prague`. Peníze `DECIMAL(10,2)`, v UI `Kč`.
- SQLite, MongoDB, React, Vue a podobné změny stacku jsou zakázané bez výslovného souhlasu obou.
- Zdroj pravdy pro schéma jsou migrace v `database/migrations/` (na větvi `feat/loop-p0` soubor
  `database/schema.sql`); zdroj pravdy pro routy je `routes/web.php` (na `feat/loop-p0`
  `config/routes.php`).

## 4. Struktura a vlastnictví

Větev `laravel`:

```text
app/Http/Controllers/   tenké controllery
app/Http/Middleware/    session a role middleware
app/Http/Requests/      validace formulářů
app/Models/             Eloquent modely
app/Policies/           serverová autorizace vlastnictví
app/Repositories/       vyhledávání a kalendář
app/Services/           transakce a business pravidla
app/Support/            datum, peníze, slug, ICS, upload
resources/views/        Blade layout, partials, sekce
routes/web.php          routy
database/migrations/    schéma
database/seeders/       ukázková data
public/                 document root, assets
lang/cs/                české validace a popisky
tests/Unit|Feature/     PHPUnit
docs/                   doc.html, laravel-migrace.md, diagramy
```

Větev `feat/loop-p0` (původní MVC):

```text
app/Core/            jádro: router, request, response, DB, session, auth, CSRF, validace
app/Controllers/     tenké controllery
app/Services/        transakce a business pravidla
app/Repositories/    explicitní SQL a mapování
app/Views/           šablony, layout, partials
app/Support/         pomocné funkce (datum, slug, ICS)
config/              konfigurace a routy
database/            schema.sql, seed.sql
public/              document root, assets, uploads
storage/             logy a dočasné soubory mimo veřejný přístup
tests/               unit a integrační testy
docs/                doc.html, diagramy, obhajoba
```

| Oblast | Vlastník | Recenzent |
|---|---|---|
| jádro, config, bin, CI | Lukáš (A) | Honzík (B) |
| auth, profil, admin uživatelů | A | B |
| místa, kategorie, jejich moderace | A | B |
| události, vstupenky, hledání | B | A |
| registrace, platby, kalendář, komentáře, hodnocení | B | A |
| moderace komentářů | A | B |
| layout, základní CSS | A | B |
| seed a demo data | B | A |
| schéma | B návrh, A review | oba |
| dokumentace a video | A koordinuje, B dodává své moduly | oba |

Shared soubory (`config/routes.php`, `database/schema.sql`, `public/assets/css/app.css`, `docs/doc.html`) měň po malých částech a v PR vysvětli proč. Konflikt řeší vlastník, u schématu oba.

## 5. Příkazy

Lokální vývoj na větvi `laravel`:

```bash
composer install
php artisan migrate --seed
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8000
vendor/bin/phpunit
find app database routes tests -name '*.php' -print0 | xargs -0 -n1 php -l
docker compose up --build        # volitelně celý stack
```

Lokální vývoj na větvi `feat/loop-p0`:

```bash
php -S 127.0.0.1:8000 -t public public/router.php
mysql -u root -p iis < database/schema.sql
mysql -u root -p iis < database/seed.sql
composer install                 # jen lokálně, dev závislosti
vendor/bin/phpunit
find app public config tests -name '*.php' -print0 | xargs -0 -n1 php -l
bin/check.sh
```

Deploy na Evu nikdy nespouštěj sám bez výslovného pokynu; může přepsat běžící aplikaci. Nikdy nespouštěj `seed.sql` na produkci opakovaně.

## 6. Doménová pravda

Nevymýšlej nové stavy, sloupce ani role. Platí:

- Role: `admin`, `moderator`, `user`; host není řádek v DB. Správce události je `events.manager_id`, ne globální role.
- Událost: `draft`, `pending`, `approved`, `rejected`, `cancelled`. `finished` neexistuje, odvozuje se z `ends_at`.
- Místo a kategorie: `pending`, `approved`, `rejected`.
- Registrace: `pending` (čeká na platbu), `confirmed`, `cancelled`.
- `events.capacity = NULL` znamená neomezeno. Do kapacity se počítají `pending` i `confirmed`, ne `cancelled`.
- Registrace ukládá snapshot ceny. Změna ceny typu vstupenky nemění staré registrace.
- Typ vstupenky s registracemi nelze smazat. Událost s registracemi nelze smazat, jen zrušit.
- Hodnotit a komentovat může jen `confirmed` účastník po `ends_at`. Jeden uživatel má na událost jedno hodnocení a jeden komentář.
- Kategorie: maximální hloubka 4, žádný cyklus, rodič musí být `approved`.
- Použité místo nebo kategorii nelze smazat. Uživatele s referencemi lze jen deaktivovat.
- Poslední aktivní admin nesmí být deaktivován, smazán ani zbaven role. Admin nesmí deaktivovat sám sebe.
- Podstatná změna schválené události (název, čas, místo, kategorie, kapacita, vstupenky) vrací událost do `pending`. Popis a fotka ne.
- Všechny změny stavů a kapacit jdou přes service s transakcí, nikdy přímým zápisem z formuláře.

## 7. Konvence kódu

- `declare(strict_types=1);` v každém PHP souboru.
- PSR-12, 4 mezery, krátké metody, časné návraty, žádné zanořování do hloubky.
- Typované parametry, návratové typy a properties. Žádné `mixed`, pokud to není nutné.
- Názvy tříd `PascalCase`, metody `camelCase`, DB `snake_case`.
- Controller je tenký. SQL patří do repository, transakce a pravidla do service.
- Žádný generický `BaseRepository`, `AbstractManager` ani rozhraní s jedinou implementací.
- Komentář jen tam, kde vysvětluje „proč“ nebo netriviální rozhodnutí. Žádné komentáře typu `// Načtení dat`.
- Žádné dekorativní hlavičky souborů, autorství, datumy ani značky nástrojů.
- Žádný zakomentovaný kód, `TODO`, `FIXME`, `var_dump`, `print_r`, `console.log`, `die()` pro uživatelské chyby.
- Nepřepisuj formátování nesouvisejících souborů. Nepřesouvej soubory bez důvodu.
- Router: `{id}` matchuje jen `\d+` a kladné číslo, `{slug}` jen `[a-z0-9-]+`; statické cesty se registrují před parametrickými, jinak vzniká kolize. Na větvi `laravel` totéž platí pro pořadí v `routes/web.php` a vázání `{event:slug}` / `{event:id}`.
- Preferuj jednoduché a vysvětlitelné řešení před „chytrým“.

## 8. Databáze a transakce

- Vždy prepared statements nebo query builder s vázanými parametry. Nikdy neinterpoluj vstup do SQL, ani do `LIKE`, ani do `ORDER BY`.
- Povolené hodnoty řazení a enumů whitelistuj v PHP.
- Vícekrokové operace a vše, co mění kapacitu, dělej v transakci.
- Registrace: `SELECT ... FOR UPDATE` na události (na větvi `laravel` `lockForUpdate()`), kontrola stavu, času, duplicity, kapacity události a typu, snapshot ceny, insert/update, commit.
- Chyby mapuj na srozumitelné české hlášky; SQL výjimky nepropouštěj k uživateli.
- Nepřidávej sloupec, tabulku ani index bez dohody a bez aktualizace schématu (migrace, respektive `schema.sql`), seedů, ER diagramu a dokumentace.
- Nepoužívej `NOW()` v aplikaci jako zdroj pravdy pro business čas; čas počítej v PHP v UTC.
- Databázové heslo nikdy do gitu. `config/config.php` je ignorovaný, verzuje se jen `config.example.php`.

## 9. Bezpečnost

Toto nejsou doporučení, ale podmínky mergování:

- Hesla přes `password_hash`/`password_verify`. Nikdy `md5`, `sha1`, plaintext ani vlastní hash.
- Session po přihlášení regeneruj, cookie `HttpOnly`, `SameSite=Lax`, `Secure` na HTTPS, timeout 30 minut.
- Session drží `user_id` a `auth_version`; každý request ověří aktivitu a shodu verze. Deaktivace, změna role a změna hesla `auth_version` zvyšují. Na větvi `laravel` to řeší middleware `EnsureSessionIsCurrent`.
- Všechny POST formuláře mají CSRF token a server ho ověřuje přes `hash_equals`.
- Každý přístup k záznamu kontroluje roli a vlastnictví na serveru. Skrytí tlačítka není autorizace.
- Všechny výstupy escapuj helperem `e()`. Žádné `echo $_GET`, `echo $model->title` bez escapování.
- Žádné mass assignment z `$_POST`; mapuj povolená pole ručně.
- Upload: `finfo` i `getimagesize`, povolené JPEG/PNG/WebP, max 2 MB, náhodný název, žádné SVG, PHP v uploads vypnuté.
- `return` URL jen lokální cesta začínající `/`, nikdy `//`.
- Do logu nikdy hesla, tokeny, celé formuláře ani osobní údaje.
- Bezpečnostní hlavičky a CSP podle plánu; pokud CSP něco blokuje, oprav kód, ne hlavičku.
- Neexistující a cizí záznam se chová jako 404/403, ne jako 500.

## 10. Formuláře a UI

- Povinná pole označ `*` a legendou „* povinné pole“. Nepovinná pole neoznačuj jako povinná; kde by to uživatel nečekal, napiš „(nepovinné)“.
- Při chybě zachovej všechny zadané hodnoty kromě hesel. Chyby u polí i v souhrnu, fokus na první chybu.
- Formát naznač pod polem: `dd. mm. rrrr hh:mm`, `123 45`, `150 nebo 150,50`.
- Cizí klíče vybírej ze seznamu. Nikdy nenuť uživatele opisovat ID.
- Datum události předvyplň rozumně (nejbližší celá hodina, konec +2 h); filtry nepředvyplňuj.
- Po úspěchu redirect + flash (PRG). Refresh nesmí odeslat formulář znovu.
- Odkazy a assety v šablonách vždy přes `url()`; žádné ruční skládání cest, aby aplikace fungovala v kořeni i v `/IIS`.
- Texty česky, konkrétní, bez marketingu, bez emoji, bez „Welcome“, bez lorem ipsum.
- Tlačítka infinitivem: „Uložit“, „Odeslat ke schválení“, „Zrušit registraci“.
- Prázdný stav musí nabídnout další krok, ne jen prázdnou tabulku.
- Responzivita 360/768/1280 px, Chrome a Firefox. Klávesnicí musí jít projít celý formulář.

## 11. Co nechceme

- Vymyšlené funkce, obrazovky nebo sloupce „pro jistotu“.
- Generické abstrakce, které nikdo nepoužívá.
- Anglické texty v UI, emoji, dekorativní komentáře, přehnané nadpisy a sekce v kódu.
- `alert()` pro validaci, klientskou validaci jako jedinou, tlačítka bez akce.
- Testy, které jen opisují implementaci, testují gettery nebo tvrdí, že něco prošlo, aniž by to běželo.
- Commity typu „AI generated“, „initial implementation“, „final“.
- Dokumentaci, která popisuje neexistující funkce nebo technologie.
- Jakékoli obcházení bezpečnosti „aby to prošlo“.
- Nové závislosti, build step, CDN, ORM, framework nebo změnu schématu bez dohody.

## 12. Git a PR

- `main` je vždy funkční. Práce v `feat/<issue>-<nazev>`, `fix/...`, `docs/...`.
- Malé commity, český rozkazovací způsob, konkrétní popis. Žádné hromadné commity.
- PR má: co, proč, jak testováno, rizika, screenshot u UI, checklist (práva, CSRF, validace, staré hodnoty, responzivita, dokumentace).
- Autor nesmí schválit sám sebe. Force push do `main` je zakázaný.
- Do gitu nikdy nepatří `config/config.php`, logy, produkční uploady, hesla, dumpy s osobními daty.

## 13. Testování

- Před tvrzením „hotovo“ spusť aplikaci a projdi scénář. Unit testy samotné nestačí.
- U rizikových změn (auth, práva, kapacita, platby, upload) projdi i negativní případ: cizí záznam, plná kapacita, duplicita, neplatný vstup, chybějící CSRF.
- Nespouštěj testy, které potřebují produkční DB nebo produkční data.
- Piš testy business pravidel, ne frameworku. Testuj hranice, přechody stavů a chybové stavy.
- Pokud test nejde spustit, napiš to. Netvrď, že prošel.
- Před PR: `php -l` (na větvi `laravel` nad `app`, `database`, `routes`, `tests`), PHPUnit, ruční scénář, kontrola logu a konzole.

## 14. Dokumentace

- `docs/doc.html` se aktualizuje průběžně, ne na konci. Každá změna routy, UC nebo schématu znamená update mapovací tabulky.
- Dokumentace nesmí obsahovat hesla na veřejné stránce; testovací účty patří jen do odevzdávaného `doc.html`.
- Diagramy musí odpovídat kódu. Žádné vymyšlené technologie ani funkce.
- `docs/obhajoba.md` je pracovní dokument pro vysvětlení: tok requestu, session, CSRF, transakce kapacity, strom kategorií, práva, upload, ICS.
- Nepřidávej README ani dokumentaci mimo rozsah `implementacni-plan.md`; pokud jsou v plánu, piš věcně a krátce.
- Do odevzdávaného zipu nepatří cizí knihovny (`public/assets/vendor/`); jejich verze patří do dokumentace.

## 15. Práce s AI (pokyny pro agenta)

- Jsi asistent, ne autor rozhodnutí. Architekturu, schéma a business pravidla navrhuje a schvaluje člověk.
- Drž se přesně zadaného kontraktu. Když něco chybí, zastav a napiš to; nevymýšlej náhrady.
- Neměň soubory mimo zadání. Neopravuj „při tom“ nesouvisející věci.
- Nepřidávej závislosti, soubory, routy, sloupce ani testovací frameworky bez výslovného pokynu.
- Výstup musí být celý soubor nebo přesný diff; žádné „...“ v kódu, žádné placeholdery.
- Před tvrzením, že něco funguje, spusť, co lze spustit. Do odpovědi napiš přesně, co jsi spustil a co ne.
- Nevysvětluj základní věci a nepiš marketing. Ke kódu piš jen to, co je potřeba.
- Pokud si nejsi jistý právem, stavem nebo transakcí, radši se zeptej. Špatná domněnka je horší než dotaz.
- Nikdy neobcházej bezpečnostní pravidla, ani „dočasně“, ani v testovacím kódu.
- Nepiš kód, který by jeho autor neuměl vysvětlit. Jednoduché řešení je správné řešení.
- Nepřidávej do kódu, commitů ani dokumentace značky AI nástrojů, automatické hlavičky ani podpisy.

## 16. Když něco nesedí

- Rozpor zadání vs. plán: vyhrává zadání, plán se opraví.
- Rozpor plán vs. kód: nejdřív zjisti, co je skutečně nasazené; pak oprav jeden z nich, ne oba naslepo.
- Chybějící sloupec/routa: zastav, navrhni změnu a počkej na schválení.
- Cizí soubor: otevři issue/PR, ne přepis bez domluvy.
- Nejistota u peněz, kapacity, práv nebo osobních údajů: konzervativní řešení a review druhým.

## 17. Definition of Done

Změna je hotová, když:

- [ ] Je dostupná z navigace nebo logického místa a funguje pro správné role.
- [ ] Server validuje vstupy, zachovává hodnoty a zobrazuje chyby česky.
- [ ] POST má CSRF, správnou metodu a PRG po úspěchu.
- [ ] Autorizace je vynucená na serveru, včetně vlastnictví.
- [ ] Vícekrokové operace jsou transakční a nemohou rozbít konzistenci.
- [ ] Negativní scénář je projdený, ne jen šťastná cesta.
- [ ] UI je responzivní, bez chyb v konzoli, bez zbytečných anglických textů.
- [ ] `php -l`, unit testy a ruční scénář prošly; je jasné, co běželo.
- [ ] Dokumentace a mapování UC jsou aktuální.
- [ ] Autor umí každý řádek vysvětlit.

## 18. Rychlý checklist před commitem

1. Nepřidal jsem secrety, logy ani cizí data?
2. Nezměnil jsem schéma nebo routy bez dohody?
3. Je SQL prepared a výstup escapovaný?
4. Má každý POST CSRF a správnou kontrolu práv?
5. Zachová formulář hodnoty při chybě?
6. Prošel jsem scénář v prohlížeči i negativní případ?
7. Je commit malý a popis konkrétní?
8. Umím vysvětlit, co jsem změnil a proč?
