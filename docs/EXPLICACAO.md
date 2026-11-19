# SafeIncident: explicação técnica para a defesa

## 1. O problema e quem usa

Quando acontece um incidente de segurança (phishing, malware, vazamento...), muitas organizações registram em planilhas ou e-mails soltos. Isso dificulta saber quantos incidentes existem, quais estão abertos, quanto tempo levam para ser resolvidos e quais ataques são mais frequentes.

O SafeIncident centraliza esse registro. O usuário é o **profissional autorizado de TI/Segurança da Informação**. Nesta versão, quem consegue fazer login já é considerado autorizado (não há níveis de permissão).

## 2. Como o sistema está organizado

```
Navegador (HTML, CSS, JavaScript)
        |  fetch() envia/recebe JSON
        v
Arquivos PHP em api/  (cada arquivo é um "endpoint")
        |  PDO + prepared statements
        v
MySQL
```

- **public/**: o que o usuário vê. Cada tela é um HTML com seu JavaScript.
- **api/**: o que roda no servidor. O JavaScript chama esses arquivos e recebe JSON.
- **api/helpers/util.php**: validações e consultas reutilizadas (para não repetir código).
- **public/js/utils.js**: funções reutilizadas no navegador (chamar a API, badges, avisos, gráficos).
- **public/js/layout.js**: monta a sidebar uma única vez e confirma a sessão em todas as telas internas.

Escolhi não usar framework de propósito: assim todo o caminho fica visível (navegador, PHP, SQL), e eu consigo explicar cada etapa.

## 3. Banco de dados

Três tabelas:

- **usuarios**: id, nome, email (único), senha_hash, departamento
- **tipos_ataque**: id, nome (Phishing, Malware, Ransomware, Engenharia Social, Acesso Não Autorizado, Vazamento de Dados, Outro)
- **incidentes**: id, título, tipo (FK), sistema afetado, prioridade, descrição, data da ocorrência, data de criação, data de resolução, data de exclusão, status, usuário que registrou (FK)

Relacionamentos (ambos 1:N):

- um **usuário** registra vários **incidentes**
- um **tipo de ataque** aparece em vários **incidentes**

Decisões de modelagem:

- **Tipo de ataque é tabela**, porque a lista pode crescer (novo tipo = um INSERT, sem alterar a estrutura).
- **Status e prioridade são ENUM**, porque são poucos valores fixos que a regra de negócio usa diretamente (por exemplo, "Resolvido" dispara o preenchimento da data de resolução).
- **Três datas com funções diferentes**: `data_ocorrencia` (quando aconteceu, informada pelo usuário), `data_criacao` (quando foi registrado, automática) e `data_resolucao` (quando foi resolvido, automática). A diferença entre criação e resolução alimenta o tempo médio.
- **usuário que registrou**, e não "responsável": a V1 apenas registra quem cadastrou o incidente; atribuir responsável fica para uma versão futura.

## 4. Como o login funciona

1. O usuário digita e-mail e senha; o JavaScript envia para `api/auth/login.php`.
2. O PHP valida os campos e busca o usuário com prepared statement.
3. `password_verify` compara a senha digitada com o **hash** guardado (a senha em si nunca é armazenada).
4. Se estiver correto, o PHP cria uma **sessão** no servidor (`session_regenerate_id` troca o id para evitar fixação de sessão) e o navegador recebe um cookie com o id dessa sessão.
5. Todo endpoint começa com `exigirLogin()`: sem sessão válida, responde 401 e o JavaScript manda o usuário para o login.

A mensagem de erro é sempre "E-mail ou senha inválidos", sem revelar se o e-mail existe.

## 5. Como um incidente é cadastrado (do clique ao banco)

1. Em **Incidentes > Novo incidente**, o usuário preenche o formulário.
2. O JavaScript valida (campos obrigatórios, tamanhos, data não futura) e mostra o erro abaixo do campo.
3. Se estiver válido, envia JSON para `api/incidentes/criar.php`.
4. O PHP **valida de novo** (o navegador pode ser burlado), confere se o tipo existe e grava com `INSERT` usando prepared statement.
5. O **usuário que registrou vem da sessão**, não do formulário, então não dá para forjar. O status começa como "Pendente" e `data_criacao` é preenchida pelo próprio MySQL.
6. O PHP responde 201 com o id e o usuário é levado aos detalhes do novo incidente.

## 6. Status, edição e exclusão

- **Alterar status** (`atualizar-status.php`): ao virar "Resolvido", o sistema grava `data_resolucao = NOW()`. Se o incidente for reaberto, a data de resolução volta a vazio. Repetir "Resolvido" não refaz a data.
- **Editar** (`editar.php`): só título, tipo, sistema, prioridade, descrição e data da ocorrência. Quem registrou, as datas automáticas e o status não mudam por aqui.
- **Excluir** é **lógico**: o registro continua no banco, ganha a data em `excluido_em` e deixa de aparecer. Em segurança da informação isso preserva a rastreabilidade e permite recuperar um registro apagado por engano. A regra `excluido_em IS NULL` fica em um único lugar (`montarFiltros`), para nenhuma consulta esquecê-la.

## 7. Dashboard

Mostra a **situação atual**, sem filtros. Tudo vem de `api/dashboard/resumo.php`, calculado no banco:

- **Total**: `COUNT(*)` dos incidentes não excluídos
- **Em aberto**: status Pendente ou Em andamento
- **Resolvidos**: status Resolvido
- **Tempo médio de resolução**: média de `TIMESTAMPDIFF(HOUR, data_criacao, data_resolucao)` dos resolvidos (mostrado em horas, ou em dias acima de 48 h)
- **Gráficos**: incidentes por tipo de ataque e por prioridade
- **Recentes**: os 5 últimos registrados

## 8. Relatórios e gráficos

O relatório responde a uma pergunta com **filtros** (tipo, sistema, status, prioridade, período da ocorrência). O endpoint `api/relatorios/gerar.php` aplica os filtros e devolve, **sobre o mesmo conjunto de registros**: resumo, tabela e dados de 5 gráficos (por tipo, status, prioridade, sistema e mês). Também é possível exportar a tabela em CSV.

**Como os gráficos são alimentados:** o PHP faz `SELECT ... COUNT(*) ... GROUP BY` no banco e devolve JSON (por exemplo, "Phishing: 6, Malware: 5"). O JavaScript entrega esses números ao Chart.js. Não existe nenhum número fixo no código: se um incidente for cadastrado, o gráfico muda.

**Dashboard x Relatórios:** o dashboard é a foto atual e rápida; o relatório é análise sob demanda, com filtros.

## 9. Segurança (o que foi implementado e por quê)

| Proteção | Onde | Contra o quê |
|---|---|---|
| Hash de senha (`password_hash` / `password_verify`) | login | vazamento do banco expor senhas |
| Sessão com cookie `httponly` e `samesite` | auth_helper.php | roubo do cookie por script; requisições de outros sites |
| `exigirLogin()` em todo endpoint | api/ | acesso sem autenticação (mesmo ignorando o JavaScript) |
| Prepared statements | todas as consultas | SQL Injection |
| Validação no navegador **e** no servidor | formulários e util.php | dados inválidos e requisições forjadas |
| `textContent` em vez de `innerHTML` | public/js | XSS (HTML digitado pelo usuário vira texto, não elemento) |
| Mensagens de erro genéricas e log no servidor | erroInterno() | vazar detalhes do banco |
| Prefixo de apóstrofo em células do CSV que começam com `=`, `+`, `-` ou `@` | exportação | injeção de fórmula no Excel |
| Credenciais do banco fora do Git (`.gitignore`) | database.php | exposição de senha no GitHub |

Não implementado (fora do escopo acadêmico): 2FA, níveis de acesso, proteção CSRF dedicada, bloqueio por tentativas de login.

## 10. Requisitos

**Funcionais**

- RF01 Autenticar usuário (login e logout)
- RF02 Exibir dashboard com indicadores e gráficos
- RF03 Cadastrar incidente
- RF04 Listar incidentes com busca, filtros e paginação
- RF05 Visualizar detalhes de um incidente
- RF06 Alterar status de um incidente
- RF07 Editar incidente
- RF08 Gerar relatório filtrado (resumo, tabela e gráficos) e exportar em CSV
- RF09 Visualizar perfil do usuário (somente leitura)
- RF10 Excluir incidente (exclusão lógica, com confirmação)

**Não funcionais**

- RNF01 Senhas armazenadas com hash
- RNF02 Rotas protegidas por sessão
- RNF03 Consultas SQL parametrizadas
- RNF04 Validação no navegador e no servidor
- RNF05 Credenciais fora do repositório
- RNF06 Proteção básica contra XSS
- RNF07 Interface voltada a desktop, com sidebar recolhível
- RNF08 Código organizado em camadas e pastas
- RNF09 Tratamento padronizado de erros (JSON com código HTTP)

## 11. Perguntas prováveis da banca

**Por que sem framework?** Para mostrar e dominar os fundamentos (como o navegador conversa com o servidor, como o SQL é protegido). Como os endpoints já devolvem JSON, uma versão futura pode trocar só o frontend (por exemplo, por React) sem mexer no banco nem no PHP.

**Por que ENUM para status e prioridade, mas tabela para tipo de ataque?** Status e prioridade são poucos valores fixos usados pela regra de negócio; o tipo de ataque é uma lista que cresce.

**Por que exclusão lógica?** Preserva a rastreabilidade das ocorrências e permite recuperar registros.

**Como evita SQL Injection?** Os valores digitados vão separados do SQL (prepared statements). Os poucos trechos de SQL montados dinamicamente usam apenas textos fixos escritos no código ou números convertidos para inteiro.

**Como evita XSS?** Os dados vindos do banco são inseridos na tela com `textContent`/`createElement`, nunca com `innerHTML`.

**Quem pode usar o sistema?** Quem tem login; nesta versão não há níveis de permissão.

**O que ficaria para a próxima versão?** Anexos de evidências, histórico de alterações de status, níveis de acesso, atribuição de responsável, exportação em PDF, recebimento automático de chamados e integração com SIEM.
