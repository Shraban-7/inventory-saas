#!/usr/bin/env sh
# Render ProxySQL config so DB_MAX_CONNECTIONS is applied to mysql_users.max_connections.
# DEV-ONLY defaults — override via compose environment.
set -eu

TEMPLATE="${PROXYSQL_TEMPLATE:-/etc/proxysql/proxysql.cnf}"
RENDERED="${PROXYSQL_CONFIG:-/etc/proxysql.cnf}"

DB_USERNAME="${DB_USERNAME:-inventory}"
DB_PASSWORD="${DB_PASSWORD:-inventory_dev_only_change_me}"
DB_MAX_CONNECTIONS="${DB_MAX_CONNECTIONS:-50}"
PROXYSQL_MONITOR_USER="${PROXYSQL_MONITOR_USER:-monitor}"
PROXYSQL_MONITOR_PASSWORD="${PROXYSQL_MONITOR_PASSWORD:-monitor_dev_only_change_me}"

# Guard against non-numeric max_connections values.
case "${DB_MAX_CONNECTIONS}" in
    ''|*[!0-9]*)
        echo "DB_MAX_CONNECTIONS must be a positive integer, got: ${DB_MAX_CONNECTIONS}" >&2
        exit 1
        ;;
esac

sed \
    -e "s|__DB_USERNAME__|${DB_USERNAME}|g" \
    -e "s|__DB_PASSWORD__|${DB_PASSWORD}|g" \
    -e "s|__DB_MAX_CONNECTIONS__|${DB_MAX_CONNECTIONS}|g" \
    -e "s|__PROXYSQL_MONITOR_USER__|${PROXYSQL_MONITOR_USER}|g" \
    -e "s|__PROXYSQL_MONITOR_PASSWORD__|${PROXYSQL_MONITOR_PASSWORD}|g" \
    "${TEMPLATE}" > "${RENDERED}"

# Prove the rendered value is present (not inert).
grep -q "max_connections=${DB_MAX_CONNECTIONS}" "${RENDERED}"

exec proxysql -f -c "${RENDERED}" "$@"
