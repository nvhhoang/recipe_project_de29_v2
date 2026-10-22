#!/bin/bash
set -eu

# The exporter account is read-only and receives its password from the local .env file.
# Use alphanumeric passwords as recommended in README.md to avoid SQL quoting issues.
: "${MYSQL_ROOT_PASSWORD:?MYSQL_ROOT_PASSWORD is required}"
: "${MYSQLD_EXPORTER_PASSWORD:?MYSQLD_EXPORTER_PASSWORD is required}"
mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" <<SQL
CREATE USER IF NOT EXISTS 'metrics_exporter'@'%' IDENTIFIED BY '${MYSQLD_EXPORTER_PASSWORD}' WITH MAX_USER_CONNECTIONS 3;
ALTER USER 'metrics_exporter'@'%' IDENTIFIED BY '${MYSQLD_EXPORTER_PASSWORD}' WITH MAX_USER_CONNECTIONS 3;
GRANT PROCESS, REPLICATION CLIENT, SELECT ON *.* TO 'metrics_exporter'@'%';
FLUSH PRIVILEGES;
SQL
