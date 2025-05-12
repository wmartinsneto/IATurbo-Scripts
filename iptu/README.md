# Sistema de Consulta de IPTU

Este sistema permite a consulta de boletos de IPTU por código do imóvel ou por CPF/CNPJ do proprietário.

## Estrutura do Sistema

O sistema é composto pelos seguintes arquivos:

- `index.php`: Interface web para consulta de IPTU
- `api_imovel.php`: API para consulta por código do imóvel
- `api_proprietario.php`: API para consulta por CPF/CNPJ do proprietário
- `dados.json`: Base de dados com informações de imóveis, proprietários e boletos
- `boletos/`: Diretório que contém:
  - `gerar_boleto.php`: API para geração e download de boletos em formato JSON
  - `exemplo_boleto.html`: Exemplo visual de um boleto de IPTU

## Como Usar

### Interface Web

1. Acesse o arquivo `index.php` em seu navegador
2. Escolha o tipo de busca (por imóvel ou por proprietário)
3. Informe o código do imóvel ou o CPF/CNPJ do proprietário
4. Clique em "Consultar"
5. Visualize os detalhes do IPTU e baixe o boleto se necessário

### API de Consulta por Imóvel

Endpoint: `api_imovel.php`

Parâmetros:
- `codigo`: Código do imóvel (obrigatório)

Exemplo de uso:
```
GET api_imovel.php?codigo=10001
```

Resposta:
```json
{
  "imovel": {
    "codigo": 10001,
    "endereco": "Rua das Flores, 123",
    "bairro": "Centro",
    "area": 120,
    "tipo": "Apartamento"
  },
  "boleto": {
    "codigo_imovel": 10001,
    "exercicio": 2025,
    "valor": 1250.75,
    "vencimento": "2025-05-15",
    "codigo_barras": "85810000012-5 50750000001-2 00010001202-5 50515000000-0",
    "link_boleto": "boletos/gerar_boleto.php?codigo=10001"
  },
  "proprietario": {
    "nome": "João da Silva",
    "id": "123.456.789-00",
    "tipo": "cpf"
  }
}
```

### API para Geração de Boletos

Endpoint: `boletos/gerar_boleto.php`

Parâmetros:
- `codigo`: Código do imóvel (obrigatório)

Exemplo de uso:
```
GET boletos/gerar_boleto.php?codigo=10001
```

Resposta:
Um arquivo JSON para download contendo os dados do imóvel, proprietário e boleto.

### API de Consulta por Proprietário

Endpoint: `api_proprietario.php`

Parâmetros:
- `id`: CPF ou CNPJ do proprietário (obrigatório)

Exemplo de uso:
```
GET api_proprietario.php?id=123.456.789-00
```

Resposta:
```json
{
  "proprietario": {
    "nome": "João da Silva",
    "id": "123.456.789-00",
    "tipo": "cpf"
  },
  "imoveis": [
    {
      "imovel": {
        "codigo": 10001,
        "endereco": "Rua das Flores, 123",
        "bairro": "Centro",
        "area": 120,
        "tipo": "Apartamento"
      },
      "boleto": {
        "codigo_imovel": 10001,
        "exercicio": 2025,
        "valor": 1250.75,
        "vencimento": "2025-05-15",
        "codigo_barras": "85810000012-5 50750000001-2 00010001202-5 50515000000-0",
        "link_boleto": "boletos/gerar_boleto.php?codigo=10001"
      },
      "tipo_imovel": "residencial"
    },
    {
      "imovel": {
        "codigo": 10002,
        "endereco": "Av. Principal, 456",
        "bairro": "Jardim Europa",
        "area": 250,
        "tipo": "Casa"
      },
      "boleto": {
        "codigo_imovel": 10002,
        "exercicio": 2025,
        "valor": 2350.50,
        "vencimento": "2025-05-15",
        "codigo_barras": "85810000023-5 50500000001-2 00010002202-5 50515000000-0",
        "link_boleto": "boletos/gerar_boleto.php?codigo=10002"
      },
      "tipo_imovel": "residencial"
    }
  ],
  "total_imoveis": 2
}
```

## Dados de Teste

### Imóveis Residenciais
- Códigos: 10001 a 10015

### Imóveis Comerciais
- Códigos: 20001 a 20020

### Proprietários (CPF)
- João da Silva: 123.456.789-00
- Maria Oliveira: 987.654.321-00
- Pedro Santos: 111.222.333-44
- Ana Pereira: 555.666.777-88
- Carlos Ferreira: 222.333.444-55
- Mariana Costa: 777.888.999-00
- Roberto Almeida: 444.555.666-77
- Juliana Martins: 888.999.000-11
- Fernando Souza: 333.444.555-66
- Luciana Rodrigues: 666.777.888-99

### Proprietários (CNPJ)
- Empresa ABC Ltda: 12.345.678/0001-90
- Comércio XYZ S.A.: 98.765.432/0001-10
- Indústria Beta Ltda: 11.222.333/0001-44
- Construtora Omega S.A.: 55.666.777/0001-88
- Supermercados Delta Ltda: 22.333.444/0001-55
- Farmácias Gama S.A.: 77.888.999/0001-00
- Restaurantes Sigma Ltda: 44.555.666/0001-77
- Hotéis Alfa S.A.: 88.999.000/0001-11
- Transportes Ômega Ltda: 33.444.555/0001-66
- Consultoria Zeta S.A.: 66.777.888/0001-99

## Documentação da API

O sistema possui uma documentação completa da API no formato OpenAPI (Swagger):

- `swagger.json`: Arquivo de especificação OpenAPI 3.0
- `swagger-ui.html`: Interface web para visualizar e testar a API

Para acessar a documentação interativa, abra o arquivo `swagger-ui.html` em seu navegador.

## Requisitos

- PHP 7.4 ou superior
- Servidor web (Apache, Nginx, etc.)
- Navegador web moderno

## Observações

- Este é um sistema de demonstração e os boletos são fictícios
- O sistema gera automaticamente arquivos JSON para download dos boletos
- Para um ambiente de produção, seria necessário implementar autenticação e outras medidas de segurança
