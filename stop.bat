@echo off
title AuditCortex - Shutdown
color 0C

echo ===============================================================================
echo                SHUTTING DOWN AUDITCORTEX SERVICES
echo ===============================================================================
echo.

cd /d "%~dp0"

echo [*] Terminating process on Port 8080 (FastAPI Agent Core)...
powershell -Command "Get-NetTCPConnection -LocalPort 8080 -ErrorAction SilentlyContinue | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue }"

echo [*] Terminating process on Port 8000 (Laravel Web Server)...
powershell -Command "Get-NetTCPConnection -LocalPort 8000 -ErrorAction SilentlyContinue | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue }"

echo.
echo [OK] All AuditCortex background servers terminated cleanly.
echo.
pause
