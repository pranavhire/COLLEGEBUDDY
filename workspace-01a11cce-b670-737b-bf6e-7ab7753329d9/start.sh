#!/usr/bin/env bash
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
export PATH="$HOME/.local/mamba-env/php-env/bin:$HOME/.local/mamba-env/mysql-env/bin:$PATH"

# 1. Start MySQL if not running
if ! lsof -i :3306 >/dev/null 2>&1; then
    echo "Starting MySQL server..."
    "$HOME/.local/mamba-env/mysql-env/bin/mysqld" \
        --datadir="$HOME/.local/mysql-data" \
        --socket=/tmp/mysql.sock \
        --port=3306 \
        --ssl=0 \
        --default-authentication-plugin=mysql_native_password >/dev/null 2>&1 &
    sleep 2
fi

# 2. Start PHP server if not running
if ! lsof -i :8000 >/dev/null 2>&1; then
    echo "Starting PHP server on http://localhost:8000..."
    cd "$DIR/CollegeBuddy" || exit 1
    "$HOME/.local/mamba-env/php-env/bin/php" -S 0.0.0.0:8000 router.php >/dev/null 2>&1 &
    sleep 1
fi

echo "CollegeBuddy is running at http://localhost:8000"
open http://localhost:8000
