# ADR-002: Adiamento do Mecanismo de Controle de Concorrência na Conferência Humana

## Status
Aceito

## Contexto
O fluxo operacional do projeto prevê que dois ou mais atendentes possam abrir a fila de conferência humana simultaneamente. Sem um mecanismo de controle de concorrência, existe o risco de condição de corrida, onde o trabalho de correção e salvamento de um operador sobrescreve as alterações feitas por outro no mesmo documento.

## Decisão
* A aplicação precisaria de uma lógica inicial de perfil do usuário, tais como autenticação de login e senha. Embora essa implementação seja possível pela agilidade da stack utilizada, a implementação principal da fatia vertical pode ser afetada.
* A aplicação permitirá o acesso e edição concomitante de múltiplos operadores sobre o mesmo documento sem emitir bloqueios de interface ou travas de transação.
* O risco é explicitamente documentado como um débito técnico assumido para priorizar o fluxo principal de recepção, enfileiramento, dublê de IA e persistência básica.

## Alternativas para implementação futura
* **Travamento Pessimista (*Pessimistic Locking* via `lockForUpdate`):** Bloquearia a linha do registro no banco durante a transação, mas demandaria controle fino de timeouts de conexão e liberação de locks em sessões HTTP de longa duração, aumentando a complexidade da entrega inicial.
* **Travamento Otimista (*Optimistic Locking* via coluna de versão/timestamp):** Rejeitaria a atualização com erro `409 Conflict` caso o registro fosse alterado antes do envio, mas exigiria tratamento específico de telas e retentativas na camada cliente.

## Consequências
* Redução da complexidade de desenvolvimento e foco estrito no caminho crítico exigido para a implementação vertical de ponta a ponta.
* Risco pontual de sobrescrita acidental de dados caso múltiplos operadores revisem o mesmo documento concomitantemente em ambiente produtivo.