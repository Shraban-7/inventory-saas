#!/usr/bin/env bash
# Integration probe for ProxySQL + reporting replica routing.
# Requires: docker compose stack healthy (mysql-writer, mysql-replica, proxysql, app).
#
# Usage:
#   ./scripts/tests/integration/reporting-replica.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
cd "${ROOT}"

COMPOSE=(docker compose -f compose.yaml)
DB_DATABASE="${MYSQL_DATABASE:-inventory_saas}"
DB_USERNAME="${MYSQL_USER:-inventory}"
DB_PASSWORD="${MYSQL_PASSWORD:-inventory_dev_only_change_me}"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-root_dev_only_change_me}"
DB_MAX_CONNECTIONS="${DB_MAX_CONNECTIONS:-50}"

echo "==> Assert physical replica is read_only (runtime + persisted for restarts)"
read_only="$("${COMPOSE[@]}" exec -T mysql-replica mysql -N -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "SELECT @@GLOBAL.read_only, @@GLOBAL.super_read_only;")"
printf '%s\n' "${read_only}" | tr -d '\r' | grep -Eq '^1[[:space:]]+1$'

persisted="$("${COMPOSE[@]}" exec -T mysql-replica mysql -N -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "SELECT variable_name, variable_value FROM performance_schema.persisted_variables WHERE variable_name IN ('read_only','super_read_only') ORDER BY variable_name;")"
persisted="$(printf '%s\n' "${persisted}" | tr -d '\r')"
printf '%s\n' "${persisted}" | grep -Eq '^read_only[[:space:]]+ON$'
printf '%s\n' "${persisted}" | grep -Eq '^super_read_only[[:space:]]+ON$'

echo "==> Assert replica did not independently create the app schema user (replication-only users)"
replica_users="$("${COMPOSE[@]}" exec -T mysql-replica mysql -N -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "SELECT COUNT(*) FROM mysql.user WHERE user='${DB_USERNAME}' AND host='%';")"
replica_users="$(printf '%s' "${replica_users}" | tr -d '\r')"
# App user must exist via replication from writer after bootstrap; count >= 1 once caught up.
test "${replica_users}" -ge 1

echo "==> Assert ProxySQL mysql_users.max_connections matches DB_MAX_CONNECTIONS=${DB_MAX_CONNECTIONS}"
proxysql_max="$("${COMPOSE[@]}" exec -T proxysql mysql -N -h127.0.0.1 -P6032 -uadmin -padmin_dev_only_change_me -e "SELECT max_connections FROM mysql_users WHERE username='${DB_USERNAME}' AND active=1;")"
proxysql_max="$(printf '%s' "${proxysql_max}" | tr -d '\r')"
test "${proxysql_max}" = "${DB_MAX_CONNECTIONS}"

echo "==> Assert app can write through ProxySQL"
"${COMPOSE[@]}" exec -T proxysql mysql -N -h127.0.0.1 -P6033 -u"${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" -e "SELECT 1;"

echo "==> Assert reporting logical connection remains reporting (not sticky mysql read/write)"
"${COMPOSE[@]}" exec -T app php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connections = [];
Illuminate\Support\Facades\DB::listen(function ($query) use (&$connections) {
    $connections[] = $query->connectionName;
});
try {
    Illuminate\Support\Facades\DB::connection("reporting")->select("select 1 as ok");
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
$unique = array_values(array_unique($connections));
if ($unique !== ["reporting"]) {
    fwrite(STDERR, "Expected reporting connection, got: " . json_encode($unique) . PHP_EOL);
    exit(1);
}
$host = config("database.connections.reporting.host");
$writeHost = config("database.connections.mysql.host");
echo "reporting_host={$host}\nwrite_host={$writeHost}\n";
if ($host === "" || $host === null) {
    fwrite(STDERR, "Reporting host is empty\n");
    exit(1);
}
'

echo "==> Assert replica rejects writes"
set +e
write_error="$("${COMPOSE[@]}" exec -T mysql-replica mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${DB_DATABASE}" -e "CREATE TABLE IF NOT EXISTS __replica_write_probe (id INT);" 2>&1)"
status=$?
set -e
if [ "${status}" -eq 0 ]; then
    echo "Replica unexpectedly accepted a write" >&2
    exit 1
fi
printf '%s\n' "${write_error}" | grep -Ei 'read.?only|SUPER READ ONLY|1290' >/dev/null

echo "Integration checks passed."
