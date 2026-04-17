#!/bin/bash
# OpenXE container entrypoint.
#
# When the WFDB_* environment variables are present, generate
# conf/user.inc.php from them so the container is fully configured
# from the environment (production / Coolify deployment).
#
# When those env vars are not set (typical local dev with a bind-
# mount that already provides conf/user.inc.php), leave any existing
# file untouched.

set -e

if [ -n "${WFDB_NAME:-}" ] && [ -n "${WFDB_USER:-}" ] && [ -n "${WFDB_PASSWORD:-}" ]; then
    cat > /var/www/html/conf/user.inc.php <<EOF
<?php
\$this->WFdbhost='${WFDB_HOST:-mysql}';
\$this->WFdbport=${WFDB_PORT:-3306};
\$this->WFdbname='${WFDB_NAME}';
\$this->WFdbuser='${WFDB_USER}';
\$this->WFdbpass='${WFDB_PASSWORD}';
\$this->WFuserdata='${WFUSERDATA:-/var/www/html/userdata}';
EOF
    chown www-data:www-data /var/www/html/conf/user.inc.php 2>/dev/null || true
fi

exec docker-php-entrypoint "$@"
