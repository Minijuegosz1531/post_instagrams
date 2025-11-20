@echo off
echo ==========================================
echo Instagram Scraper - Setup
echo ==========================================
echo.

REM Verificar si Docker está instalado
docker --version >nul 2>&1
if %errorlevel% neq 0 (
    echo Error: Docker no esta instalado.
    echo Por favor instala Docker Desktop desde: https://www.docker.com/products/docker-desktop
    pause
    exit /b 1
)

REM Verificar si Docker Compose está instalado
docker-compose --version >nul 2>&1
if %errorlevel% neq 0 (
    echo Error: Docker Compose no esta instalado.
    echo Por favor instala Docker Desktop que incluye Docker Compose
    pause
    exit /b 1
)

echo [OK] Docker y Docker Compose estan instalados
echo.

REM Verificar archivos de configuración
if not exist "config\config.php" (
    echo [!] Advertencia: No se encontro config\config.php
    echo Asegurate de configurar tus API keys antes de usar la aplicacion
)

if not exist "config\google-credentials.json" (
    echo [!] Advertencia: No se encontro config\google-credentials.json
    echo Necesitaras crear este archivo para usar Google Sheets
    echo Ver README.md para instrucciones
)

echo.
echo Construyendo la imagen Docker...
docker-compose build

if %errorlevel% neq 0 (
    echo Error: Fallo la construccion de la imagen Docker
    pause
    exit /b 1
)

echo.
echo [OK] Imagen construida exitosamente
echo.
echo ==========================================
echo Configuracion completada!
echo ==========================================
echo.
echo Para iniciar la aplicacion, ejecuta:
echo   docker-compose up -d
echo.
echo Para detener la aplicacion, ejecuta:
echo   docker-compose down
echo.
echo La aplicacion estara disponible en:
echo   http://localhost:8080
echo.
echo Recuerda configurar:
echo   1. config\config.php con tu API token de Apify
echo   2. config\google-credentials.json con tus credenciales
echo   3. El Sheet ID en config\config.php
echo.
pause
