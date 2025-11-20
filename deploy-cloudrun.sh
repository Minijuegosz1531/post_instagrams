#!/bin/bash

# Script de deployment a Google Cloud Run
# Uso: ./deploy-cloudrun.sh [PROJECT_ID] [REGION] [SERVICE_NAME]

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}=========================================="
echo "🚀 Deployment a Google Cloud Run"
echo -e "==========================================${NC}"
echo ""

# Configuración
PROJECT_ID=${1:-""}
REGION=${2:-"us-central1"}
SERVICE_NAME=${3:-"instagram-scraper"}
IMAGE_NAME="gcr.io/$PROJECT_ID/$SERVICE_NAME"

# Validar PROJECT_ID
if [ -z "$PROJECT_ID" ]; then
    echo -e "${RED}❌ ERROR: PROJECT_ID es requerido${NC}"
    echo "Uso: ./deploy-cloudrun.sh [PROJECT_ID] [REGION] [SERVICE_NAME]"
    echo ""
    echo "Ejemplo:"
    echo "  ./deploy-cloudrun.sh mi-proyecto-123 us-central1 instagram-scraper"
    exit 1
fi

echo -e "${YELLOW}📋 Configuración:${NC}"
echo "  Project ID: $PROJECT_ID"
echo "  Región: $REGION"
echo "  Service: $SERVICE_NAME"
echo "  Image: $IMAGE_NAME"
echo ""

# Verificar que gcloud esté instalado
if ! command -v gcloud &> /dev/null; then
    echo -e "${RED}❌ ERROR: gcloud CLI no está instalado${NC}"
    echo "Instala desde: https://cloud.google.com/sdk/docs/install"
    exit 1
fi

# Verificar que Docker esté corriendo
if ! docker info > /dev/null 2>&1; then
    echo -e "${RED}❌ ERROR: Docker no está corriendo${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Dependencias verificadas${NC}"
echo ""

# Confirmar antes de continuar
read -p "¿Continuar con el deployment? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}⚠ Deployment cancelado${NC}"
    exit 0
fi

# Configurar proyecto
echo -e "${BLUE}🔧 Configurando proyecto...${NC}"
gcloud config set project $PROJECT_ID

# Habilitar APIs necesarias
echo -e "${BLUE}🔧 Habilitando APIs necesarias...${NC}"
gcloud services enable cloudbuild.googleapis.com
gcloud services enable run.googleapis.com
gcloud services enable containerregistry.googleapis.com

# Construir la imagen
echo -e "${BLUE}🏗 Construyendo imagen Docker...${NC}"
docker build -t $IMAGE_NAME -f Dockerfile.cloudrun .

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ ERROR: Falló la construcción de la imagen${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Imagen construida exitosamente${NC}"
echo ""

# Configurar autenticación para Docker
echo -e "${BLUE}🔐 Configurando autenticación Docker...${NC}"
gcloud auth configure-docker

# Subir imagen a Container Registry
echo -e "${BLUE}📤 Subiendo imagen a Container Registry...${NC}"
docker push $IMAGE_NAME

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ ERROR: Falló la subida de la imagen${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Imagen subida exitosamente${NC}"
echo ""

# Verificar si existen credenciales de Google
if [ ! -f "config/google-credentials.json" ]; then
    echo -e "${YELLOW}⚠ ADVERTENCIA: No se encontró config/google-credentials.json${NC}"
    echo "Necesitarás configurar las credenciales como secret en Cloud Run"
    echo ""
fi

# Deploy a Cloud Run
echo -e "${BLUE}🚀 Desplegando a Cloud Run...${NC}"
gcloud run deploy $SERVICE_NAME \
    --image $IMAGE_NAME \
    --platform managed \
    --region $REGION \
    --allow-unauthenticated \
    --memory 512Mi \
    --cpu 1 \
    --timeout 300 \
    --max-instances 10 \
    --set-env-vars "APIFY_API_TOKEN=$APIFY_API_TOKEN" \
    --set-env-vars "GOOGLE_SHEET_ID=$GOOGLE_SHEET_ID"

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ ERROR: Falló el deployment${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}=========================================="
echo "✅ Deployment completado exitosamente!"
echo -e "==========================================${NC}"
echo ""

# Obtener URL del servicio
SERVICE_URL=$(gcloud run services describe $SERVICE_NAME --platform managed --region $REGION --format 'value(status.url)')

echo -e "${GREEN}🌐 Tu aplicación está disponible en:${NC}"
echo "   $SERVICE_URL"
echo ""
echo -e "${YELLOW}📝 Notas importantes:${NC}"
echo "   1. Configura las credenciales de Google como secret"
echo "   2. Verifica las variables de entorno"
echo "   3. Revisa los logs: gcloud run logs read --service=$SERVICE_NAME"
echo ""
