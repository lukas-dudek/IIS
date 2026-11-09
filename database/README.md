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
