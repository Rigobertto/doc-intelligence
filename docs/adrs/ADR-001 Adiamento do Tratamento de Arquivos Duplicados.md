# ADR-001: Adiamento do Tratamento de Duplicidade de Arquivos

## Status
Aceito

## Contexto
No fluxo operacional, o mesmo documento é reenviado com frequência devido à insegurança de clientes ou precaução do atendimento. Como a classificação e extração via IA de terceiro é cobrada por requisição, processar arquivos idênticos gera desperdício de recursos e custos desnecessários. 

## Decisão
Adiar a implementação da verificação e descarte de duplicidade de arquivos nesta primeira entrega:
* O sistema aceitará e processará arquivos repetidos de forma redundante na prova de conceito.
* O risco é formalmente registrado como débito técnico consciente para viabilizar a entrega do fluxo principal (ingestão, fila assíncrona, dublê de IA e persistência).

## Alternativas Descartadas
* **Cálculo de Hash SHA-256 no Upload:** Bloquearia envios idênticos imediatamente na API via banco de dados, mas demandaria lógica adicional de idempotência e respostas específicas de contrato que desviariam o foco da fatia vertical central.
* **Deduplicação Assíncrona no Worker:** Identificar arquivos repetidos antes de chamar a IA, descartada momentaneamente para manter o pipeline de mensageria o mais simples e direto possível.

## Consequências
* Foco estrito no caminho crítico exigido para a Trilha A sem dispersão de escopo.
* Possibilidade temporária de reenvios gerarem chamadas duplicadas e consumo redundante de recursos em um cenário com IA real.
* **Plano de Mitigação Futuro:** Inclusão da coluna `hash_file` na tabela principal e criação de um *FormRequest* / *Middleware* para interceptar requisições com *hash* já existente antes do enfileiramento.