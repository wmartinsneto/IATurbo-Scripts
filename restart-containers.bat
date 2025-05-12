@echo off
echo Parando e removendo containers existentes...
cd C:\dev\git\dify\docker
docker-compose down
cd C:\dev\git\IATurbo-Scripts
docker-compose down

echo Reconstruindo e iniciando os containers...
cd C:\dev\git\dify\docker
docker-compose up -d
cd C:\dev\git\IATurbo-Scripts
docker-compose up -d

echo Containers reiniciados com sucesso!
echo.
echo Para verificar os logs do Dify:
echo docker-compose -f C:\dev\git\dify\docker\docker-compose.yaml logs -f
echo.
echo Para verificar os logs do IATurbo-Scripts:
echo docker-compose -f C:\dev\git\IATurbo-Scripts\docker-compose.yml logs -f
