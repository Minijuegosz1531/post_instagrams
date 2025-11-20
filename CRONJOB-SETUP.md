# Configuración del Cronjob

Este documento explica cómo configurar el script para que se ejecute automáticamente desde un cronjob.

## Funcionamiento

El script `cron-process.php`:
1. Lee las URLs desde Google Sheets (hoja "Urls")
2. Procesa cada URL con Apify para obtener métricas
3. Descarga y sube imágenes al FTP
4. Guarda los resultados en Google Sheets (hoja "Posts")
5. Detecta y actualiza filas duplicadas del mismo día

## Preparar Google Sheets

### Hoja "Urls" (entrada)
Crea una hoja llamada "Urls" con la siguiente estructura:

| A (URL) |
|---------|
| URL     |
| https://www.instagram.com/p/ABC123xyz/ |
| https://www.instagram.com/p/DEF456uvw/ |

- **Columna A**: URLs de Instagram (una por fila)
- **Fila 1**: Header opcional (se salta automáticamente)

### Hoja "Posts" (salida)
Los resultados se guardarán en la hoja "Posts" con estas columnas:

| Fecha | URL | Caption | Usuario | Comentarios | Vistas | Reproducciones | Imagen |
|-------|-----|---------|---------|-------------|--------|----------------|--------|

## Configuración del Cronjob

### Opción 1: Servidor Local o VPS

1. Abre el crontab:
```bash
crontab -e
```

2. Agrega una de estas líneas según la frecuencia deseada:

```bash
# Cada 5 minutos
*/5 * * * * cd /ruta/al/proyecto && php cron-process.php >> /var/log/instagram-scraper.log 2>&1

# Cada hora
0 * * * * cd /ruta/al/proyecto && php cron-process.php >> /var/log/instagram-scraper.log 2>&1

# Cada día a las 9:00 AM
0 9 * * * cd /ruta/al/proyecto && php cron-process.php >> /var/log/instagram-scraper.log 2>&1

# Cada hora entre las 8 AM y 6 PM (horario laboral)
0 8-18 * * * cd /ruta/al/proyecto && php cron-process.php >> /var/log/instagram-scraper.log 2>&1
```

3. Guardar y salir (Ctrl+X, luego Y, luego Enter en nano)

### Opción 2: Cloud Run (Google Cloud)

Cloud Run no soporta cronjobs nativamente, pero puedes usar **Cloud Scheduler**:

1. Crear un endpoint HTTP que ejecute el script:

Crea `cron-endpoint.php`:
```php
<?php
// Validar que la petición venga de Cloud Scheduler
$schedulerToken = $_SERVER['HTTP_X_CLOUDSCHEDULER'] ?? '';
if (empty($schedulerToken)) {
    http_response_code(403);
    die('Forbidden');
}

// Ejecutar el script
require_once 'cron-process.php';
```

2. Configurar Cloud Scheduler:
```bash
gcloud scheduler jobs create http instagram-scraper-cron \
  --location=us-central1 \
  --schedule="0 * * * *" \
  --uri="https://tu-servicio.run.app/cron-endpoint.php" \
  --http-method=GET \
  --headers="X-CloudScheduler=tu-token-secreto"
```

### Opción 3: cPanel (Hosting compartido)

1. Ingresa a cPanel
2. Busca "Cron Jobs"
3. En "Add New Cron Job":
   - **Minuto**: */5 (cada 5 minutos) o el intervalo deseado
   - **Comando**: `/usr/bin/php /home/usuario/public_html/ruta/cron-process.php`
4. Guardar

## Ejecutar Manualmente

Para probar el script antes de configurar el cronjob:

```bash
cd /ruta/al/proyecto
php cron-process.php
```

Salida esperada:
```
[2025-01-19 10:30:00] 🚀 Iniciando proceso automático de scraping...
[2025-01-19 10:30:01] ✅ Conexión con Google Sheets establecida
[2025-01-19 10:30:02] 📋 URLs encontradas: 5
[2025-01-19 10:30:03] 🔄 Llamando a Apify con 5 URLs...
[2025-01-19 10:30:45] ✅ Datos recibidos de Apify: 5 posts
[2025-01-19 10:30:46] ✅ Conexión FTP establecida
[2025-01-19 10:30:47] 📝 Procesando: https://www.instagram.com/p/ABC123xyz/
[2025-01-19 10:30:50] ✅ Imagen subida: https://losdemarketing.com/posts/ABC123_1234567890.jpg
[2025-01-19 10:30:51] ✅ Datos procesados
...
[2025-01-19 10:31:30] ✅ 3 filas nuevas agregadas a Google Sheets
[2025-01-19 10:31:30] ✅ 2 filas actualizadas
[2025-01-19 10:31:30] 🎉 Proceso completado exitosamente - Total procesado: 5
```

## Ver Logs

### Servidor Linux/Mac
```bash
# Ver logs en tiempo real
tail -f /var/log/instagram-scraper.log

# Ver últimas 100 líneas
tail -n 100 /var/log/instagram-scraper.log

# Buscar errores
grep "❌" /var/log/instagram-scraper.log
```

### Cloud Run
```bash
# Ver logs del servicio
gcloud run services logs read instagram-scraper --region=us-central1 --limit=100
```

## Códigos de Salida

El script retorna diferentes códigos según el resultado:

- `0`: Éxito (proceso completado sin errores)
- `1`: Error (falló la conexión, Apify, FTP, o Google Sheets)

## Solución de Problemas

### El script no se ejecuta
```bash
# Verificar que PHP está disponible
which php

# Verificar permisos de ejecución
ls -la cron-process.php

# Dar permisos si es necesario
chmod +x cron-process.php
```

### Error de credenciales
Verifica que el archivo `config/google-credentials.json` existe y tiene los permisos correctos:
```bash
ls -la config/google-credentials.json
chmod 644 config/google-credentials.json
```

### No encuentra las URLs
- Verifica que la hoja se llama exactamente "Urls"
- Confirma que las URLs están en la columna A
- Asegúrate de que son URLs válidas de Instagram

### Timeout de Apify
Si procesas muchas URLs (>10), considera:
- Dividir las URLs en lotes más pequeños
- Ejecutar el cronjob con menos frecuencia
- Aumentar el timeout en la línea 235 de `cron-process.php`

## Recomendaciones

1. **Frecuencia**: No ejecutes el script muy frecuentemente para evitar:
   - Consumir muchos créditos de Apify
   - Alcanzar rate limits de Instagram
   - Recomendado: Cada 1-6 horas

2. **Monitoreo**: Configura alertas si el script falla consecutivamente

3. **Backup**: Haz backup periódico de tu Google Sheet

4. **Límites**: Instagram y Apify tienen rate limits, respétalos
