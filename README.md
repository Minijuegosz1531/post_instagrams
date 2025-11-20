# Instagram Post Scraper - Gestor

Aplicación PHP para scraping de posts de Instagram usando Apify y envío de datos a Google Sheets.

## Características

- Carga de URLs de Instagram desde archivo CSV
- Scraping masivo usando Apify Actor (apify/instagram-post-scraper)
- Extracción de métricas: caption, vistas, reproducciones, comentarios, etc.
- Envío automático a Google Sheets
- Visualización de resultados en tabla HTML

## Requisitos

### Opción 1: Usando Docker (Recomendado para pruebas)
- Docker Desktop instalado
- Docker Compose
- Cuenta de Apify con acceso al API
- Cuenta de Google Cloud con Google Sheets API habilitado

### Opción 2: Instalación Local
- PHP 7.4 o superior
- Composer
- Cuenta de Apify con acceso al API
- Cuenta de Google Cloud con Google Sheets API habilitado

## Instalación Rápida con Docker

Docker es la forma más rápida de probar la aplicación sin necesidad de instalar PHP o Composer en tu sistema.

### 1. Configurar API Keys

Antes de iniciar Docker, configura tus credenciales:

#### a. Configurar Apify

Edita `config/config.php` y agrega tu API token:

```php
define('APIFY_API_TOKEN', 'tu_token_aqui');
```

Para obtener tu token:
1. Ve a [Apify Console](https://console.apify.com/account/integrations)
2. Copia tu API Token

#### b. Configurar Google Sheets

1. Sigue las instrucciones de la sección [Configurar Google Sheets](#3-configurar-google-sheets) más abajo
2. Coloca el archivo `google-credentials.json` en la carpeta `config/`
3. Actualiza el `GOOGLE_SHEET_ID` en `config/config.php`

### 2. Iniciar con Docker

**En Windows:**
```bash
setup.bat
docker-compose up -d
```

**En Linux/Mac:**
```bash
chmod +x setup.sh
./setup.sh
docker-compose up -d
```

### 3. Acceder a la aplicación

Abre tu navegador en: `http://localhost:8080`

### 4. Detener la aplicación

```bash
docker-compose down
```

### Comandos útiles de Docker

```bash
# Ver logs
docker-compose logs -f

# Reconstruir la imagen
docker-compose build --no-cache

# Reiniciar el contenedor
docker-compose restart

# Ver estado
docker-compose ps
```

## Instalación Local (Sin Docker)

### 1. Instalar dependencias

```bash
composer install
```

### 2. Configurar Apify

1. Crea una cuenta en [Apify](https://apify.com/)
2. Obtén tu API token desde [Apify Console](https://console.apify.com/account/integrations)
3. Copia el token en `config/config.php`:

```php
define('APIFY_API_TOKEN', 'tu_token_aqui');
```

### 3. Configurar Google Sheets

#### a. Crear proyecto en Google Cloud

1. Ve a [Google Cloud Console](https://console.cloud.google.com/)
2. Crea un nuevo proyecto
3. Habilita la API de Google Sheets:
   - Menú > APIs & Services > Library
   - Busca "Google Sheets API"
   - Haz clic en "Enable"

#### b. Crear Service Account

1. Menú > APIs & Services > Credentials
2. Clic en "Create Credentials" > "Service Account"
3. Completa el formulario y crea la cuenta
4. En la lista de Service Accounts, haz clic en la cuenta creada
5. Ve a la pestaña "Keys"
6. Clic en "Add Key" > "Create new key" > "JSON"
7. Descarga el archivo JSON
8. Renombra el archivo a `google-credentials.json`
9. Mueve el archivo a la carpeta `config/`

#### c. Configurar Google Sheet

1. Crea una nueva hoja de cálculo en Google Sheets
2. Copia el ID de la hoja (está en la URL):
   - URL: `https://docs.google.com/spreadsheets/d/[ID_AQUI]/edit`
3. Comparte la hoja con el email del Service Account (aparece en el JSON como `client_email`)
   - Dale permisos de "Editor"
4. Pega el ID en `config/config.php`:

```php
define('GOOGLE_SHEET_ID', 'tu_sheet_id_aqui');
```

5. Ajusta el nombre de la hoja si es necesario:

```php
define('GOOGLE_SHEET_RANGE', 'Hoja1'); // Cambia 'Hoja1' por el nombre de tu hoja
```

## Uso

### 1. Preparar archivo CSV

Crea un archivo CSV con las URLs de Instagram (una por línea):

```csv
https://www.instagram.com/p/DPCIz3zjtnv/
https://www.instagram.com/p/ABC123xyz/
https://www.instagram.com/p/DEF456uvw/
```

Ver `ejemplo.csv` para referencia.

### 2. Ejecutar la aplicación

1. Inicia un servidor PHP local:

```bash
php -S localhost:8000
```

2. Abre en tu navegador: `http://localhost:8000`

3. Sube tu archivo CSV

4. Espera a que se procesen los datos (puede tomar varios minutos dependiendo del número de URLs)

5. Los resultados se mostrarán en una tabla y se enviarán automáticamente a tu Google Sheet

## Estructura del Proyecto

```
post_instagrams/
├── config/
│   ├── config.php                      # Configuración (API keys)
│   ├── google-credentials.json         # Credenciales de Google (no incluido en git)
│   └── google-credentials.example.json # Ejemplo de credenciales
├── css/
│   └── styles.css                      # Estilos
├── js/
│   └── main.js                         # JavaScript para el frontend
├── uploads/                            # Carpeta temporal para CSVs
├── vendor/                             # Dependencias de Composer
├── GoogleSheetsHelper.php              # Clase helper para Google Sheets
├── process.php                         # Procesador principal
├── index.php                           # Página principal
├── composer.json                       # Dependencias
├── ejemplo.csv                         # Ejemplo de CSV
└── README.md                           # Este archivo
```

## Datos Extraídos

La aplicación extrae los siguientes datos de cada post:

- **Fecha**: Fecha y hora de ejecución
- **URL**: URL del post de Instagram
- **Caption**: Texto del post
- **Usuario**: Nombre de usuario del propietario
- **Comentarios**: Número de comentarios
- **Vistas**: Número de vistas del video
- **Reproducciones**: Número de reproducciones del video

## Solución de Problemas

### Error: "No se subió ningún archivo"
- Verifica que el tamaño del archivo no exceda el límite de PHP
- Aumenta `upload_max_filesize` y `post_max_size` en `php.ini` si es necesario

### Error: "Error al obtener datos de Apify"
- Verifica que tu API token de Apify sea correcto
- Asegúrate de tener créditos disponibles en tu cuenta de Apify
- Verifica que las URLs de Instagram sean válidas

### Error: "Error al enviar a Google Sheets"
- Verifica que el archivo de credenciales exista en `config/google-credentials.json`
- Asegúrate de haber compartido la hoja con el Service Account
- Verifica que el Sheet ID sea correcto

### El script se detiene o tarda mucho
- Apify puede tomar varios minutos para procesar múltiples URLs
- El timeout está configurado en 5 minutos
- Considera procesar las URLs en lotes más pequeños

## Seguridad

- NUNCA subas tus credenciales a Git
- El archivo `.gitignore` está configurado para excluir archivos sensibles
- Mantén tus API keys seguras
- Considera implementar rate limiting en producción

## Licencia

Este proyecto es de código abierto y está disponible bajo la licencia MIT.
