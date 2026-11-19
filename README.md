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

## Como executar (XAMPP)

1. Copie a pasta do projeto para `C:\xampp\htdocs\safeincident`
2. Inicie **Apache** e **MySQL** no painel do XAMPP
3. Copie `api/config/database.example.php` para `api/config/database.php` e ajuste host, porta, usuário e senha
4. No phpMyAdmin, aba **SQL**, execute `database/schema.sql`
5. Crie o primeiro usuário (a senha deve ser gravada com `password_hash` do PHP; nunca em texto puro)
6. (Opcional) Execute `database/dados_exemplo.sql` para popular o sistema com incidentes de exemplo
7. Acesse `http://localhost:8080/safeincident/public/login.html` (ajuste a porta conforme o seu Apache)

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
