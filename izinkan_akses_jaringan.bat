@echo off
title Buka Firewall Si-Revita untuk Akses Jaringan Lokal (LAN)
echo ======================================================================
echo          MENGATUR WINDOWS FIREWALL UNTUK AKSES LOKAL SI-REVITA
echo ======================================================================
echo.

:: Cek Administrator Privilege
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo [PERHATIAN] Script ini membutuhkan hak akses Administrator!
    echo.
    echo Cara menjalankan:
    echo 1. Tutup jendela ini.
    echo 2. Klik kanan file "izinkan_akses_jaringan.bat"
    echo 3. Pilih "Run as administrator" (Jalankan sebagai administrator)
    echo.
    pause
    exit /b
)

echo [1/2] Menambahkan izin Windows Firewall untuk Port 80 (Apache)...
netsh advfirewall firewall delete rule name="Si-Revita Apache (Port 80)" >nul 2>&1
netsh advfirewall firewall add rule name="Si-Revita Apache (Port 80)" dir=in action=allow protocol=TCP localport=80 profile=any >nul 2>&1

echo [2/2] Menambahkan izin Windows Firewall untuk Port 8000 (Artisan)...
netsh advfirewall firewall delete rule name="Si-Revita Artisan (Port 8000)" >nul 2>&1
netsh advfirewall firewall add rule name="Si-Revita Artisan (Port 8000)" dir=in action=allow protocol=TCP localport=8000 profile=any >nul 2>&1

echo.
echo ======================================================================
echo [SUKSES!] Port 80 dan 8000 BERHASIL diizinkan di Windows Firewall.
echo.
echo Sekarang perangkat lain (HP / Laptop di WiFi atau LAN yang sama)
echo dapat membuka Si-Revita melalui browser:
echo.
echo     http://192.168.77.191
echo.
echo ======================================================================
pause
