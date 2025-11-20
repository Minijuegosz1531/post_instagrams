#!/bin/bash

echo "=========================================="
echo "Instagram Scraper - Setup"
echo "=========================================="
echo ""

# Verificar si Docker está instalado
if ! command -v docker &> /dev/null; then
    echo "Error: Docker no está instalado."
    echo "Por favor instala Docker desde: https://www.docker.com/get-started"
    exit 1
fi

# Verificar si Docker Compose está instalado
if ! command -v docker-compose &> /dev/null; then
    echo "Error: Docker Compose no está instalado."
    echo "Por favor instala Docker Compose desde: https://docs.docker.com/compose/install/"
    exit 1
fi

echo "✓ Docker y Docker Compose están instalados"
echo ""

# Verificar si existe config.php
if [ ! -f "config/config.php" ]; then
    echo "⚠ Advertencia: No se encontró config/config.php"
    echo "Asegúrate de configurar tus API keys antes de usar la aplicación"
fi

# Verificar si existen credenciales de Google
if [ ! -f "config/google-credentials.json" ]; then
    echo "⚠ Advertencia: No se encontró config/google-credentials.json"
    echo "Necesitarás crear este archivo para usar Google Sheets"
    echo "Ver README.md para instrucciones"
fi

echo ""
echo "Construyendo la imagen Docker..."
docker-compose build

if [ $? -ne 0 ]; then
    echo "Error: Falló la construcción de la imagen Docker"
    exit 1
fi

echo ""
echo "✓ Imagen construida exitosamente"
echo ""
echo "=========================================="
echo "Configuración completada!"
echo "=========================================="
echo ""
echo "Para iniciar la aplicación, ejecuta:"
echo "  docker-compose up -d"
echo ""
echo "Para detener la aplicación, ejecuta:"
echo "  docker-compose down"
echo ""
echo "La aplicación estará disponible en:"
echo "  http://localhost:8080"
echo ""
echo "Recuerda configurar:"
echo "  1. config/config.php con tu API token de Apify"
echo "  2. config/google-credentials.json con tus credenciales"
echo "  3. El Sheet ID en config/config.php"
echo ""
