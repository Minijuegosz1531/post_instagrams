#!/bin/bash

echo "=========================================="
echo "Iniciando Instagram Scraper con Docker"
echo "=========================================="
echo ""

# Verificar si existen los archivos de configuración
if [ ! -f "config/config.php" ]; then
    echo "⚠ Error: No se encontró config/config.php"
    echo "Por favor configura tus API keys primero"
    exit 1
fi

# Verificar si Docker está corriendo
if ! docker info > /dev/null 2>&1; then
    echo "⚠ Error: Docker no está corriendo"
    echo "Por favor inicia Docker Desktop y vuelve a intentar"
    exit 1
fi

# Detener contenedores previos si existen
echo "Deteniendo contenedores previos..."
docker-compose down

# Iniciar la aplicación
echo ""
echo "Iniciando aplicación..."
docker-compose up -d

if [ $? -eq 0 ]; then
    echo ""
    echo "✓ Aplicación iniciada exitosamente!"
    echo ""
    echo "=========================================="
    echo "Accede a la aplicación en:"
    echo "  http://localhost:8080"
    echo "=========================================="
    echo ""
    echo "Para ver los logs en tiempo real:"
    echo "  docker-compose logs -f"
    echo ""
    echo "Para detener la aplicación:"
    echo "  docker-compose down"
    echo ""
else
    echo ""
    echo "⚠ Error al iniciar la aplicación"
    echo "Revisa los logs con: docker-compose logs"
fi
