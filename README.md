# Doc Intelligence API

API robusta em Laravel para o processamento assíncrono de documentos e notas fiscais utilizando Inteligência Artificial. O sistema extrai metadados dos arquivos, gerencia níveis de confiança (confidence level), lida com fluxos de falhas automatizados e suporta intervenção humana para correções.

---

## 🛠 Pré-requisitos

- **PHP** 8.4.25
- **Composer** instalado
- **PostgreSQL** (O banco de dados oficial do projeto, necessário devido ao uso avançado de colunas JSON nas buscas)
- **Extensões PHP:** `pdo_pgsql`, `fileinfo`, `gd`, `curl`, entre outras nativas do Laravel.

---

## 🚀 Como Instalar e Configurar

1. **Clone o repositório e acesse a pasta do projeto:**
   ```bash
   git clone <url-do-repositorio>
   cd doc-intelligence
   ```

2. **Instale as dependências do Composer:**
   ```bash
   composer install
   ```

3. **Configure o arquivo de ambiente:**
   Copie o arquivo de exemplo para criar o seu `.env`:
   ```bash
   cp .env.example .env
   ```

4. **Gere a chave da aplicação:**
   ```bash
   php artisan key:generate
   ```

5. **Crie os links simbólicos de Storage:**
   Isso é **muito importante** para que as imagens processadas sejam acessíveis publicamente.
   ```bash
   php artisan storage:link
   ```

6. **Rode as Migrations do banco de dados:**
   Certifique-se de ter configurado o banco de dados no seu `.env` primeiro.
   ```bash
   php artisan migrate
   ```

7. **Inicie o Servidor e a Fila:**
   Você precisará de dois terminais abertos rodando em paralelo:
   ```bash
   # Terminal 1 - Servidor Web
   php artisan serve

   # Terminal 2 - Fila de Processamento (Jobs)
   php artisan queue:work
   ```

---

## ⚙️ Configurações do `.env`

O sistema possui variáveis de ambiente exclusivas para controlar o motor de IA e facilitar os testes. Configure-as no seu arquivo `.env`:

### Configurações de Banco (Obrigatórias)
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=doc-intelligence
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha
```

### Configurações da IA
```env
# Chave da API da OpenAI para quando for usar em modo real (enviei uma chave de teste no corpo do email, essa chave será válida por 7 dias a contar da data de envio do email)
OPENAI_API_KEY=sk-xxxx...

# Régua de confiança: Se a IA retornar uma confiança menor que esse valor (0 a 1), o arquivo será enviado para triagem humana (failed_file).
AI_MIN_CONFIDENCE_LEVEL=0.7 
```

### Configurações de Mock / Testes
O projeto possui um serviço de Mock embutido para que você possa testar todos os fluxos (sucesso, falhas, quebra de JSON) sem gastar tokens ou bater na API oficial.

```env
# Se 'true', desvia as requisições da OpenAI e usa o AIMockService internamente.
USE_AI_MOCK=true

# Estilo de resposta do Mock (Se USE_AI_MOCK=true):
# 'success'        -> Simula um documento extraído com perfeição (Confiança 0.95). Vai para a pasta `files`.
# 'low_confidence' -> Simula que a IA não entendeu a imagem (Confiança 0.4). Vai para a pasta `failed_file`.
# 'invalid_json'   -> Simula que a IA travou e devolveu um erro de sintaxe. Vai para a pasta `failed_file`.
MOCK_RESPONSE_STYLE=success
```
> **Aviso:** Sempre que você alterar o valor do `MOCK_RESPONSE_STYLE` ou `USE_AI_MOCK` no `.env`, você **precisa reiniciar o comando `php artisan queue:work`**, pois os *workers* mantêm a configuração antiga na memória. Caso não funcione, considere reiniciar também o projeto com **`php artisan serve`**.

---

## 📡 Endpoints da API

Abaixo estão listados todos os endpoints disponíveis na aplicação. Todos retornam JSON.

### 1. Upload de Arquivos
- **Endpoint:** `POST /api/file`
- **O que faz:** Recebe o upload do arquivo (PDF, JPG, PNG). O arquivo é salvo temporariamente na pasta `temp_file` e o processamento dele é despachado para uma fila em *background*.
- **Body (`multipart/form-data`):**
  - `file`: O arquivo físico.
- **Retorno:** Mensagem de sucesso e o identificador temporário.

### 2. Listar Arquivos Processados
- **Endpoint:** `GET /api/file`
- **O que faz:** Retorna todos os documentos que foram processados **com sucesso** (confiança alta), carregando junto todos os metadados extraídos pela IA. Os arquivos listados aqui já se encontram fisicamente na pasta `files`.

### 3. Busca Inteligente de Arquivos
- **Endpoint:** `GET /api/file-search`
- **O que faz:** Permite pesquisar termos específicos dentro do JSON de metadados extraídos (ex: nome, valor, descrição). A busca é *case-insensitive*.
- **Query Params:**
  - `q`: O termo a ser pesquisado (ex: `/api/file-search?q=João Silva`).

### 4. Listar Arquivos com Falha (Baixa Confiança)
- **Endpoint:** `GET /api/failed-file`
- **O que faz:** Retorna os documentos que passaram pela IA, mas não atingiram o nível de confiança mínimo estabelecido, ou tiveram JSON inválido. Estes arquivos ficam retidos na pasta `failed_file` aguardando correção humana.

### 5. Corrigir Arquivo com Falha Manualmente
- **Endpoint:** `POST /api/fix-file/{id}`
- **O que faz:** Endpoint destinado à intervenção humana. Recebe os dados corrigidos do usuário, move o arquivo fisicamente da pasta `failed_file` para a pasta final `files`, transfere o registro no banco de dados para a tabela de sucesso e insere a descrição manualmente aprovada.
- **Body (`application/json`):**
  - `file_name` (string)
  - `description` (string)

### 6. Listar Jobs Travados (Crashes)
- **Endpoint:** `GET /api/failed-jobs`
- **O que faz:** Retorna a lista da tabela nativa do Laravel `failed_jobs`. Útil para identificar arquivos que nem chegaram a ser analisados devido a um erro de sistema ou erro 500 fatal (ex: falha de banco de dados, servidor sem memória, etc).

### 7. Tentar Reprocessar Jobs Travados
- **Endpoint:** `POST /api/failed-jobs/retry`
- **O que faz:** Re-enfileira todos os trabalhos que deram problema (executa o equivalente ao comando `queue:retry all`). Muito útil se a API da OpenAI caiu temporariamente e você quer reprocessar toda a fila atrasada de uma só vez.