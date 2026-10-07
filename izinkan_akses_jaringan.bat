@echo off
setlocal
title Izin Akses Jaringan Lokal Si-Revita
echo ====================================================================
echo  MEMBUKA PORT FIREWALL UNTUK AKSES LOKAL JARINGAN SI-REVITA
echo ====================================================================
echo.

openfiles >nul 2>&1
if %errorlevel% neq 0 (
    echo [INFO] Meminta izin hak akses Administrator...
    powershell -NoProfile -Command "Start-Process cmd.exe -ArgumentList '/c \"\"%~f0\" admin\"' -Verb RunAs"
    exit /b
)

echo Menambahkan aturan Firewall untuk Port 80 dan 8000 (TCP)...
netsh advfirewall firewall delete rule name="Sirevita LAN (Port 80, 8000)" >nul 2>&1
netsh advfirewall firewall add rule name="Sirevita LAN (Port 80, 8000)" dir=in action=allow protocol=TCP localport=80,8000 profile=any >nul 2>&1

if %errorlevel% equ 0 (
    echo [SUKSES] Port 80 dan 8000 BERHASIL diizinkan di Windows Firewall!
    echo.
    echo Aplikasi Si-Revita dapat diakses oleh perangkat lain di jaringan yang sama melalui:
    echo   - http://192.168.77.191
    echo   - http://192.168.77.191:8000
    echo.
) else (
    echo [ERROR] Gagal menambahkan aturan firewall.
    echo Silakan klik kanan file ini lalu pilih "Run as administrator".
)

echo ====================================================================
if "%1"=="admin" (
    echo Jendela ini akan tertutup otomatis dalam 5 detik...
    timeout /t 5 >nul
) else (
    pause
)

