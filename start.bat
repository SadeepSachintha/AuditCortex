@echo off
setlocal enabledelayedexpansion
title AuditCortex - Autonomous Agentic Code Reviewer & PR Security Auditor
color 0B

echo ===============================================================================
echo                   AUDITCORTEX - AUTONOMOUS SECURITY SAAS
echo           Multi-Pass AST Vulnerability Scanning & Fix Synthesizer
echo ===============================================================================
echo.

:: Change directory to script location
cd /d "%~dp0"

:: 1. Check PHP
where php >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] PHP executable was not found in PATH!
    echo Please make sure PHP (XAMPP) is installed and available.
    pause
    exit /b 1
)

:: 2. Check Python
where python >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Python executable was not found in PATH!
    echo Please install Python 3.10+ and add it to PATH.
    pause
    exit /b 1
)

echo [OK] PHP and Python runtimes verified.
echo.

:: 3. Check / Run Migrations & Seeding if database.sqlite does not exist
if not exist "database\database.sqlite" (
    echo [*] Initializing SQLite database and running migrations...
    type nul > database\database.sqlite
    php artisan migrate --force
    php artisan db:seed --force
    echo [OK] Database seeded with initial sample PR fixtures.
    echo.
)

:: 4. Start FastAPI Agent Engine (Port 8080)
echo [*] Starting Python FastAPI Agent Engine on http://127.0.0.1:8080 ...
start "AuditCortex - FastAPI Agent Engine (Port 8080)" /min cmd /k "color 0A && title AuditCortex Agent Engine && python -m uvicorn agent_engine.main:app --host 127.0.0.1 --port 8080"

:: Wait 2 seconds for FastAPI to bind
timeout /t 2 /nobreak >nul

:: 5. Start Laravel Orchestrator & Review Terminal (Port 8000)
echo [*] Starting Laravel Webhook Orchestrator & Review Terminal on http://127.0.0.1:8000 ...
start "AuditCortex - Laravel Orchestrator (Port 8000)" /min cmd /k "color 09 && title AuditCortex Laravel Web && php artisan serve --port 8000"

:: Wait 2 seconds for Laravel to bind
timeout /t 2 /nobreak >nul

:: 6. Launch Web Browser
echo [*] Launching AuditCortex Review Terminal in default browser...
start http://127.0.0.1:8000/

echo.
echo ===============================================================================
echo  [SUCCESS] AuditCortex is now fully running!
echo.
echo   - Web Review Terminal : http://127.0.0.1:8000/
echo   - Python Agent Engine : http://127.0.0.1:8080/health
echo   - Webhook Receiver    : http://127.0.0.1:8000/api/webhooks/github
echo.
echo   To shut down both servers, run stop.bat or close the background windows.
echo ===============================================================================
echo.
pause
