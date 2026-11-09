# Databáze

Zdroj pravdy schématu je `schema.sql`, ukázková data jsou v `seed.sql`.

## Lokální import

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS iis CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;"
mysql -u root iis < database/schema.sql
mysql -u root iis < database/seed.sql
```

Lokální přihlašovací údaje patří do `config/config.php` (gitignored), nikdy do gitu.
Testovací databáze se jmenuje `iis_test` a importuje se stejným schématem.

## Pravidla

- `seed.sql` je určen pro čistou databázi. Opakované spuštění na produkci přepíše data.
- Schéma se nemění bez dohody obou vlastníků; každá změna znamená update `schema.sql`,
  `seed.sql`, ER diagramu a dokumentace.
- Časy jsou v UTC, peníze `DECIMAL(10,2)`, všechny tabulky InnoDB a `utf8mb4`.

## Demo data

`seed.sql` obsahuje data podle Přílohy A implementačního plánu: 10 uživatelů,
11 míst, 12 kategorií, 14 událostí, 20 typů vstupenek, 40 registrací,
15 komentářů a 20 hodnocení. Termíny pokrývají říjen–prosinec 2026.
Testovací účty a hesla patří pouze do odevzdávané `docs/doc.html`, nikdy do
veřejně dostupné dokumentace ani README.
