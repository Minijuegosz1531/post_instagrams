# Configuración del Crontab

## Instrucciones para configurar la ejecución cada 3 horas

### 1. Subir el script al servidor
Copiar `run-cron.sh` a la ruta del servidor:
```bash
/www/wwwroot/losdemarketing.com/posts/get_data_instagram/run-cron.sh
```

### 2. Dar permisos de ejecución
```bash
chmod +x /www/wwwroot/losdemarketing.com/posts/get_data_instagram/run-cron.sh
```

### 3. Configurar el crontab
Editar el crontab:
```bash
crontab -e
```

Agregar esta línea para ejecutar cada 3 horas:
```cron
0 */3 * * * /www/wwwroot/losdemarketing.com/posts/get_data_instagram/run-cron.sh
```

**Opciones de horario:**
- `0 */3 * * *` - Cada 3 horas en punto (00:00, 03:00, 06:00, 09:00, etc.)
- `0 0,3,6,9,12,15,18,21 * * *` - Lo mismo, pero más explícito
- `0 8,11,14,17,20,23 * * *` - Cada 3 horas empezando a las 8:00 AM

### 4. Verificar el crontab
```bash
crontab -l
```

### 5. Verificar los logs
El log se guardará en:
```
/www/wwwroot/losdemarketing.com/posts/get_data_instagram/logs/cron-process.log
```

Ver los últimos registros:
```bash
tail -f /www/wwwroot/losdemarketing.com/posts/get_data_instagram/logs/cron-process.log
```

## Notas
- El log rotará automáticamente cuando supere los 10MB
- El script tiene un timeout de 120 segundos
- Los logs antiguos se comprimen automáticamente (.gz)
