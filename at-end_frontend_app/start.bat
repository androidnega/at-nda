@echo off
REM Start Flutter web app on LAN - other devices on same network can access it
REM Run as Administrator if firewall rule fails

cd /d "%~dp0"

echo.
echo === Flutter Attendance App - LAN Mode ===
echo.

REM Add Windows Firewall rule (may require Admin - right-click start.bat, Run as administrator)
netsh advfirewall firewall add rule name="Flutter Web 8082" dir=in action=allow protocol=TCP localport=8082 2>nul
if %errorlevel% neq 0 (
    echo [Note] Firewall rule may need Admin. If other devices cannot connect,
    echo        run this script as Administrator, or allow port 8082 in Windows Firewall.
    echo.
)

REM Get LAN IP from adapter with default gateway (WiFi/Ethernet - NOT VirtualBox)
for /f "delims=" %%a in ('powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0get_lan_ip.ps1" 2^>nul') do set "LAN_IP=%%a"
set "LAN_IP=%LAN_IP: =%"
if "%LAN_IP%"=="" (
    echo Could not detect LAN IP. Run "ipconfig" and use your WiFi/Ethernet IPv4 with :8082
    set "LAN_IP=???"
)

echo Your computer's LAN IP: %LAN_IP%
echo.
echo Access the app from other devices (phone, tablet) at:
echo   http://%LAN_IP%:8082
echo.
echo IMPORTANT: Other devices must be on the SAME WiFi network as this PC.
echo (192.168.56.1 is VirtualBox - ignore it. Use the IP above.)
echo.

flutter run -d web-server --web-hostname 0.0.0.0 --web-port 8082

pause
