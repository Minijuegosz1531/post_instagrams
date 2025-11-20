# Instrucciones de Debugging

He agregado logs detallados tanto en JavaScript como en PHP para ayudarte a identificar el problema.

## Paso 1: Ver Logs del Navegador (JavaScript)

1. Abre la aplicación en tu navegador
2. Presiona `F12` para abrir las herramientas de desarrollador
3. Ve a la pestaña **Console**
4. Recarga la página (`F5`)
5. Deberías ver mensajes como:
   ```
   🚀 Script main.js cargado
   ✅ DOM cargado completamente
   📋 Elementos principales cargados: {form: true, resultsDiv: true, ...}
   🔍 Iniciando búsqueda de elementos del botón de prueba...
   📍 Elementos encontrados:
     - testButton: <button>
     - testBtnText: <span>
     - testBtnLoader: <span>
   ✅ Agregando event listener al botón de prueba...
   ✅ Event listener agregado exitosamente!
   ```

6. Haz clic en el botón "🧪 Probar con respuesta.json"
7. Deberías ver:
   ```
   🎯 Click detectado en el botón de prueba!
   ⏳ Loader activado, iniciando petición...
   📡 Haciendo fetch a test-google-sheets.php...
   📥 Respuesta recibida: 200 OK
   📦 Datos parseados: {success: true, data: [...], ...}
   ✅ Éxito! Mostrando resultados...
   ```

## Paso 2: Ver Logs del Servidor (PHP)

### Si usas Docker:

```bash
# Ver logs en tiempo real
docker-compose logs -f

# O específicamente del contenedor app
docker logs -f instagram-scraper-app
```

### Si usas PHP local:

Los logs se escriben en el error log de PHP. Depende de tu configuración:

**En Windows (XAMPP):**
```
C:\xampp\apache\logs\error.log
```

**En Windows (PHP standalone):**
```bash
# Ver logs mientras ejecutas el servidor
php -S localhost:8000 2>&1 | tee php-errors.log
```

**En Linux/Mac:**
```bash
tail -f /var/log/apache2/error.log
# o
tail -f /var/log/php-fpm/error.log
```

### Logs esperados del servidor:

```
🧪 [TEST] test-google-sheets.php iniciado
🧪 [TEST] Headers y requires cargados
🧪 [TEST] Buscando archivo: /ruta/a/respuesta.json
✅ [TEST] Archivo encontrado, leyendo contenido...
✅ [TEST] JSON parseado correctamente. Items: 1
✅ [TEST] Datos procesados: 1 filas
🔄 [TEST] Intentando enviar a Google Sheets...
✅ [TEST] GoogleSheetsHelper creado
✅ [TEST] Datos enviados exitosamente a Google Sheets
```

## Paso 3: Verificar qué está fallando

### Si NO ves logs en el navegador:
- El archivo `js/main.js` no se está cargando
- Verifica la ruta en `index.php`: `<script src="js/main.js"></script>`
- Verifica que el archivo exista en la carpeta `js/`
- Revisa la pestaña **Network** en DevTools para ver si hay error 404

### Si ves los logs pero NO el mensaje "Click detectado":
- El event listener no está funcionando
- Puede haber un error de JavaScript antes
- Busca errores en rojo en la consola

### Si el click funciona pero falla el fetch:
- Revisa el status code de la respuesta en la pestaña **Network**
- Verifica que `test-google-sheets.php` exista en la raíz del proyecto
- Verifica permisos del archivo (debe ser ejecutable por el servidor web)

### Si el servidor no responde:
- Verifica que el servidor esté corriendo
- Verifica que la ruta sea correcta (no debe tener `/` al inicio en el fetch)
- Mira los logs del servidor para ver si llegó la petición

## Paso 4: Probar el endpoint directamente

Puedes probar el endpoint PHP directamente desde la terminal:

```bash
# Si usas Docker:
docker-compose exec app php test-google-sheets.php

# Si usas PHP local:
php test-google-sheets.php
```

Esto debería mostrar el JSON de respuesta y los logs en la terminal.

## Paso 5: Probar con curl

También puedes probar con curl:

```bash
# Local
curl -X POST http://localhost:8000/test-google-sheets.php

# Docker
curl -X POST http://localhost:8080/test-google-sheets.php
```

## Errores Comunes

### Error: "No se encontró el botón de prueba"
- El HTML no se cargó correctamente
- Verifica que `index.php` tenga el botón con `id="testButton"`

### Error: "Archivo respuesta.json no encontrado"
- Verifica que `respuesta.json` esté en la raíz del proyecto
- Verifica la ruta en los logs del servidor

### Error: "Error al enviar a Google Sheets"
- Verifica tus credenciales en `config/google-credentials.json`
- Verifica que el Sheet ID sea correcto
- Verifica que hayas compartido la hoja con el service account
- Mira el mensaje de error completo en los logs

## Siguiente Paso

Una vez que veas los logs, dime qué mensajes aparecen o qué errores ves, y te ayudo a solucionarlo.
