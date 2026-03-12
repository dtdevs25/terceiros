FROM php:8.2-apache

# Instalar extensões necessárias
RUN docker-php-ext-install pdo pdo_mysql

# Habilitar o mod_rewrite do Apache
RUN a2enmod rewrite

# Copiar os arquivos da aplicação
COPY . /var/www/html/

# Criar diretórios necessários e definir permissões
RUN mkdir -p /var/www/html/uploads /var/www/html/docs /var/www/html/assinaturas \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/docs /var/www/html/assinaturas \
    && chmod -R 775 /var/www/html/uploads /var/www/html/docs /var/www/html/assinaturas

# Configurar o Apache para permitir .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Definir o diretório de trabalho
WORKDIR /var/www/html/

# Expor a porta 80
EXPOSE 80
