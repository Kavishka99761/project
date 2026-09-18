@echo off
title AcadeAlert - Full System Launcher
echo ========================================================
echo   AcadeAlert Academic Platform Launcher
echo   Backend: http://127.0.0.1:8000
echo   Frontend: http://localhost:5173
echo ========================================================
echo.
echo Launching Laravel Backend...
start "AcadeAlert Backend API" cmd /c "%~dp0start-backend.bat"

echo Launching React Frontend...
start "AcadeAlert React App" cmd /c "%~dp0start-frontend.bat"

timeout /t 3 /nobreak >nul
echo Opening browser...
start http://localhost:5173

echo Both servers have been launched!
