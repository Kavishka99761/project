@echo off
setlocal
cd /d "%~dp0"

if not exist "node_modules" (
  echo Installing AcadeAlert React dependencies...
  call npm install
  if errorlevel 1 (
    echo npm install failed. Make sure Node.js and npm are installed.
    pause
    exit /b 1
  )
)

start "AcadeAlert Dev Server" cmd /k "cd /d ""%~dp0"" && npm run dev"
timeout /t 3 /nobreak >nul
start "" "http://localhost:5173"

endlocal