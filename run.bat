@echo off
rem Start GemDesk (Windows)
cd /d %~dp0
echo Starting GemDesk at http://127.0.0.1:8000  (close this window to stop)
php artisan serve
