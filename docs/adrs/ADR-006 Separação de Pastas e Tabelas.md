# ADR 0006: Separação de Arquivos em Pastas Físicas e Tabelas Distintas

## 1. Contexto e Problema
No processo de recebimento, análise (via Inteligência Artificial) e extração de metadados de documentos (identidades, notas, etc.), lidamos com o fluxo de sucesso, onde a IA consegue extrair tudo com alto grau de confiança, e com o fluxo de falhas (JSON quebrado ou confiança baixa). O upload do arquivo ocorre na porta de entrada da API e o processamento ocorre de forma assíncrona.

É necessário decidir como organizar fisicamente os arquivos de imagem/PDF no disco e como modelar isso no banco de dados, sabendo que haveriam estados temporários e estados de falha que dependem de intervenção humana.

## 2. Decisões Arquiteturais

### 2.1 Separação Física (Discos/Storage)
Decidido usar três *Storages* (pastas físicas) completamente isoladas na aplicação Laravel:
- **`temp_file`**: Pasta temporária, isolada do acesso público. O arquivo entra por aqui via API (`POST /api/file`) antes do processamento assíncrono começar. Previne o acúmulo de arquivos órfãos não processados junto de arquivos válidos.
- **`files`**: Pasta definitiva, acessível publicamente via link simbólico. Armazena os documentos onde a IA teve alta confiança e sucesso na extração do metadado.
- **`failed_file`**: Pasta reservada para os documentos nos quais o LLM teve baixa confiança (`< 0.7`) ou retornou uma resposta sintaticamente inválida (JSON quebrado). Fica aguardando ação corretiva humana antes de ser movida para a pasta definitiva.

### 2.2 Separação Lógica (Tabelas do Banco de Dados)
Em vez de utilizarmos uma única tabela `files` com uma coluna de enum `status` (`pending`, `success`, `failed`), foi decidido separar a modelagem:
- A tabela **`files`** (junto com `file_meta_data`) será responsável APENAS pelos sucessos limpos e corrigidos.
- A tabela **`failed_files`** (junto com `failed_file_meta_data`) será responsável por gerenciar a fila de documentos com erro/revisão pendente.

## 3. Consequências

### Vantagens

- **Facilidade na Triagem Humana:** A interface de atendentes focará unicamente nos endpoints atrelados à `failed_files`, desacoplando completamente a carga de trabalho de leitura da base final.
- **Organização no Disco:** Fisicamente é muito fácil olhar o volume de arquivos dentro de `storage/app/public/failed_file` para monitorar retenções.
- **Segurança** O arquivo conrrompido ou cru não é salvo no diretório público imediatamente. Ele aguarda no `temp_file` para só então ser migrado ao `files` limpo, caso tenha sido classificado corretamente.

### Desvantagens
- **Mover Arquivos entre Discos:** Na intervenção humana para salvar a correção (`/api/fix-file`), o sistema precisa mover fisicamente os arquivos (`Storage::move` de uma pasta pra outra) em vez de apenas fazer um `$file->update(['status' => 'success'])`.
