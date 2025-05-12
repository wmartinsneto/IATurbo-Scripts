# Configuração de Conectividade entre Dify e IATurbo-Scripts

Este documento descreve as alterações feitas para permitir a comunicação entre os containers Docker do Dify e do IATurbo-Scripts.

## Problema

O Dify não conseguia acessar os endpoints do IATurbo-Scripts usando a URL `http://localhost:8080/iptu/api_imovel.php`. Isso ocorria porque o Dify executa o código em um ambiente sandbox que restringe as requisições HTTP.

### Detalhes do Problema

Conforme discutido em [Node HTTP Request run failed: Reached maximum retries (3) for URL](https://github.com/langgenius/dify/discussions/6968), o problema ocorre porque:

1. O código do Dify é executado em um container sandbox
2. O serviço API está em um container separado
3. As requisições HTTP feitas pelo código no sandbox são restritas

## Solução

A solução implementada foi criar uma rede Docker compartilhada entre os dois projetos e configurar os containers para se comunicarem através dessa rede. Além disso, foi necessário usar o endpoint interno do API do Dify (`http://api:5001/v1`) em vez do endpoint externo (`http://nginx:80/v1` ou `http://localhost:8082/v1`).

Esta solução é baseada na discussão [Node HTTP Request run failed: Reached maximum retries (3) for URL](https://github.com/langgenius/dify/discussions/6968).

### Alterações realizadas:

1. **Criação de uma rede Docker compartilhada**:
   ```
   docker network create iaturbo-network
   ```

2. **Configuração do IATurbo-Scripts**:
   - Atualização do arquivo `docker-compose.yml` para usar a rede compartilhada
   - Modificação do arquivo `config.php` para usar o endpoint interno do API do Dify (`http://api:5001/v1`)
   - Atualização da variável de ambiente `DIFY_API_URL` para usar o endpoint interno do API do Dify

3. **Configuração do Dify**:
   - Criação do arquivo `docker-compose.override.yml` para adicionar a rede compartilhada
   - Configuração dos serviços api, worker, web e nginx para usar a rede compartilhada
   - Adição de `extra_hosts` para permitir o acesso a host.docker.internal

### Por que isso funciona?

De acordo com a discussão no GitHub, o Nginx do Dify encaminha as requisições para `/v1` para o serviço API interno (`http://api:5001`). Ao usar diretamente o endpoint interno do API, evitamos as restrições do sandbox do Dify.

## Como testar

1. **Reiniciar os containers**:
   Execute o script `restart-containers.bat` para parar e reiniciar os containers com as novas configurações.

2. **Testar a conectividade**:
   Execute o script `test-connectivity.bat` para verificar se o IATurbo-Scripts pode se comunicar com o Dify.

3. **Testar a conectividade do Dify para o IATurbo-Scripts**:
   Execute o seguinte comando para verificar se o Dify pode se comunicar com o IATurbo-Scripts:
   ```
   docker exec -it dify-api-1 curl -v http://chatbot-iaturbo/iptu/api_imovel.php
   ```

## Resolução de problemas

Se a conectividade ainda não estiver funcionando após a reinicialização dos containers, verifique:

1. **Redes Docker**:
   ```
   docker network ls
   ```
   Verifique se a rede `iaturbo-network` foi criada.

2. **Containers conectados à rede**:
   ```
   docker network inspect iaturbo-network
   ```
   Verifique se os containers do Dify e do IATurbo-Scripts estão conectados à rede.

3. **Logs dos containers**:
   ```
   docker-compose -f C:\dev\git\dify\docker\docker-compose.yaml logs -f
   docker-compose -f C:\dev\git\IATurbo-Scripts\docker-compose.yml logs -f
   ```
   Verifique os logs para identificar possíveis erros.

4. **Configuração de DNS**:
   ```
   docker exec -it dify-api-1 ping chatbot-iaturbo
   docker exec -it chatbot-iaturbo ping nginx
   ```
   Verifique se os containers podem resolver os nomes uns dos outros.
