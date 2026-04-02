@echo off
REM Restart Flutter web server - double-click or run: restart.bat

cd /d "%~dp0"

echo Stopping server on port 8082...
for /f "tokens=5" %%a in ('netstat -ano 2^>nul ^| findstr ":8082" ^| findstr "LISTENING"') do taskkill /F /PID %%a 2>nul
timeout /t 2 /nobreak >nul

echo Starting...
call start.bat
