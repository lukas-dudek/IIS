# Odchylky Laravel implementace

Tato větev (`laravel`) nahrazuje původní vlastní MVC rámec Laravel 10. Dokument shrnuje, co se změnilo
oproti rozhodnutím v [`AGENTS.md`](../AGENTS.md) a [`implementacni-plan.md`](../implementacni-plan.md), aby
bylo jasné, co platí.

## Stack

| Oblast | Původní rozhodnutí | Nyní |
|---|---|---|
| framework | vlastní malé MVC | Laravel 10 (poslední verze podporující PHP 8.1) |
| routing | `config/routes.php` | `routes/web.php` |
| DB přístup | PDO + repositories | Eloquent + migrace, `SELECT ... FOR UPDATE` přes `lockForUpdate()` |
| šablony | vlastní `View` + PHP šablony | Blade, layout a partials |
| frontend | Bootstrap 5.3 lokálně | beze změny, stále lokální soubory bez CDN a bez build stepu |
| testy | PHPUnit 10 nad vlastním bootstrapem | PHPUnit 10 nad Laravel testovacím základem |
| autentizace | vlastní `Auth` nad session | Laravel Auth s vlastním middleware pro `auth_version` |

Zadání (`zadani.md`, `spec-zadani.md`) zůstává nadřazené. Doporučení v zadání Laravel výslovně
připouští, takže se nejedná o rozpor se zadáním, pouze o změnu dřívějšího týmového rozhodnutí.

## Co zůstalo stejné

- Doménový model: tabulky `users`, `venues`, `categories`, `events`, `ticket_types`, `registrations`,
  `comments`, `ratings`, `login_attempts` se shodnými sloupci a stavy.
- Business pravidla: stavy událostí, hloubka kategorií, snapshot ceny, kapacita, práva hodnocení,
  ochrana posledního administrátora.
- České cesty rout (`/udalosti`, `/moderace`, `/admin/uzivatele`, …), anglické názvy rout a kód.
- Časy v UTC, zobrazení v `Europe/Prague`, peníze `DECIMAL(10,2)` a formát `Kč`.

## Co se změnilo v pravidlech pro vývoj

- `AGENTS.md` §3 už Laravel nezakazuje; doplněna poznámka o této větvi.
- `implementacni-plan.md` §2 popisuje Laravel jako zvolený stack.
- Middleware `auth_version` nahrazuje původní vlastní session wrapper; chování je stejné
  (deaktivace, změna role a změna hesla ukončí existující relaci).
- Politiky (`app/Policies`) nesou autorizaci vlastnictví, controllery ji nevynucují ručně.

## Co je potřeba udělat při návratu na vlastní MVC

Neplánuje se. Pokud by k tomu došlo, musí se znovu ověřit všechna pravidla výše, protože se
opírají o Eloquent relace a transakce.
