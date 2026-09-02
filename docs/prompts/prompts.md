# HISTÓRICO DE PROMPTS:

---

Atue como um especialista em Laravel e configure um novo disco local no arquivo config/filesystems.php para gerenciar os documentos do projeto. Defina o diretório root apropriado, explique como expor publicamente esses arquivos utilizando o comando php artisan storage:link

---

Atue como um especialista em Laravel e crie um endpoint POST /file para o projeto que valide a extensão de arquivos (PDF, PNG, JPEG). O controller deve salvar o upload no disco local configurado e capturar o caminho gerado como identificador. Em seguida, faça o dispatch de um Job para processamento assíncrono passando esse identificador como parâmetro. Ao estruturar a classe do Job, configure explicitamente a quantidade máxima de tentativas de execução utilizando o atributo #[Tries]. deve ser no máximo 3 tentativas.

---

Qual variável de ambiente deve ser configurada no php.ini para permitir o sistema de armazenar arquivos temporariamente?

---

Atue como especialista em Laravel e modifique o Job ProcessUploadedFile.php adicionando um Enum PHP com os status em inglês: 'Pending', 'Processing' e 'Failed'. Atualize a lógica da classe para garantir que o estado inicial seja sempre 'Pending'. 

---

Atue como especialista em Laravel e crie os models e migrations para File e FileMetaData.
Para o model File, defina os campos de tabela url e file_name.
Para FileMetaData, crie file_id (foreign key vinculada a files) e data (do tipo json).

---

Atue como especialista em Laravel e crie uma classe AIService. O serviço deve configurar a conexão com uma LLM lendo as variáveis do .env (AI_URL, AI_API_KEY, AI_MODEL) e conter um system prompt interno. Implemente o método document_analiser, que recebe um arquivo, faz a chamada à LLM e utiliza a resposta para persistir e retornar o model File junto com seus respectivos dados salvos na relação FileMetaData (em formato JSON).

---

Alternativas para que na classe AIService consiga transformar o arquivo do tipo pdf em texto e em seguida enviar o conteúdo em texto para a IA categorizar. Caso seja uma imagem ele deve ser enviado o conteúdo do arquivo sem transformação.

---

Atue como especialista em Laravel e atualize o método handle do Job ProcessUploadedFile. Injete o AIService e chame o método document_analiser passando o arquivo armazenado para análise. Atualize o Enum de status do Job para 'Processing' antes de iniciar o serviço. Utilize um bloco try/catch para garantir que, em caso de erro na LLM, o status seja alterado para 'Failed'.

---

Atue como especialista em Laravel e crie um system prompt estrito para extração documental, forçando saída exclusiva em JSON válido (sem markdown ou explicações). O schema deve iniciar obrigatoriamente pela chave "file_name" no padrão [type]_[indentifier]_[data], seguido de "description", o objeto "metadata" (com campos dinâmicos extraídos como nomes, números, datas e valores) e "confidence_level" (float de 0.0 a 1.0). Instrua o modelo a retornar campos ilegíveis como null e a reduzir a confiança caso identifique inconsistências. O prompt deve ser em inglês e retornar valores em português.

---

Utilizando o mesmo escopo do model e tabela de file e file_meta_data, crie os models chamados failed_file e failed_file_metada e suas respectivas tabelas. 

---

Já criei a requisição do tipo GET que retorna os arquivos que foram salvos dentro da tabela failed_file para avaliação humana. Para trabalhar juntamente com este endpoint crie um outro endpoint auxiliar chamado /fix-file do tipo POST. Onde o usuário poderá de forma manual inserir o file name do arquivo e a description. Ao fazer isso o arquivo sairá da tabela failed_file e irá para a tabela file, pois ele foi corrigido.

---

Quando o arquivo for movido para file, no metadata deve ficar apenas a description que o usuario passou pelo json da rota fix-file

---

A description deve ir para dentro de um objeto metadata no json, para ficar similar a estrutura dos arquivos processados. exemplo
{"file_name":"teste","metadata":{"description":"teste"}}

---

Atue como especialista em Laravel e crie o código para gerenciar falhas de jobs.
Contexto: Arquivos na fila falham por problemas externos na API da IA (falta de tokens/créditos) e vão para a tabela failed_job.
Necessidade 1: Crie um endpoint GET que liste os arquivos retidos nesta tabela de falhas.
Necessidade 2: Crie um endpoint POST que acione o reprocessamento (retry) desses arquivos falhos. 
Forneça a estrutura de rotas e a lógica dos controllers aplicando boas práticas.

---

Atue como especialista em Laravel e crie um endpoint GET /file-search no api.php com seu respectivo método no FileController. A busca deve filtrar os models File consultando termos específicos dentro da coluna JSON data do relacionamento FileMetaData. a consulta precisa ser case-insensitive. Retorne os arquivos correspondentes com a relação de metadados carregada.

---

Agora que temos a pasta temp_file destinada a aquivos temporários que serão processados. É necessário ter outra pasta chamada files para os arquivos que já foram processados no AIService e renomeados.

---

Preciso atualizar AIMockService para Mockar respostas do processamento da IA utilizada na AIService. Para alternar o uso da AIService entre AIMockService eu criei uma variável no .env chamada USE_AI_MOCK que por padrão será true. Além disso, uma outra variável no .env é necessária para alterar os estilos de respostas mockadas que o usuário gostaria de testar na AIMockService.

---

Nos seguintes casos, considere os exemplos. Devo renomear como um file_name padrão de invalido ou torná-lo null para que não entre na primeira condição do IF de falha?
$newFileName=$metadataArray['file_name'] ?? "documento_mockado_invalido_{$random}";
ou 
$newFileName = $metadataArray['file_name'] ?? null

---

No Laravel como posso tornar os discos locais file e failed_file da pasta storage como arquivos públicos para que eu possa acessá-los a partir do link?

---

Como especialista em Laravel verifique se a pasta failed_file está linkado para a pasta public. Se não, explique porque está ocorrendo erro 404 ao tentar acessar links de arquivos no disco file

---

Em um cenário de uso da IA real, preciso implementar formas de tratar arquivos que falharam no job e foram parar no failed_job. Quais alternativas eu posso considerar para esse tratamento?

---

Atue como especialista em Laravel e crie 2 endpoints para limpar arquivos de failed_file e file, assim como seus respectivos registros no banco de dados.

---

Para implementação de testes de integração, que alternativas eu posso considerar para obter a maior cobertura sem precisar utilizar arquivos reais e encher a memória no disco?

---

Atue como especialista em Laravel e crie Factories para os modelos File e FailedFile.

---

Para testes unitários / serviços utilize a Smalot\PdfParser injetada para que os testes não dependam de arquivos complexos no disco. O plano de implementação deve considerar a maior cobertura de código possível nos services de AIService e AIServiceMock.

---
