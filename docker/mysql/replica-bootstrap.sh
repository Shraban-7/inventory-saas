#!/usr/bin/env bash
# Idempotent replica bootstrap: configure GTID replication, then persist read-only.
# Replica must initialize with root only (no MYSQL_DATABASE/USER/PASSWORD) and stay
# writable until replication is healthy so the official entrypoint can finish.
# DEV-ONLY credentials — override via compose environment.
set -euo pipefail

WRITER_HOST="${MYSQL_WRITER_HOST:-mysql-writer}"
WRITER_PORT="${MYSQL_WRITER_PORT:-3306}"
REPLICA_HOST="${MYSQL_REPLICA_HOST:-mysql-replica}"
REPLICA_PORT="${MYSQL_REPLICA_PORT:-3306}"
ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-root_dev_only_change_me}"
REPL_USER="${MYSQL_REPLICATION_USER:-repl}"
REPL_PASSWORD="${MYSQL_REPLICATION_PASSWORD:-repl_dev_only_change_me}"

echo "Waiting for writer ${WRITER_HOST}:${WRITER_PORT}..."
until mysqladmin ping -h"${WRITER_HOST}" -P"${WRITER_PORT}" -uroot -p"${ROOT_PASSWORD}" --silent; do
    sleep 2
done

echo "Waiting for replica ${REPLICA_HOST}:${REPLICA_PORT}..."
until mysqladmin ping -h"${REPLICA_HOST}" -P"${REPLICA_PORT}" -uroot -p"${ROOT_PASSWORD}" --silent; do
    sleep 2
done

mysql_replica=(mysql -h"${REPLICA_HOST}" -P"${REPLICA_PORT}" -uroot -p"${ROOT_PASSWORD}")

enable_persisted_read_only() {
    "${mysql_replica[@]}" <<'SQL'
SET PERSIST read_only = ON;
SET PERSIST super_read_only = ON;
SQL
    local flags
    flags="$("${mysql_replica[@]}" -N -e "SELECT @@GLOBAL.read_only, @@GLOBAL.super_read_only;")"
    flags="$(printf '%s' "${flags}" | tr -d '\r')"
    printf '%s\n' "${flags}" | grep -Eq '^1[[:space:]]+1$'
    echo "Replica read_only and super_read_only are enabled and persisted."
}

wait_for_replica_healthy() {
    local status io sql
    for _ in $(seq 1 60); do
        status="$("${mysql_replica[@]}" -N -e "SHOW REPLICA STATUS\G" | tr -d '\r')"
        io="$(printf '%s\n' "${status}" | awk -F': ' '/Replica_IO_Running:/{print $2; exit}')"
        sql="$(printf '%s\n' "${status}" | awk -F': ' '/Replica_SQL_Running:/{print $2; exit}')"
        if [ "${io}" = "Yes" ] && [ "${sql}" = "Yes" ]; then
            return 0
        fi
        sleep 2
    done
    return 1
}

already_running="$("${mysql_replica[@]}" -N -e "SHOW REPLICA STATUS\G" 2>/dev/null | tr -d '\r' || true)"
io="$(printf '%s\n' "${already_running}" | awk -F': ' '/Replica_IO_Running:/{print $2; exit}')"
sql="$(printf '%s\n' "${already_running}" | awk -F': ' '/Replica_SQL_Running:/{print $2; exit}')"

if [ "${io}" = "Yes" ] && [ "${sql}" = "Yes" ]; then
    echo "Replica replication already running."
    enable_persisted_read_only
    exit 0
fi

# Ensure bootstrap can write SET PERSIST / CHANGE SOURCE on a fresh instance.
"${mysql_replica[@]}" -e "SET GLOBAL super_read_only = OFF; SET GLOBAL read_only = OFF;" 2>/dev/null || true

"${mysql_replica[@]}" -e "STOP REPLICA; RESET REPLICA ALL;" 2>/dev/null || true

"${mysql_replica[@]}" <<SQL
CHANGE REPLICATION SOURCE TO
  SOURCE_HOST='${WRITER_HOST}',
  SOURCE_PORT=${WRITER_PORT},
  SOURCE_USER='${REPL_USER}',
  SOURCE_PASSWORD='${REPL_PASSWORD}',
  SOURCE_AUTO_POSITION=1,
  GET_SOURCE_PUBLIC_KEY=1;
START REPLICA;
SQL

if wait_for_replica_healthy; then
    echo "Replica replication is running."
    enable_persisted_read_only
    exit 0
fi

echo "Replica failed to start within timeout." >&2
"${mysql_replica[@]}" -e "SHOW REPLICA STATUS\G" || true
exit 1
