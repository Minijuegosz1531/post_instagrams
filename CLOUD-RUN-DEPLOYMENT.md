# Deployment a Google Cloud Run

Esta guía te ayudará a desplegar la aplicación Instagram Scraper en Google Cloud Run.

## ¿Por qué Cloud Run?

- ✅ **Sin servidor**: No gestiones infraestructura
- ✅ **Escalado automático**: De 0 a N instancias según demanda
- ✅ **Pago por uso**: Solo pagas cuando se usa (por segundo)
- ✅ **HTTPS incluido**: Certificado SSL automático
- ✅ **Global**: Deploy en múltiples regiones

## Requisitos Previos

1. **Cuenta de Google Cloud Platform**
   - Crea una en: https://console.cloud.google.com/
   - Habilita facturación (incluye $300 de créditos gratuitos)

2. **Google Cloud CLI (gcloud)**
   - Instala desde: https://cloud.google.com/sdk/docs/install
   - Verifica instalación: `gcloud --version`

3. **Docker Desktop**
   - Instala desde: https://www.docker.com/products/docker-desktop

4. **Credenciales configuradas**
   - API Token de Apify
   - Credenciales de Google Sheets (Service Account JSON)
   - Google Sheet ID

## Opción 1: Deployment Manual (Recomendado para primera vez)

### Paso 1: Instalar y Configurar gcloud

```bash
# Instalar gcloud CLI
# Windows: Descarga el instalador
# Mac: brew install google-cloud-sdk
# Linux: curl https://sdk.cloud.google.com | bash

# Inicializar gcloud
gcloud init

# Autenticar
gcloud auth login

# Listar proyectos disponibles
gcloud projects list

# Crear un nuevo proyecto (opcional)
gcloud projects create mi-proyecto-instagram --name="Instagram Scraper"

# Configurar proyecto por defecto
gcloud config set project mi-proyecto-instagram
```

### Paso 2: Habilitar APIs Necesarias

```bash
# Habilitar Cloud Run
gcloud services enable run.googleapis.com

# Habilitar Container Registry
gcloud services enable containerregistry.googleapis.com

# Habilitar Cloud Build
gcloud services enable cloudbuild.googleapis.com
```

### Paso 3: Configurar Variables de Entorno

Crea un archivo `.env.cloudrun` con tus credenciales:

```bash
APIFY_API_TOKEN=apify_api_XXXXXXXXXXXXXXXXXXXXXXXX
GOOGLE_SHEET_ID=1ABC...XYZ
```

### Paso 4: Preparar Credenciales de Google Sheets

```bash
# Convertir credenciales JSON a base64 para usarlas como variable de entorno
# En Linux/Mac:
base64 config/google-credentials.json > credentials-base64.txt

# En Windows (PowerShell):
[Convert]::ToBase64String([IO.File]::ReadAllBytes("config\google-credentials.json")) > credentials-base64.txt
```

### Paso 5: Build y Deploy

```bash
# Opción A: Usar el script automático
chmod +x deploy-cloudrun.sh
./deploy-cloudrun.sh tu-proyecto-id us-central1 instagram-scraper

# Opción B: Comandos manuales
# Build
gcloud builds submit --tag gcr.io/tu-proyecto-id/instagram-scraper -f Dockerfile.cloudrun

# Deploy
gcloud run deploy instagram-scraper \
  --image gcr.io/tu-proyecto-id/instagram-scraper \
  --platform managed \
  --region us-central1 \
  --allow-unauthenticated \
  --memory 512Mi \
  --cpu 1 \
  --timeout 300 \
  --max-instances 10 \
  --set-env-vars APIFY_API_TOKEN=tu_token_aqui \
  --set-env-vars GOOGLE_SHEET_ID=tu_sheet_id
```

### Paso 6: Configurar Secrets (Recomendado para producción)

En lugar de variables de entorno, usa Google Secret Manager:

```bash
# Crear secret para Apify Token
echo -n "apify_api_XXXXXXX" | gcloud secrets create apify-token --data-file=-

# Crear secret para Google Credentials
gcloud secrets create google-credentials --data-file=config/google-credentials.json

# Dar acceso al servicio Cloud Run
gcloud secrets add-iam-policy-binding apify-token \
  --member=serviceAccount:tu-proyecto-id@appspot.gserviceaccount.com \
  --role=roles/secretmanager.secretAccessor

# Deploy con secrets
gcloud run deploy instagram-scraper \
  --image gcr.io/tu-proyecto-id/instagram-scraper \
  --platform managed \
  --region us-central1 \
  --allow-unauthenticated \
  --update-secrets=/config/apify-token=apify-token:latest \
  --update-secrets=/config/google-credentials.json=google-credentials:latest
```

## Opción 2: CI/CD Automático con Cloud Build

### Configurar Cloud Build

1. **Conectar repositorio Git**:
   - Ve a: https://console.cloud.google.com/cloud-build/triggers
   - Clic en "Conectar repositorio"
   - Selecciona GitHub, GitLab o Bitbucket
   - Autoriza y selecciona tu repositorio

2. **Crear Trigger**:
   ```bash
   gcloud builds triggers create github \
     --repo-name=tu-repo \
     --repo-owner=tu-usuario \
     --branch-pattern="^main$" \
     --build-config=cloudbuild.yaml
   ```

3. **Configurar variables de entorno en Cloud Build**:
   - Ve a: https://console.cloud.google.com/run
   - Selecciona tu servicio
   - Edita > Variables y Secrets
   - Agrega tus variables

4. **Push y Deploy automático**:
   ```bash
   git add .
   git commit -m "Deploy to Cloud Run"
   git push origin main
   # Cloud Build automáticamente construirá y desplegará
   ```

## Verificar Deployment

```bash
# Ver URL del servicio
gcloud run services describe instagram-scraper \
  --platform managed \
  --region us-central1 \
  --format 'value(status.url)'

# Ver logs
gcloud run logs read --service=instagram-scraper --limit=50

# Ver logs en tiempo real
gcloud run logs tail --service=instagram-scraper
```

## Configuración Avanzada

### Configurar Dominio Personalizado

```bash
# Mapear dominio
gcloud beta run domain-mappings create \
  --service instagram-scraper \
  --domain scraper.tudominio.com \
  --region us-central1
```

### Configurar Autenticación

```bash
# Requerir autenticación
gcloud run services update instagram-scraper \
  --region us-central1 \
  --no-allow-unauthenticated
```

### Ajustar Recursos

```bash
# Más memoria y CPU
gcloud run services update instagram-scraper \
  --region us-central1 \
  --memory 1Gi \
  --cpu 2 \
  --concurrency 80
```

## Costos Estimados

Cloud Run cobra por:
- **Requests**: $0.40 por millón de requests
- **CPU**: $0.00002400 por vCPU-segundo
- **Memoria**: $0.00000250 por GiB-segundo

**Ejemplo**: 1000 requests/mes, 500ms promedio, 512MB RAM
- Costo: ~$0.20/mes (prácticamente gratis)

**Nivel gratuito mensual**:
- 2 millones de requests
- 360,000 vCPU-segundos
- 180,000 GiB-segundos

## Troubleshooting

### Error: "Permission denied"
```bash
# Asegúrate de tener los permisos correctos
gcloud projects add-iam-policy-binding tu-proyecto-id \
  --member=user:tu-email@gmail.com \
  --role=roles/run.admin
```

### Error: "Service not found"
```bash
# Verifica que el servicio existe
gcloud run services list --platform managed
```

### Ver logs de error
```bash
# Logs de Cloud Build
gcloud builds log $(gcloud builds list --limit=1 --format='value(id)')

# Logs de Cloud Run
gcloud run logs read --service=instagram-scraper --limit=100
```

### Contenedor no inicia
```bash
# Probar localmente primero
docker build -t test-image -f Dockerfile.cloudrun .
docker run -p 8080:8080 -e PORT=8080 test-image
```

## Actualizar la Aplicación

```bash
# Opción 1: Usar el script
./deploy-cloudrun.sh tu-proyecto-id

# Opción 2: Manual
gcloud builds submit --tag gcr.io/tu-proyecto-id/instagram-scraper
gcloud run deploy instagram-scraper --image gcr.io/tu-proyecto-id/instagram-scraper
```

## Rollback a Versión Anterior

```bash
# Listar revisiones
gcloud run revisions list --service=instagram-scraper

# Hacer rollback
gcloud run services update-traffic instagram-scraper \
  --to-revisions=instagram-scraper-00001-abc=100
```

## Eliminar el Servicio

```bash
# Eliminar servicio de Cloud Run
gcloud run services delete instagram-scraper --region us-central1

# Eliminar imágenes de Container Registry
gcloud container images delete gcr.io/tu-proyecto-id/instagram-scraper
```

## Monitoreo y Alertas

1. **Ver métricas**:
   - Ve a: https://console.cloud.google.com/run
   - Selecciona tu servicio
   - Pestaña "Métricas"

2. **Configurar alertas**:
   ```bash
   # Crear alerta de errores
   gcloud alpha monitoring policies create \
     --notification-channels=CHANNEL_ID \
     --display-name="Cloud Run Errors" \
     --condition-threshold-value=10
   ```

## Mejores Prácticas

1. ✅ **Usar secrets** para credenciales sensibles
2. ✅ **Habilitar logging** y monitoreo
3. ✅ **Configurar health checks** si es necesario
4. ✅ **Limitar max-instances** para controlar costos
5. ✅ **Usar tags** en imágenes para control de versiones
6. ✅ **Implementar CI/CD** para deploys automáticos
7. ✅ **Configurar alertas** para errores y latencia

## Recursos Adicionales

- [Documentación de Cloud Run](https://cloud.google.com/run/docs)
- [Precios de Cloud Run](https://cloud.google.com/run/pricing)
- [Mejores prácticas](https://cloud.google.com/run/docs/best-practices)
- [Troubleshooting](https://cloud.google.com/run/docs/troubleshooting)

## Soporte

Si tienes problemas con el deployment:
1. Revisa los logs: `gcloud run logs read --service=instagram-scraper`
2. Verifica las variables de entorno
3. Prueba el contenedor localmente primero
4. Consulta la documentación oficial

¡Tu aplicación debería estar funcionando en Cloud Run! 🎉
