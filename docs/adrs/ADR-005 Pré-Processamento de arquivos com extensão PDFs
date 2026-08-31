# ADR-005: Pré-Processamento de arquivos com extensão PDFs

## Status
Aceito (Substitui a abordagem de envio direto de PDFs binários)

## Contexto
Inicialmente, o pipeline previa o envio direto do binário/arquivo PDF para o modelo a IA. No entanto, o envio de arquivos PDF volumosos (com dezenas de páginas ou texto puro digitalizado) para modelos de IA eleva consideravelmente a latência de processamento e o custo de tokens por requisição, além da probabilidade de falhas de processamento já testadas na IA do projeto.

## Decisão
Implementar uma etapa intermediária de pré-processamento no service:
* Extrair o texto do PDF no próprio servidor (utilizando utilitários locais como `pdf-to-text` ou parsers nativos do laravel) antes de invocar a IA.
* Enviar para a IA primariamente o conteúdo textual estruturado para a extração dos campos (RG, CPF, nome, etc.), reservando o envio do documento original apenas para imagens (JPEG/PNG).

## Alternativas Descartadas
* **Envio Integral do PDF como Mídia Multimodal:** Descartado por encarecer o custo por documento e aumentar a latência de processamento em PDFs puramente digitais.
* **OCR Pesado com Tesseract no Backend:** Descartado para não inflar a imagem da aplicação nem consumir excessivamente CPU do servidor durante picos de envio.

## Consequências
* Redução drástica no volume de dados transmitidos e no custo de tokens na API de IA de terceiro.
* Menor tempo médio de resposta para PDFs nativos/gerados digitalmente.
* Necessidade de lidar com PDFs que são apenas imagens (sem texto embutido), sendo necessário análise humana para esses casos.