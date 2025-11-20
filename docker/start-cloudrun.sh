#!/bin/bash
set -e

echo "🚀 Iniciando aplicación en Cloud Run..."
echo "📍 Puerto asignado: $PORT"

# Reemplazar el puerto en la configuración de Apache
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf

# Reemplazar el puerto en el VirtualHost
sed -i "s/<VirtualHost \*:8080>/<VirtualHost *:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

# Configurar seguridad de Apache (fuera de VirtualHost)
echo "ServerTokens Prod" >> /etc/apache2/apache2.conf
echo "ServerSignature Off" >> /etc/apache2/apache2.conf

# Habilitar headers module
a2enmod headers

# Verificar que los directorios existan
mkdir -p /var/www/html/uploads
chown -R www-data:www-data /var/www/html/uploads

echo "✅ Configuración completada"
echo "🌐 Servidor iniciando en puerto $PORT..."

# Iniciar Apache en primer plano
exec apache2-foreground
