@echo off
title AcadeAlert - Laravel Backend
cd /d "%~dp0backend-laravel"
set "PATH=C:\xampp\php;%PATH%"
echo Starting Laravel Backend API on http://127.0.0.1:8000 ...
php artisan serve --host=127.0.0.1 --port=8000
pause
