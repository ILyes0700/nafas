@echo off
REM ============================================================
REM Nafass - Build Windows .exe (script automatique)
REM ============================================================
setlocal

echo.
echo ============================================
echo  Nafass - Build .exe pour Windows
echo ============================================
echo.

REM 1) Verifier Node
where node >nul 2>nul
if errorlevel 1 (
  echo [ERREUR] Node.js n'est pas installe.
  echo Telecharge-le sur https://nodejs.org puis relance ce script.
  pause
  exit /b 1
)

echo [1/3] Installation des dependances...
call npm install
if errorlevel 1 (
  echo [ERREUR] npm install a echoue.
  pause
  exit /b 1
)

echo.
echo [2/3] Generation de l'executable Windows...
call npm run dist:win
if errorlevel 1 (
  echo [ERREUR] Le build a echoue.
  pause
  exit /b 1
)

echo.
echo [3/3] Build termine !
echo.
echo ============================================
echo  Fichiers generes dans le dossier dist\ :
echo ============================================
dir /B dist\*.exe 2>nul
echo.
echo Tu peux lancer Nafass-1.0.0-portable.exe ou Nafass-1.0.0-x64.exe
echo.
pause
