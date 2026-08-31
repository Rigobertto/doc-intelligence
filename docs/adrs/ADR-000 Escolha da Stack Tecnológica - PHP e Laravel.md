# ADR-000: Escolha da Stack Tecnológica — PHP e Laravel 13

## Status
Aceito

## Contexto
O serviço DOC Intelligence recebe imagens e PDFs enviados pelo atendimento (com nomes despadronizados e fotos diretas da câmera), contendo dados pessoais e sensíveis. A aplicação precisa absorver picos matinais de mais de 800 requisições.

## Decisão
Adotar **PHP** com **Laravel 13** para a Trilha A (Back-end).
* **Abstração de Armazenamento (Flysystem Integrado):** O Laravel isola o armazenamento de arquivos via *disks*, permitindo usar `local` privado para desenvolvimento e testes com geração de arquivos temporários, facilitando a transição futura para `s3` ou armazenamentos corporativos sem alterar a lógica de negócio.
* **Segurança e Privacidade Nativa:** Suporte a discos com visibilidade privada (`0600`/`0700`) e geração de URLs assinadas/temporárias (`temporaryUrl`), garantindo que documentos com dados pessoais e sensíveis não fiquem expostos publicamente na web.
* **Mensageria e Filas Nativas:** Filas assíncronas, *retries* e tratamento de falhas prontos para amortecer picos e a instabilidade da IA sem travar a API.
* **Testabilidade e Injeção:** `Storage::fake()` e `UploadedFile::fake()` para testes automatizados de upload sem I/O real em disco, além de *Service Container* para injetar o dublê da IA.

## Alternativas Descartadas
* **Node.js (NestJS) / Python (FastAPI):** Exigiriam montagem manual de bibliotecas de fila, abstração de *storage*, upload multipart e ORM, consumindo tempo excessivo de configuração no prazo curto.
* **Go (Golang):** Alta performance, mas demandaria muito código *boilerplate* para gerenciamento seguro de *storage*, validações e persistência.

## Consequências
* Rapidez e consistência na entrega da fatia vertical com foco no desenho arquitetural.
* Isolamento total do provedor de IA (*Strategy*) e do local de armazenamento dos arquivos (*Storage Disks*).
* Necessidade de instruções claras de ambiente/Docker no `README` para execução do projeto.