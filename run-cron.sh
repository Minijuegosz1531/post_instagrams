#!/bin/bash

# Script para ejecutar el cron-process.php del Instagram Scraper
# Endpoint: https://instagram-scraper-850202598006.us-west1.run.app/cron-process.php

URL="https://instagram-scraper-850202598006.us-west1.run.app/cron-process.php"
LOG_DIR="/www/wwwroot/losdemarketing.com/posts/get_data_instagram/logs"
LOG_FILE="$LOG_DIR/cron-process.log"
MAX_LOG_SIZE=10485760  # 10MB en bytes

# Crear directorio de logs si no existe
mkdir -p "$LOG_DIR"

# Rotar log si es muy grande
if [ -f "$LOG_FILE" ] && [ $(stat -c%s "$LOG_FILE" 2>/dev/null || stat -f%z "$LOG_FILE" 2>/dev/null) -gt $MAX_LOG_SIZE ]; then
    mv "$LOG_FILE" "$LOG_FILE.old"
    gzip "$LOG_FILE.old" 2>/dev/null || true
fi

# Función para registrar en log
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

log "========================================"
log "Iniciando ejecución de cron-process"
log "URL: $URL"

# Ejecutar la petición HTTP con timeout de 120 segundos
response=$(curl -s -w "\n%{http_code}" --max-time 120 "$URL" 2>&1)
curl_exit=$?

if [ $curl_exit -ne 0 ]; then
    log "ERROR: curl falló con código $curl_exit"
    log "Respuesta: $response"
    exit 1
fi

http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | sed '$d')

log "Código HTTP: $http_code"

if [ "$http_code" -eq 200 ]; then
    log "✓ Proceso ejecutado exitosamente"
    log "Respuesta: $body"
    exit_code=0
else
    log "✗ Error en la ejecución (HTTP $http_code)"
    log "Respuesta: $body"
    exit_code=1
fi

log "Finalizado"
log ""

exit $exit_code
