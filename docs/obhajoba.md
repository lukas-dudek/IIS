# Obhajoba klíčových částí projektu IIS

Pracovní dokument pro vysvětlení rozhodnutí, toků a bezpečnostních mechanismů.
Odpovídá implementaci v `app/`, `database/schema.sql` a `config/routes.php`.

## 1. Toku požadavku

1. `public/index.php` je jediný dynamický vstup. `.htaccess` přepíše cesty, které
   neexistují jako soubor, na `index.php`. Lokálně slouží `public/router.php`
   pro `php -S` a vrací `false` pro statické soubory.
2. `app/bootstrap.php` načte:
   - autoloader `App\*`,
   - helpery `app/Support/*.php`,
   - konfiguraci z `config/config.php`,
   - session (timeout 1800 s, `SameSite=Lax`, `HttpOnly`, `Secure` na HTTPS),
   - `Database`,
   - `Auth`, `AuthService`, `UserService`, `Authorization`,
   - `View`, `Logger`, `Upload`,
   - repozitáře a service,
   - `Request::fromGlobals()`.
3. `Router` porovná metodu a cestu z `config/routes.php`. Statické cesty jsou
   registrovány před parametrickými, `{id}` odpovídá `\d+`, `{slug}` odpovídá
   `[a-z0-9-]+`. Neodpovídající cesta → 404.
4. Middleware `auth` ověří aktivní uživatele a shodu `auth_version`. `role:moderator`
   a `role:admin` ověří roli (admin prochází vždy). POST routy navíc vyžadují
   CSRF token.
5. Controller je tenký: validuje vstup, volá service, připravuje data pro view
   nebo redirect.
6. Service drží business pravidla a transakce. Repository provádí explicitní
   prepared SQL a mapování řádků na pole.
7. `Response::send()` pošle hlavičky a tělo. Hlavičky jsou v `app/Core/Response.php`:
   - `X-Content-Type-Options: nosniff`
   - `X-Frame-Options: SAMEORIGIN`
   - `Referrer-Policy: strict-origin-when-cross-origin`
   - `Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'`

## 2. Session a auth

- `Session::start()` nastaví `use_strict_mode`, `use_only_cookies`,
  `cookie_httponly`, `SameSite=Lax` a `Secure` podle konfigurace/HTTPS.
- Po přihlášení `Auth::login()` zavolá `Session::regenerate()` a uloží
  `user_id` a `auth_version` do session.
- Každá request `Auth::user()` ověří:
  - `user_id` a `auth_version` jsou čísla,
  - uživatel existuje a je aktivní,
  - `auth_version` v DB se shoduje s verzí v session.
- Deaktivace uživatele, změna role a změna hesla zvýší `auth_version` v DB,
  čímž se invalidují všechny jeho relace.
- Timeout 30 minut: `Session::start()` porovná `_last_activity`; po vypršení
  se session vymaže a uživatel vidí flash „Relace vypršela.“

## 3. CSRF

- Token je náhodný řetězec `bin2hex(random_bytes(32))` uložený v session.
- `Csrf::field()` vloží skryté pole `_csrf` do každého POST formuláře.
- Server ověří token přes `hash_equals($expected, $token)`; porovnání je
  constant-time.
- Po přihlášení a odhlášení `Csrf::rotate()` vymaže starý token.
- Pokud token neexistuje nebo nesedí, request se zamítne 403.

## 4. Kapacita a registrace

Všechny operace, které mění kapacitu nebo platbu, jsou v `RegistrationService`
transakční a používají `SELECT … FOR UPDATE` na řádku události:

1. Získání události a zamykání jejího řádku.
2. Ověření, že událost je `approved` a nezačala.
3. Ověření, že uživatel na událost nemá aktivní registraci.
4. Ověření kapacity události (`capacity` NULL = neomezeno) – počítají se
   `pending` i `confirmed`.
5. Ověření kapacity typu vstupenky.
6. Vytvoření registrace s `price` snapshotem z typu vstupenky.
7. Pokud je cena > 0, vygeneruje se `variable_symbol` (unikátní, 10 míst).
8. Commit.

Uživatel může po platbě kliknout „Uhradil jsem“ – nastaví se `payment_claimed_at`,
stav zůstává `pending`. Správce pak může potvrdit (`confirmed`) nebo odmítnout
(`cancelled`). Potvrzení uloží `decided_by` a `decided_at`.

Zrušení registrace uvolní místo (stav `cancelled` se nepočítá do kapacity).
Opětovná registrace použije stejný řádek; historie se nezachovává.

## 5. Strom kategorií

- Hloubka stromu je maximálně 4.
- Žádný cyklus: přesun kategorie pod sebe nebo pod svého potomka je zakázaný.
- Schválení vyžaduje, aby rodič byl `approved` (nebo neexistoval).
- KATEGORIE S PODKATEGORIÍ NEBO UDÁLOSTÍ NELZE SMAZAT.
- Slug se generuje z názvu, kolize se řeší příponou `-2`, `-3`…

## 6. Práva a autorizace

- Middleware `auth`, `role:moderator`, `role:admin` řeší hrubá práva.
- Jemná práva (vlastnictví) řeší `Authorization` a service:
  - `canManageEvent($event)`: admin nebo `event.manager_id === auth.id()`,
  - návrhy míst/kategorií: autor návrhu nebo moderátor/admin,
  - komentáře: autor může upravit/smazat, moderátor/admin skrýt/zobrazit.
- Vlastník události si ji neschválí sám; schvaluje moderátor nebo admin.
- Neexistující nebo cizí záznam se chová jako 404/403, ne jako 500.
- Skrytí tlačítka v UI není autorizace – všechny kontroly jsou na serveru.

## 7. Upload

`app/Core/Upload.php`:
- `finfo` určí MIME typ; povoleny jsou pouze `image/jpeg`, `image/png`, `image/webp`.
- `getimagesize()` ověří, že soubor je platný obrázek.
- Maximální velikost 2 MB.
- Název souboru je náhodný `bin2hex(random_bytes(16))` s příponou podle MIME.
- Cíl je `public/uploads/<folder>/…`, složka musí odpovídat `[a-z0-9_-]+`.
- V `public/uploads` je PHP vypnuté (podle plánu při nasazení na Evu).

## 8. ICS export

`app/Support/ics.php`:
- `UID` je stabilní: `event-<id>@<host>` (host z konfigurace, fallback `iis.local`).
- Časy jsou v UTC, formát `Ymd\THis\Z`.
- Popis a další texty escapují `\`, `;`, `,`, novinky.
- Řádky se lámalé dle RFC 5545 (max 75 bajtů, pokračování `space`).
- Výstup obsahuje `BEGIN:VCALENDAR`, `VERSION:2.0`, `PRODID`, `CALSCALE`,
  `METHOD:PUBLISH`, `X-WR-CALNAME`, `X-WR-TIMEZONE` a `VEVENT` s `SUMMARY`,
  `LOCATION`, `DESCRIPTION`, `URL`, `STATUS:CONFIRMED`.

## 9. Životní cyklus události

```
[*] → draft
draft → pending : odeslat ke schválení
pending → approved : schválit
pending → rejected : zamítnout s důvodem
rejected → pending : upravit a znovu odeslat
approved → pending : podstatná změna
approved → cancelled : zrušit s důvodem
pending → cancelled : zrušit s důvodem
cancelled → [*]
```

- Podstatná změna (název, čas, místo, kategorie, kapacita, vstupenky)
  vrací schválenou událost do `pending`. Popis a fotka se mění bez schvalování.
- Zrušení události přepne všechny aktivní registrace na `cancelled`.
- Událost se `finished` nenazývá; ukončení se odvozuje z `ends_at < now`.

## 10. Bezpečnostní testy

- Rate limit: 5 neúspěšných pokusů na username+IP do 10 minut.
- Po 5. neúspěšném pokusu se zamítne i správné heslo (odhlásí session).
- Deaktivovaný uživatel se nemůže přihlásit ani přes existující session
  (zvýšení `auth_version` invaliduje relace).
- Admin nesmí deaktivovat ani smazat sám sebe; poslední aktivní admin je
  chráněn před deaktivací, smazáním a změnou role.
- CSRF: POST bez tokenu nebo s špatným tokenem → 403.
- SQLi: všechny repository metody používají prepared statements; vstup do SQL
  se nikdy neinterpoluje.
- XSS: všechny výstupy escapují `e()`; CSP zakazuje inline skripty a vnější
  zdroje.
- Upload: soubor s PHP obsahem, špatným MIME nebo přes 2 MB je zamítnut.

## 11. Testy

- Unit: ICS, plural, Request, Validator.
- Integration: AuthService, UserService, VenueService, CategoryService,
  EventService, RegistrationService, FeedbackService, EventSearch,
  ModerationQueue, Calendar.
- Integration testy potřebují `iis_test` s `schema.sql` a `seed.sql`;
  `IIS_DB_PASSWORD` musí odpovídat `config/config.php`.
- Manuální E2E: viz `docs/doc.html` sekce „Manuální E2E scénáře“.

## 12. Nasazení

- Lokálně: `php -S 127.0.0.1:8000 -t public public/router.php`.
- Produkce (Eva): Apache + `mod_rewrite`, `mod_headers`; `~/iis` jako aplikace,
  `~/WWW -> iis/public` jako document root.
- `config/config.php` je generovaný z `config.example.php` a do gitu
  nepatří.
- `database/schema.sql` a `database/seed.sql` se importují ručně; seed se na
  produkci nespouští opakovaně.
- `public/assets/vendor/` s Bootstrap se nahrává, aby web nezávisel na CDN.
- `vendor/` (Composer) se do produkce nenahrává.
