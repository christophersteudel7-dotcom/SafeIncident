# SafeIncident

Aplicação web para **gerenciamento de incidentes de segurança da informação**. Profissionais autorizados de TI/Segurança registram os incidentes, acompanham o status, visualizam indicadores em um dashboard e geram relatórios filtráveis com gráficos.

Projeto acadêmico individual (Análise e Desenvolvimento de Sistemas).

## Funcionalidades (V1)

- Login e logout com sessão
- Dashboard: total, em aberto, resolvidos, tempo médio de resolução, gráficos por tipo de ataque e por prioridade e incidentes recentes
- Cadastro, edição e exclusão lógica de incidentes
- Consulta com busca, filtros (tipo, prioridade, status) e paginação
- Detalhes do incidente e alteração de status
- Relatórios com filtros (tipo, sistema, status, prioridade, período), resumo, 5 gráficos, tabela e exportação em CSV
- Perfil do usuário (somente leitura)
- Sidebar recolhível

## Tecnologias

HTML, CSS e JavaScript puro (frontend) · PHP com PDO (backend) · MySQL · Chart.js (gráficos)

## Como executar no XAMPP para Windows

A pasta de referência é C:\xampp\htdocs\safeincident. Na sua máquina, ajuste a pasta e a porta do Apache se necessário.

### Instalação que já tem banco e usuário

1. Mantenha os valores de host, porta do banco, nome da base, usuário e senha do seu arquivo local. Preencha esses valores nas cinco variáveis da nova configuração em api/config/database.php.
2. Inicie Apache e o serviço de banco no XAMPP e abra http://localhost:8080/safeincident/public/login.html usando a porta do seu Apache. A porta do banco é configurada em $port e pode ser diferente.
3. Entre com a conta existente. Esta atualização de código dispensa a importação de schema.sql, dos exemplos e a criação de outro usuário.

### Instalação nova para demonstração

1. Extraia o projeto em C:\xampp\htdocs\safeincident e inicie os dois serviços. No phpMyAdmin, use database/schema.sql uma vez para criar uma base nova.
2. Copie api/config/database.example.php para api/config/database.php. Preencha os cinco valores com os da instalação. A conta configurada deve conseguir consultar as três tabelas e inserir e atualizar incidentes.
3. Para gerar o hash do primeiro usuário, abra o PowerShell na pasta do projeto e execute o bloco abaixo. A senha é digitada de forma oculta. O PHP devolve o hash.

~~~powershell
& {
    $OutputEncoding = [System.Text.UTF8Encoding]::new($false)
    $segredo = Read-Host 'Senha do primeiro usuário' -AsSecureString
    $credencial = [System.Net.NetworkCredential]::new('', $segredo)
    $credencial.Password | & C:\xampp\php\php.exe .\tools\gerar-hash.php
}
~~~

4. No phpMyAdmin, selecione a base safeincident e use uma conta com permissão para cadastrar usuários. Preencha seu nome, e-mail e o hash completo no INSERT abaixo. Isso cria somente o usuário de demonstração.

~~~sql
INSERT INTO usuarios (nome, email, senha_hash)
VALUES ('Seu nome', 'pessoa@example.test', 'COLE_AQUI_O_HASH_GERADO');
~~~

5. Se quiser os exemplos, execute database/dados_exemplo.sql uma única vez na base nova, depois de criar o usuário. A referência para essa carga é 29 incidentes ativos, 8 em aberto, 21 resolvidos e 24,5 horas de média.
6. Acesse a mesma página de login da instalação existente.

### Gerar o ZIP do projeto

Depois de registrar a versão de entrega no Git, execute na raiz do próprio repositório. O comando empacota o commit HEAD e exclui a configuração pessoal e o diagnóstico.

~~~powershell
git archive --format=zip --output=../SafeIncident-entrega.zip HEAD -- README.md .gitignore api public database docs tools ':(exclude)api/config/database.php' ':(exclude)api/teste.php'
~~~

## Estrutura

```
public/    telas (HTML), estilos (css/) e scripts (js/)
api/       endpoints PHP (auth, incidentes, dashboard, relatorios, perfil) e funções auxiliares
database/  schema.sql e dados_exemplo.sql
docs/      explicação técnica do projeto
```

## Segurança

Senhas com hash, sessão com cookie `httponly`, consultas com prepared statements, validação no navegador e no servidor, dados exibidos com `textContent` (contra XSS), credenciais do banco fora do repositório.

## Fora do escopo desta versão

Anexos, histórico de alterações, níveis de acesso, atribuição de responsável, exportação em PDF, integrações (SIEM), IA e monitoramento em tempo real.
