# Doc Intelligence API

API em Laravel que implementa a **Trilha A** (backend) para o processamento assíncrono de documentos utilizando Inteligência Artificial. O sistema extrai metadados dos arquivos, gerencia níveis de confiança (confidence level), lida com fluxos de falhas automatizados e suporta intervenção humana para correções.

## Como foi pensado
A fatial vertical principal desta aplicação é: 
1. Enviar um arquivo PDF/Imagem para a API
2. Salvar o arquivo temporariamente na pasta `temp_file`
3. Despachar o arquivo para a fila de processamento (jobs) assíncrono.
4. O job envia o arquivo para o modelo de IA, que extrai os metadados do arquivo e estabele uma **NOTA DE CONFIANÇA (0 a 1).**
5. Caso a IA não consiga extrair os metadados com alta confiança, o arquivo é movido para a pasta `failed_file` e fica aguardando intervenção humana.
6. Caso a IA consiga extrair os metadados com alta confiança, o arquivo é movido para a pasta `files`. Já devidamente **RENOMEADO** seguindo um padrão estabelecido no **SYSTEM PROMPT.**

---

## Como funciona a Nota de Confiança
Essa nota de confiança é estabelecida no arquivo `.env` na variável AI_MIN_CONFIDENCE_LEVEL. O valor padrão é 0.7.
Quanto maior for a nota de confiança maior será a precisão do modelo de IA em extrair os metadados do documento. Consequentemente, menor será a chance de o arquivo ser movido para a pasta `failed_file`.

---

## System Prompt
Aqui está o modelo de System Prompt utilizado na API da NVIDIA, seguindo regras de padronização para o `file_name` e `metadados`. Embora a implementação de conexão com uma IA real não fosse requisito essencial na entrega, eu quis me desafiar testando diferentes modelos de LLMs para saber a viabilidade do projeto em um cenário real. *Consulte a aba de configuração da IA neste README para ter uma noção de como funcionaria a integração com uma IA real.*

Caso você queira testar o projeto com dados mockados, este System Prompt será irrelevante, pois o sistema irá retornar dados previamente estabelecidos no sistema. *Consulte a aba de configuração de dados mockados no README para mais informações.*

> **Aviso:** Este System Prompt se encaixa apenas em ambientes que utiliza a IA da NVIDIA, isso significa que se você estiver usando outra IA você precisará adapta-lo.

```
You are a specialized document extraction engine. Your sole objective is to analyze the provided document and extract structured data strictly adhering to the JSON schema defined below.

### OPERATIONAL RULES
1. OUTPUT FORMAT: Respond ONLY with a single, raw, valid JSON object. Do not include markdown code fences (e.g., ```json or ```), introduction, commentary, or trailing text.
2. LANGUAGE: The field names (keys) must remain in English as defined in the schema. All extracted values, descriptions, and dynamic metadata values must be in Brazilian Portuguese (pt-BR).
3. MISSING, EMPTY, OR UNREADABLE DATA: 
   - If a specific field, form box, or value is missing, unfilled, blank, illegible, or obscured, assign its value strictly as `null`. Never fabricate, guess, or hallucinate data.
   - If the document is entirely blank, contains only empty form boxes/templates, or has no extractable data:
     * Set `metadata` to an empty object `{}`.
     * Describe the state in `description` (e.g., "Documento em branco, modelo não preenchido ou sem dados extraíveis.").
     * Use generic placeholders for `file_name` (e.g., `documento_vazio_nao_identificado_[date]`).
4. FRAUD, ANOMALY & MANIPULATION DETECTION:
   - Scrutinize the document for visual or logical manipulation: mismatched fonts, misaligned text, irregular artifacting around numbers/names, patched backgrounds, or abnormal spacing.
   - Detect evidently fake numbers, placeholders, or sequence patterns (e.g., sequential/repeated IDs like `123456789`, `000.000.000-00`, `11.111.111/1111-11`, impossible issue dates, or invalid mathematical totals/check-digits).
5. CONFIDENCE SCORING: Strictly evaluate document integrity, OCR clarity, field completeness, and authenticity markers. Apply severe penalties to `confidence_level` under the following conditions:
   - Empty documents, blank pages, or documents containing predominantly blank/unfilled fields or empty form boxes.
   - Documents with visibly manipulated regions, digital tampering artifacts, or mismatched typography.
   - Documents presenting clearly fake, sequential, placeholder, or structurally invalid identifiers (CPFs, CNPJs, invoice IDs, barcodes).

### JSON SCHEMA
{
  "file_name": "[type]_[identifier]_[date]",
  "metadata": {
    "description": "string (A concise description in Portuguese summarizing the document type, main subject, parties involved, and explicitly noting any observed anomalies, blank fields, or signs of tampering)",
    "dynamic_field_1": "value",
    "dynamic_field_2": 0.00
  },
  "confidence_level": 0.00
}

### FIELD SPECIFICATIONS
- "file_name": Must follow the naming standard `[type]_[identifier]_[date]`.
  * `[type]`: Standardized document category in lowercase snake_case (e.g., `nota_fiscal`, `contrato_prestacao_servicos`, `comprovante_pagamento`, `relatorio_medico`, or `documento_vazio` if no type can be identified).
  * `[identifier]`: Primary unique identifier such as a sanitized document number, invoice ID, CPF/CNPJ, or primary party name (alphanumerics only, separated by underscores). If unidentifiable or fake, use `nao_identificado` or `suspeita_invalido`.
  * `[date]`: Must be the current execution date/time representing "today", formatted strictly as `YYYY-MM-DD` (derived from the `.date("Y-m-d")` format, using hyphens or underscores to maintain valid filename syntax).
- "description": High-level synthesis in Portuguese detailing the document purpose and key entities, or stating clearly if the document contains blank boxes, signs of digital manipulation, or invalid placeholder numbers.
- "metadata": Key-value pairs extracted dynamically from the document.
  * Extract all relevant entities, including but not limited to: full names, corporate names, tax IDs (CPF/CNPJ), document-internal dates (in `YYYY-MM-DD` format), currency values (as numerical floats), line items, and addresses.
  * Set unreadable, missing, or blank box values to `null`.
  * Return `{}` if no valid entities exist.
- "confidence_level": A float between `0.0` and `1.0` representing total extraction certainty and document validity:
  * `1.0`: Completely legible, verified checksums/totals, fully filled fields, zero manipulation signs or ambiguities.
  * `0.7 - 0.9`: High legibility and authenticity, with minor OCR noise or non-critical omitted secondary fields.
  * `0.3 - 0.6`: Degraded OCR, partial form boxes left blank, or non-critical numerical discrepancies.
  * `0.0 - 0.2`: Empty/blank documents, unfilled form templates, evident signs of digital tampering/alteration, or clearly fake/sequential identifiers.
        
        You MUST use the current date in the file_name, which is: "' . date('Y-m-d') . '" at the end of the file name.
```
---

## 📚 Documentos de Tomadas de Decisão, Diagramas e Histórico de Prompts
No diretório `/docs` na raiz do projeto está todos os documentos de tomadas de decisões (ADRs), assim como os prompts utilizados neste projeto. Considere ler os ADR's em ordem, em determinado momento algumas decisões precisaram ser repensadas. Me aventurei um pouco em criar diagramas para auxiliar no entendimento do banco de dados e do projeto.

---

## 🛠 Pré-requisitos para Instalação do Projeto

- **PHP** 8.4.25
- **Composer** instalado
- **PostgreSQL** (O banco de dados oficial do projeto, necessário devido ao uso avançado de colunas JSON nas buscas)
- **Extensões PHP:** `pdo_pgsql`, `fileinfo`, `gd`, `curl`, entre outras nativas do Laravel.

---

## 🤓☝️ Como Instalar e Configurar

1. **Clone o repositório e acesse a pasta do projeto:**
   ```bash
   git clone https://github.com/Rigobertto/doc-intelligence.git
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
Chave da API (eu enviei uma chave para testes no corpo do email, essa chave será válida por 7 dias a contar da data de envio do email, em caso de dúvida entre em contato para fazer uma nova chave.)
```env
AI_API_KEY="sk-xxxx..."

# Régua de confiança: Se a IA retornar uma confiança menor que esse valor (0 a 1), o arquivo será enviado para triagem humana (failed_file).
AI_MIN_CONFIDENCE_LEVEL=0.7 
```

### Configurações de Mock / Testes
O projeto possui um serviço de Mock embutido para que você possa testar todos os fluxos (sucesso, falhas, quebra de JSON) sem gastar tokens ou bater na API oficial.

```env
# Se 'true', desvia as requisições da IA da NVidia e usa o AIMockService internamente.
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
- **O que faz:** Re-enfileira todos os trabalhos que deram problema (executa o equivalente ao comando `queue:retry all`). Muito útil se a API da Nvidia caiu temporariamente e você quer reprocessar toda a fila atrasada de uma só vez.

### 8. Excluir Arquivo Processado
- **Endpoint:** `DELETE /api/file/{id}`
- **O que faz:** Remove fisicamente o documento da pasta `files` e exclui o registro de sucesso (com seus metadados) no banco de dados.

### 9. Excluir Arquivo com Falha
- **Endpoint:** `DELETE /api/failed-file/{id}`
- **O que faz:** Remove fisicamente o documento da pasta `failed_file` e exclui o registro de falha no banco de dados.

---

## 🧪 Testes Automatizados

O sistema foi extensivamente testado com o framework **Pest**. As suítes de testes cobrem toda a camada da API (Feature Tests) e a lógica de serviços e filas (Unit Tests), fazendo o *mock* correto das respostas HTTP da Nvidia, dos bancos e dos storages para não onerar APIs externas nem o disco local durante os testes.

Para rodar todos os testes de uma só vez, utilize o comando Artisan na raiz do projeto:

```bash
php artisan test
```

Se desejar executar apenas um grupo específico de testes ou arquivos individuais, você pode utilizar filtros:

- **Executar somente Testes da API (Feature):**
  ```bash
  php artisan test --filter=Api
  ```
- **Executar somente Testes Unitários (Serviços e Jobs):**
  ```bash
  php artisan test --testsuite=Unit
  ```
- **Executar um arquivo de teste específico:**
  ```bash
  php artisan test --filter=AIServiceTest
  ```