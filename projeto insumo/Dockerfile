# Dockerfile para app PHP + Apache com extensões necessárias
FROM mirror.gcr.io/library/php:8.2-apache

# Dependências do sistema e extensões PHP (pdo_mysql, zip)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       libzip-dev unzip git \
    && docker-php-ext-install pdo_mysql zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Copiar composer do container oficial
COPY --from=mirror.gcr.io/library/composer:2 /usr/bin/composer /usr/bin/composer

# Definir diretório de trabalho
WORKDIR /var/www/html

# Copiar código da aplicação
COPY . /var/www/html

# Instalar dependências PHP (sem dev)
RUN composer install --no-dev --no-interaction --prefer-dist || true

# Garantir diretórios de upload existentes e graváveis
RUN mkdir -p assets/uploads \
    && chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 775 {} \; \
    && find /var/www/html -type f -exec chmod 664 {} \;

# Expor porta padrão do Apache
EXPOSE 80

# Apache roda por padrão via imagem base
