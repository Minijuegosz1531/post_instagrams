# Guía Rápida - Docker

Esta guía te ayudará a ejecutar la aplicación usando Docker en menos de 5 minutos.

## Prerrequisitos

1. **Docker Desktop** instalado
   - Windows/Mac: [Descargar Docker Desktop](https://www.docker.com/products/docker-desktop)
   - Linux: Instalar Docker y Docker Compose

2. **Credenciales configuradas**:
   - API Token de Apify
   - Credenciales de Google Sheets (archivo JSON)
   - Google Sheet ID

## Pasos Rápidos

### 1. Configurar Credenciales

#### Apify
Edita `config/config.php`:
```php
define('APIFY_API_TOKEN', 'apify_api_XXXXXXXXXXXXXXXXXXXXXXXX');
```

#### Google Sheets
1. Coloca tu archivo de credenciales en: `config/google-credentials.json`
2. Edita `config/config.php`:
```php
define('GOOGLE_SHEET_ID', 'tu-sheet-id-aqui');
```

### 2. Iniciar Aplicación

**Windows:**
```bash
docker-start.bat
```

**Linux/Mac:**
```bash
chmod +x docker-start.sh
./docker-start.sh
```

O manualmente:
```bash
docker-compose up -d
```

### 3. Abrir Aplicación

Abre tu navegador en: **http://localhost:8080**

### 4. Usar la Aplicación

1. Prepara un archivo CSV con URLs de Instagram:
   ```
   https://www.instagram.com/p/DPCIz3zjtnv/
   https://www.instagram.com/p/ABC123xyz/
   ```

2. Sube el archivo en la interfaz web

3. Espera a que se procesen los datos (3-5 minutos)

4. Los resultados aparecerán en la tabla y en tu Google Sheet

## Comandos Útiles

```bash
# Ver logs en tiempo real
docker-compose logs -f

# Detener la aplicación
docker-compose down

# Reiniciar
docker-compose restart

# Ver estado
docker-compose ps

# Reconstruir (después de cambios en código)
docker-compose build --no-cache
docker-compose up -d
```

## Solución de Problemas

### Puerto 8080 ya en uso
Edita `docker-compose.yml` y cambia el puerto:
```yaml
ports:
  - "8081:80"  # Cambiar 8080 por 8081
```

### Error "Docker not running"
1. Abre Docker Desktop
2. Espera a que inicie completamente
3. Vuelve a ejecutar el comando

### Cambios en config.php no se aplican
Los archivos de configuración están montados como volumen, así que los cambios deben aplicarse inmediatamente. Si no:
```bash
docker-compose restart
```

### Ver archivos dentro del contenedor
```bash
docker-compose exec app ls -la /var/www/html
```

### Acceder al contenedor
```bash
docker-compose exec app bash
```

## Notas Importantes

- Los archivos en `uploads/` y `config/` están montados como volúmenes
- Los cambios en estos archivos se reflejan inmediatamente
- Los cambios en otros archivos PHP requieren reconstruir: `docker-compose build`
- El contenedor usa Apache en el puerto 80, mapeado a 8080 en tu host

## Arquitectura Docker

```
Host (tu computadora)
  ↓
Puerto 8080
  ↓
Docker Container
  ↓
Apache (Puerto 80)
  ↓
PHP 8.1 + App
```

## Estructura de Volúmenes

```
./uploads → /var/www/html/uploads   (archivos CSV temporales)
./config  → /var/www/html/config    (credenciales)
```

Esto permite que tus credenciales permanezcan en tu máquina y no se copien al contenedor.

## Primeros Pasos Recomendados

1. **Probar con 1 URL primero** - Usa `ejemplo.csv` que tiene solo 1 URL
2. **Verificar Google Sheets** - Asegúrate de que los datos lleguen correctamente
3. **Luego escalar** - Prueba con más URLs

## Siguiente Paso

Si todo funciona, lee el [README.md](README.md) completo para más detalles sobre configuración avanzada.
