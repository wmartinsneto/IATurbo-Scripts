FROM php:8.0-apache

# Definir variáveis de ambiente
ENV DEBIAN_FRONTEND=noninteractive

# Instalar dependências necessárias
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libonig-dev \
    zip \
    unzip \
    && docker-php-ext-install curl mbstring

# Configurar Apache para processar .htaccess
RUN a2enmod rewrite

# Copiar aplicação
COPY . /var/www/html/

# Criar diretórios necessários e definir permissões
RUN mkdir -p /var/www/html/logs \
    /var/www/html/dify/completed \
    /var/www/html/dify/pending \
    /var/www/html/speech/output \
    /var/www/html/speech/input \
    /var/www/html/speech/logs \
    /var/www/html/public/images \
    && chmod -R 775 /var/www/html \
    && chown -R www-data:www-data /var/www/html

# Adicionar script de inicialização
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expor porta
EXPOSE 80

# Execução do script de inicialização
ENTRYPOINT ["docker-entrypoint.sh"]

# Iniciar Apache
CMD ["apache2-foreground"]
