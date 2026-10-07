@echo off
title Si-Revita Artisan Server (Akses LAN)
cd /d %~dp0

echo ====================================================================
echo             SI-REVITA - SERVER AKSES JARINGAN LOKAL (LAN)
echo ====================================================================
echo IP Komputer Anda : 192.168.77.191
echo Port             : 8000
echo.
echo URL Akses dari perangkat lain (HP / Laptop di jaringan yang sama):
echo   ---> http://192.168.77.191:8000
echo ====================================================================
echo Menjalankan Laravel Artisan Serve (Tekan Ctrl+C untuk berhenti)...
echo.

php artisan serve --host=0.0.0.0 --port=8000
pause

