#!/usr/bin/env bash
# Start GemDesk (Linux / macOS)
cd "$(dirname "$0")"
echo "Starting GemDesk at http://127.0.0.1:8000  (Ctrl+C to stop)"
php artisan serve
