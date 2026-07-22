#!/usr/bin/env bash
# Idempotent writer bootstrap for local Docker replication + ProxySQL.
# DEV-ONLY defaults — override via compose environment; never commit production secrets.
set -euo pipefail

mysql_cmd=(mysql -uroot -p"${MYSQL_ROOT_PASSWORD}")

APP_DB="${MYSQL_DATABASE:-inventory_saas}"
APP_USER="${MYSQL_USER:-inventory}"
APP_PASSWORD="${MYSQL_PASSWORD:-inventory_dev_only_change_me}"
REPL_USER="${MYSQL_REPLICATION_USER:-repl}"
REPL_PASSWORD="${MYSQL_REPLICATION_PASSWORD:-repl_dev_only_change_me}"
MONITOR_USER="${PROXYSQL_MONITOR_USER:-monitor}"
MONITOR_PASSWORD="${PROXYSQL_MONITOR_PASSWORD:-monitor_dev_only_change_me}"

"${mysql_cmd[@]}" <<SQL
CREATE DATABASE IF NOT EXISTS \`${APP_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS '${APP_USER}'@'%' IDENTIFIED BY '${APP_PASSWORD}';
ALTER USER '${APP_USER}'@'%' IDENTIFIED BY '${APP_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${APP_DB}\`.* TO '${APP_USER}'@'%';

CREATE USER IF NOT EXISTS '${REPL_USER}'@'%' IDENTIFIED BY '${REPL_PASSWORD}';
ALTER USER '${REPL_USER}'@'%' IDENTIFIED BY '${REPL_PASSWORD}';
GRANT REPLICATION SLAVE ON *.* TO '${REPL_USER}'@'%';

CREATE USER IF NOT EXISTS '${MONITOR_USER}'@'%' IDENTIFIED BY '${MONITOR_PASSWORD}';
ALTER USER '${MONITOR_USER}'@'%' IDENTIFIED BY '${MONITOR_PASSWORD}';
GRANT REPLICATION CLIENT ON *.* TO '${MONITOR_USER}'@'%';
GRANT SELECT ON performance_schema.* TO '${MONITOR_USER}'@'%';

FLUSH PRIVILEGES;
SQL

echo "Writer bootstrap complete for database ${APP_DB}."
