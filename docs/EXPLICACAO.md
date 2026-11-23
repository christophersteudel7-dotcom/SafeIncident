# SafeIncident

## O problema e o público

Quando uma ocorrência fica dividida entre e-mails e planilhas, a equipe precisa reconstruir o que aconteceu antes de responder a perguntas simples. O incidente ainda está aberto? Qual sistema foi afetado? Quando foi encerrado? O SafeIncident reúne essas respostas em uma ficha e usa os mesmos registros para compor o dashboard e os relatórios.

O projeto é uma aplicação web acadêmica de Christopher Steudel, desenvolvida para profissionais de TI e Segurança da Informação. Esta versão documenta a V1 com as correções dos Lotes 1 e 2. Cada pessoa com conta válida pode consultar e manter os incidentes da equipe. O perfil exibe nome, e-mail e departamento.

## Executar no XAMPP

- **Base em uso.** Inicie Apache e o serviço do banco. Preserve os cinco valores do seu `api/config/database.php` e abra `public/login.html` pela URL do Apache. Por exemplo, se a pasta for `safeincident` e o Apache usar a porta 8080, acesse `http://localhost:8080/safeincident/public/login.html`. Use a conta do SafeIncident já cadastrada. Essa instalação dispensa importação de SQL.
- **Instalação nova.** Em uma base de demonstração vazia, siga o `README.md`. O roteiro cria as tabelas a partir de `database/schema.sql`, orienta a copiar `api/config/database.example.php` para a configuração local e usa `tools/gerar-hash.php` no terminal para gerar o hash da senha do primeiro usuário. Depois do cadastro desse usuário, uma única importação de `database/dados_exemplo.sql` carrega os exemplos.

## Do navegador ao banco

As páginas HTML em `public/` carregam o CSS e os scripts de `public/js/`. O JavaScript usa `fetch` para consultar ou enviar dados. Cada rota em `api/` atende a uma operação e devolve JSON. O PHP reúne validações e consultas compartilhadas em `api/helpers/` e usa PDO para acessar o MySQL.

No cadastro, por exemplo, o formulário envia os seis campos editáveis a `api/incidentes/criar.php`. O servidor confere a sessão e as entradas, valida a existência do tipo de ataque e grava a linha com o identificador de quem entrou no sistema. A resposta HTTP 201 inclui `id_incidente`. O navegador usa esse número para abrir os detalhes.

## Banco de dados

| Tabela | Chave e campos de interesse | Uso |
|---|---|---|
| `usuarios` | `id_usuario` (PK), `nome`, `email` único, `senha_hash`, `departamento` opcional | Autenticar e identificar quem cadastrou o incidente. |
| `tipos_ataque` | `id_tipo` (PK), `nome` único | Manter o catálogo usado nos formulários, filtros e agrupamentos. |
| `incidentes` | `id_incidente` (PK), `id_tipo` e `id_usuario_registro` (FKs), `titulo`, `sistema_afetado`, `prioridade`, `descricao`, `status`, `data_ocorrencia`, `data_criacao`, `data_resolucao` e `excluido_em` | Guardar a ocorrência, seu estado atual e as datas. |

Um usuário pode registrar zero ou muitos incidentes. Um tipo de ataque pode classificar zero ou muitos incidentes. Cada incidente exige um usuário de registro e um tipo válidos. A tabela de tipos permite ampliar o catálogo por cadastro no banco. Status e prioridade usam listas fixas do tipo `ENUM`.

## Acesso e tratamento de erros

O login público recebe e-mail e senha por POST. O PHP valida os campos, consulta o e-mail e usa `password_verify` para conferir a senha com o hash armazenado. Um login válido regenera o identificador da sessão e guarda o ID e o nome do usuário no servidor. Uma combinação incorreta recebe HTTP 401 com a mesma mensagem para e-mail desconhecido e senha errada. O botão Sair envia POST e o servidor destrói a sessão.

As onze rotas de negócio verificam a sessão antes de abrir o PDO. Sem sessão, a API responde 401 e as telas internas voltam ao login. As páginas HTML são estáticas. A verificação que controla o acesso aos dados ocorre no PHP. O cookie usa `HttpOnly` e `SameSite=Lax`. O PHP encerra a sessão após 30 minutos sem atividade autenticada. O prazo é ajustado em `MINUTOS_INATIVIDADE`, em `api/helpers/auth_helper.php`. Digitação, clique e rolagem também avisam ao servidor que a página está em uso. Uma aba parada consulta o prazo sem renová-lo. Se outra aba estiver em uso, ambas compartilham a sessão. Quando o PHP retorna 401, a tela volta ao login.

O login, o logout e as quatro escritas de incidentes exigem um objeto JSON, `Content-Type: application/json` e `X-SafeIncident: 1`. O logout envia `{}`. O cabeçalho identifica o formato de chamada da interface e o CORS da API fica fechado a origens externas. O login envia os cabeçalhos em `public/js/login.js`. As telas internas usam `enviarJson` e `chamarApi`, em `public/js/utils.js`.

| Situação | Resposta esperada |
|---|---|
| Campo, identificador, filtro ou corpo inválido | 400 com mensagem de validação |
| Credenciais incorretas ou rota protegida sem sessão | 401 |
| Marcador da aplicação ausente ou incorreto em uma escrita JSON | 403 |
| Incidente inexistente ou excluído na consulta de detalhes | 404 |
| Método HTTP incorreto ou mídia de escrita incompatível | 405 ou 415, respectivamente |
| Falha do PDO ao conectar ou executar uma consulta | 500, JSON genérico e detalhe técnico em `error_log` |

Os valores recebidos para o SQL são vinculados como parâmetros. Consultas sem entradas externas podem usar texto fixo. Os agrupamentos usam colunas definidas no código, e a paginação usa inteiros validados. Os scripts exibem dados externos com `textContent` ou com `.value` nos formulários. O menu lateral usa uma estrutura HTML fixa. A configuração pessoal do banco tem regra no `.gitignore` e o README descreve como excluí-la do pacote de entrega. Verificar se ela entrou em algum commit anterior exige consultar o histórico real do repositório.

## Ciclo de um incidente

| Ação | Regra observável |
|---|---|
| Cadastrar | Título de 3 a 150 caracteres, sistema de 2 a 100, descrição de 10 a 5.000, tipo existente, prioridade válida e data de ocorrência real até hoje. O status inicial é Pendente. O banco preenche a data de criação. |
| Consultar | A busca considera título, descrição e sistema. A lista combina tipo, prioridade e status e mostra dez registros por página na interface. Os detalhes incluem o registrante e as datas. |
| Editar | Altera título, tipo, sistema, prioridade, descrição e data da ocorrência. A alteração de status tem operação própria. O registrante permanece o do cadastro. |
| Resolver | Ao mudar para Resolvido, o banco grava a hora em `data_resolucao`. O próprio UPDATE exige status diferente do solicitado e registro ativo. Entre dois pedidos para resolver o mesmo caso, apenas a primeira transição grava a data. Repetições recebem sucesso e preservam esse horário. |
| Reabrir | Ao passar de Resolvido para Pendente ou Em andamento, `data_resolucao` volta a `NULL`. Uma nova resolução marca o novo encerramento. |
| Excluir | Após confirmação, `excluido_em` recebe a hora da exclusão. A linha permanece no banco e deixa de aparecer nas consultas. A condição `excluido_em IS NULL` entra pelo filtro compartilhado da lista, do dashboard e do relatório. Detalhes, sistemas e verificação de registro ativo aplicam a condição diretamente. |

O sistema guarda quem registrou o incidente, o estado atual e a data de exclusão. O autor da exclusão e a sequência de alterações não são registrados.

## Como ler o dashboard e os relatórios

O dashboard conta todos os registros ativos, inclusive os resolvidos. “Em aberto” soma Pendente e Em andamento. O resumo traz o total, os abertos, os resolvidos e a média de tempo. A mesma página apresenta dois gráficos e os cinco cadastros mais recentes segundo a data de criação.

A média usa as horas inteiras entre criação e resolução de cada incidente resolvido e ativo. O SQL calcula a média dessas diferenças e o PHP a arredonda para uma casa decimal. Um registro encerrado em 59 minutos contribui com zero hora. Ao reabrir, ele sai do cálculo. Se houver incidentes ativos e nenhum resolvido, a tela mostra um travessão. A média aparece em dias a partir de 48 horas. Quando a base não tem incidentes ativos, o dashboard apresenta uma mensagem de lista vazia.

Com um usuário criado e os dados de exemplo importados uma vez em base limpa, são **32 linhas no banco, 3 excluídas, 29 ativas, 8 em aberto e 21 resolvidas**. Os abertos são 3 pendentes e 5 em andamento. As 515 horas inteiras dos 21 resolvidos resultam em **24,5 horas** na tela.

O relatório filtra por tipo, sistema, status, prioridade e intervalo inclusivo da data da ocorrência. O resumo e os cinco agrupamentos (tipo, status, prioridade, sistema e mês) usam todo o conjunto filtrado. A tabela traz até 1.000 registros, ordenados por ocorrência e, em caso de empate, por ID decrescente. O CSV exporta as linhas dessa tabela, com cabeçalho, separador ponto e vírgula e codificação UTF-8 com BOM. Ao gerar um período sem ocorrências, a página informa que os filtros não retornaram incidentes e não oferece um arquivo vazio.

Ao mudar um filtro, a página invalida o resultado e desabilita o CSV. Uma resposta antiga também perde a vez se os filtros mudarem enquanto ela está em trânsito. Após gerar de novo, o botão informa as linhas de dados que serão exportadas, descontado o cabeçalho. Novos cadastros ou mudanças de status aparecem na próxima consulta que os inclua.

Dashboard e Relatórios carregam o arquivo UMD do Chart.js 4.4.1 de forma assíncrona pelo cdnjs. O navegador confere o hash SHA-512 indicado em `integrity`. Quando a biblioteca falta, cada gráfico mostra os valores por categoria como texto. Indicadores, recentes, tabela e CSV continuam disponíveis com o servidor local em funcionamento.

## Requisitos da versão implementada

| Código | Funcionalidade | Código | Funcionalidade |
|---|---|---|---|
| RF01 | Entrar e sair | RF06 | Alterar status |
| RF02 | Consultar dashboard e gráficos | RF07 | Editar incidente |
| RF03 | Cadastrar incidente | RF08 | Gerar relatório filtrado e CSV |
| RF04 | Listar, buscar, filtrar e paginar | RF09 | Consultar perfil |
| RF05 | Abrir os detalhes | RF10 | Excluir logicamente, após confirmação |

| Código | Requisito não funcional |
|---|---|
| RNF01 | Armazenar senhas com hash |
| RNF02 | Proteger as rotas de dados com sessão |
| RNF03 | Parametrizar os valores externos usados no SQL |
| RNF04 | Validar entradas no navegador e no servidor |
| RNF05 | Manter a configuração pessoal fora do repositório distribuído |
| RNF06 | Exibir entradas de usuário como texto |
| RNF07 | Oferecer interface para desktop com menu recolhível |
| RNF08 | Organizar interface, rotas, auxiliares e banco em pastas próprias |
| RNF09 | Responder aos erros da API em JSON com código HTTP adequado |

## Roteiro curto para demonstrar

1. Entre no sistema, confira o perfil e mostre os quatro indicadores antes de alterar a base.
2. Cadastre uma ocorrência de demonstração com título identificável. Abra os detalhes e mostre que o registrante veio da sessão.
3. Edite o título, marque Resolvido e anote a data. Reabra e mostre que a data foi limpa. Confira nos indicadores a mudança de grupo.
4. Faça uma busca, aplique um filtro de status e mostre a paginação. Em Relatórios, gere um período com dados e exporte o CSV. Gere outro período sem ocorrências.
5. Exclua apenas o registro de demonstração. Confira o desaparecimento da lista e do relatório e a redução do total no dashboard. Encerre a sessão e tente abrir uma tela interna.

## Perguntas que podem surgir na defesa

**Por que o tipo de ataque fica em uma tabela própria?** Acrescentar um tipo exige inserir uma linha no catálogo. As fichas guardam a chave desse tipo e os relatórios conseguem agrupar ocorrências da mesma categoria. O status tem três opções ligadas a regras específicas, como preencher a data ao resolver.

**Como demonstrar a diferença entre resolver e excluir?** Resolva o caso de teste e mostre que ele continua na lista e no total de ativos. Depois, quando confirmar a exclusão, mostre que ele sai da lista e do total. No banco, a marca `excluido_em` permite identificar a linha excluída.

**Por que o total do relatório pode superar a quantidade de linhas no CSV?** O resumo considera todos os registros que atendem aos filtros. A tabela e o CSV trazem até 1.000. Um período menor permite exportar um conjunto mais específico.

**Como o cadastro preserva a autoria?** O formulário envia os dados da ocorrência. O PHP obtém o ID do usuário de sua própria sessão e usa esse valor no INSERT. Trocar o título ou a prioridade depois preserva o registrante.

## Limitações conhecidas

A V1 usa as mesmas permissões para toda a equipe e guarda o registrante e o estado atual. Histórico de alterações, identificação de quem excluiu, atribuição de responsável, gestão de contas pela interface e limitação de tentativas de login ficam para outra versão. O CSV traz até 1.000 incidentes por consulta. Exportações maiores, medições de carga, anexos, PDF e integrações são trabalhos futuros.
