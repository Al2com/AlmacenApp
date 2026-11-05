# Imagen del backend (Laravel) para desarrollo local.
# El código y vendor/ se montan como volumen desde docker-compose.
FROM php:8.2-cli

# Extensiones que Laravel necesita para hablar con MySQL.
RUN docker-php-ext-install pdo_mysql bcmath

WORKDIR /app

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
