#!/bin/bash
set -e

# Executar o script de inicialização PHP
echo "Executando script de inicialização..."
php /var/www/html/init.php

# Verificar se há atualizações de permissões necessárias
echo "Verificando permissões..."
chown -R www-data:www-data /var/www/html/logs /var/www/html/dify/completed /var/www/html/dify/pending /var/www/html/speech/output /var/www/html/speech/input

# Exibir mensagem de conclusão
echo "Inicialização concluída. Iniciando servidor Apache..."
echo "---------------------------------------------------"
echo "Chatbot IATurbo está pronto!"
echo "Acesse: http://localhost:8080 (ou a porta configurada)"
echo "---------------------------------------------------"

# Executar o comando fornecido (apache2-foreground)
exec "$@"
