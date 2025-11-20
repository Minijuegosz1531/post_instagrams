@echo off
echo ==========================================
echo Iniciando Instagram Scraper con Docker
echo ==========================================
echo.

REM Verificar si existe config.php
if not exist "config\config.php" (
    echo [X] Error: No se encontro config\config.php
    echo Por favor configura tus API keys primero
    pause
    exit /b 1
)

REM Verificar si Docker está corriendo
docker info >nul 2>&1
if %errorlevel% neq 0 (
    echo [X] Error: Docker no esta corriendo
    echo Por favor inicia Docker Desktop y vuelve a intentar
    pause
    exit /b 1
)

REM Detener contenedores previos si existen
echo Deteniendo contenedores previos...
docker-compose down

REM Iniciar la aplicación
echo.
echo Iniciando aplicacion...
docker-compose up -d

if %errorlevel% equ 0 (
    echo.
    echo [OK] Aplicacion iniciada exitosamente!
    echo.
    echo ==========================================
    echo Accede a la aplicacion en:
    echo   http://localhost:8080
    echo ==========================================
    echo.
    echo Para ver los logs en tiempo real:
    echo   docker-compose logs -f
    echo.
    echo Para detener la aplicacion:
    echo   docker-compose down
    echo.
) else (
    echo.
    echo [X] Error al iniciar la aplicacion
    echo Revisa los logs con: docker-compose logs
)

pause
