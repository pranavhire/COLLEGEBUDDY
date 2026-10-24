#!/usr/bin/env bash
echo "Stopping CollegeBuddy PHP and MySQL servers..."
pkill -f "php -S 0.0.0.0:8000"
pkill -f "mysqld --datadir="
echo "CollegeBuddy servers stopped."
