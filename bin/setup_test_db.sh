#!/usr/bin/env bash
# Připraví databáze `iis` a `iis_test` a uživatele `iis` pro lokální testy i CI.
#
#   bin/setup_test_db.sh <HESLO>           # lokálně, jako aktuální uživatel
#   bin/setup_test_db.sh <HESLO> mysql     # CI/remote, jako root přes TCP
set -euo pipefail

cd "$(dirname "$0")/.."

if [ $# -lt 1 ]; then
    echo "Použití: bin/setup_test_db.sh <HESLO> [mysql]" >&2
    exit 1
fi

PASSWORD="$1"
MODE="${2:-local}"

if [ "$MODE" = "mysql" ]; then
    MYSQL=(mysql --host=127.0.0.1 --protocol=TCP --user=root)
else
    MYSQL=(mysql)
fi

"${MYSQL[@]}" <<SQL
DROP DATABASE IF EXISTS iis;
DROP DATABASE IF EXISTS iis_test;
CREATE DATABASE iis CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
CREATE DATABASE iis_test CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
CREATE USER IF NOT EXISTS 'iis'@'localhost' IDENTIFIED BY '${PASSWORD}';
CREATE USER IF NOT EXISTS 'iis'@'%' IDENTIFIED BY '${PASSWORD}';
ALTER USER 'iis'@'localhost' IDENTIFIED BY '${PASSWORD}';
ALTER USER 'iis'@'%' IDENTIFIED BY '${PASSWORD}';
GRANT ALL PRIVILEGES ON iis.* TO 'iis'@'localhost';
GRANT ALL PRIVILEGES ON iis.* TO 'iis'@'%';
GRANT ALL PRIVILEGES ON iis_test.* TO 'iis'@'localhost';
GRANT ALL PRIVILEGES ON iis_test.* TO 'iis'@'%';
FLUSH PRIVILEGES;
SQL

mysql --host=127.0.0.1 --protocol=TCP --user=iis --password="${PASSWORD}" iis < database/schema.sql
mysql --host=127.0.0.1 --protocol=TCP --user=iis --password="${PASSWORD}" iis < database/seed.sql
mysql --host=127.0.0.1 --protocol=TCP --user=iis --password="${PASSWORD}" iis_test < database/schema.sql
mysql --host=127.0.0.1 --protocol=TCP --user=iis --password="${PASSWORD}" iis_test < database/seed.sql

echo "Databáze iis a iis_test jsou připravené."
