<?php
// Funções auxiliares compartilhadas pelos endpoints do SafeIncident.
require_once __DIR__ . '/auth_helper.php';

// Um aviso do driver não deve aparecer antes da resposta JSON no XAMPP.
ini_set('display_errors', '0');
date_default_timezone_set('America/Sao_Paulo');

// Valores permitidos (iguais aos ENUM do banco)
const PRIORIDADES = ['Baixa', 'Media', 'Alta', 'Critica'];
const STATUS_VALIDOS = ['Pendente', 'Em andamento', 'Resolvido'];

// Garante que o endpoint só aceita o método HTTP esperado (GET ou POST)
function exigirMetodo($metodo) {
    if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
        header('Allow: ' . $metodo);
        responderJson(['erro' => 'Método não permitido'], 405);
    }
}

// As escritas da interface enviam JSON e o mesmo cabeçalho. O CORS fica na mesma origem.
function lerJson() {
    $tipo = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($tipo !== 'application/json') {
        responderJson(['erro' => 'Envie application/json'], 415);
    }
    if (($_SERVER['HTTP_X_SAFEINCIDENT'] ?? '') !== '1') {
        responderJson(['erro' => 'Cabeçalho da aplicação inválido'], 403);
    }

    $objeto = json_decode(file_get_contents('php://input'));
    if (!is_object($objeto)) {
        responderJson(['erro' => 'Envie um objeto JSON válido'], 400);
    }
    return get_object_vars($objeto);
}

// Erro de banco: registra no log do servidor e responde sem revelar detalhes
function erroInterno($e) {
    error_log('SafeIncident: ' . $e->getMessage());
    responderJson(['erro' => 'Erro interno do servidor'], 500);
}

// Chame dentro do try do endpoint, após a sessão e as validações que dispensam o banco.
function conectarBanco() {
    $arquivo = __DIR__ . '/../config/database.php';
    if (!is_readable($arquivo)) {
        erroInterno(new RuntimeException('Configuração local do banco ausente ou ilegível'));
    }
    require $arquivo; // Define $host, $port, $dbname, $usuario e $senha nesta função.
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    return new PDO($dsn, $usuario, $senha, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

// Campo opcional ausente equivale a texto vazio. Um valor informado deve ser texto.
function param($origem, $chave) {
    if (!array_key_exists($chave, $origem)) return '';
    if (!is_string($origem[$chave])) {
        responderJson(['erro' => 'Campo de texto inválido'], 400);
    }
    return trim($origem[$chave]);
}

// Aceita um inteiro JSON ou uma sequência de dígitos vinda da URL ou do formulário.
function idPositivo($valor) {
    if ((is_int($valor) || is_string($valor)) && ctype_digit((string) $valor)) {
        $texto = ltrim((string) $valor, '0'); // Mantém válidos os links com zeros à esquerda.
        $opcoes = ['options' => ['min_range' => 1, 'max_range' => 2147483647]];
        $id = filter_var($texto, FILTER_VALIDATE_INT, $opcoes);
        if ($id !== false) return $id;
    }
    responderJson(['erro' => 'Identificador inválido'], 400);
}

// A lista ajusta páginas numéricas fora do intervalo, mas rejeita texto malformado.
function numeroDePagina($get, $chave, $padrao) {
    if (!array_key_exists($chave, $get)) return $padrao;
    $texto = param($get, $chave);
    if (!preg_match('/\A[+-]?[0-9]+\z/', $texto)) {
        responderJson(['erro' => 'Paginação inválida'], 400);
    }
    return max(1, (int) $texto);
}

// Conta caracteres (e não bytes), para "é" valer 1. Usa mbstring se existir.
function tamanhoTexto($texto) {
    if (function_exists('mb_strlen')) {
        return mb_strlen($texto, 'UTF-8');
    }
    return preg_match_all('/./su', $texto);
}

// Confere se o texto é uma data real no formato AAAA-MM-DD
function dataValida($texto) {
    if (!preg_match('/\A([1-9][0-9]{3})-([0-9]{2})-([0-9]{2})\z/', $texto, $partes)) {
        return false;
    }
    return checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1]);
}

// Valida os campos de um incidente (usado no cadastro e na edição)
// Retorna os campos já limpos; se algo estiver errado, responde 400 e encerra.
function validarIncidente($dados, $pdo) {
    $titulo = param($dados, 'titulo');
    $sistema = param($dados, 'sistema_afetado');
    $descricao = param($dados, 'descricao');
    $prioridade = param($dados, 'prioridade');
    $dataOcorrencia = param($dados, 'data_ocorrencia');
    $idTipo = idPositivo($dados['id_tipo'] ?? null);

    if (tamanhoTexto($titulo) < 3 || tamanhoTexto($titulo) > 150) {
        responderJson(['erro' => 'O título deve ter entre 3 e 150 caracteres'], 400);
    }
    if (tamanhoTexto($sistema) < 2 || tamanhoTexto($sistema) > 100) {
        responderJson(['erro' => 'O sistema afetado deve ter entre 2 e 100 caracteres'], 400);
    }
    if (tamanhoTexto($descricao) < 10 || tamanhoTexto($descricao) > 5000) {
        responderJson(['erro' => 'A descrição deve ter entre 10 e 5000 caracteres'], 400);
    }
    if (!in_array($prioridade, PRIORIDADES, true)) {
        responderJson(['erro' => 'Prioridade inválida'], 400);
    }
    if (!dataValida($dataOcorrencia)) {
        responderJson(['erro' => 'Data da ocorrência inválida'], 400);
    }
    if ($dataOcorrencia > date('Y-m-d')) {
        responderJson(['erro' => 'A data da ocorrência não pode ser futura'], 400);
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tipos_ataque WHERE id_tipo = ?');
    $stmt->execute([$idTipo]);
    if ($stmt->fetchColumn() == 0) {
        responderJson(['erro' => 'Tipo de ataque inválido'], 400);
    }

    return [
        'titulo' => $titulo,
        'id_tipo' => $idTipo,
        'sistema_afetado' => $sistema,
        'prioridade' => $prioridade,
        'descricao' => $descricao,
        'data_ocorrencia' => $dataOcorrencia,
    ];
}

// Busca um incidente que NÃO foi excluído; responde 404 se não existir
function buscarIncidenteAtivo($pdo, $id) {
    $stmt = $pdo->prepare('SELECT id_incidente, status FROM incidentes WHERE id_incidente = ? AND excluido_em IS NULL');
    $stmt->execute([$id]);
    $incidente = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$incidente) {
        responderJson(['erro' => 'Incidente não encontrado'], 404);
    }
    return $incidente;
}

// Monta o WHERE dos filtros (lista de incidentes e relatórios).
// Devolve [texto do WHERE, lista de valores]. Os valores vão separados do SQL
// (prepared statements), então nada digitado pelo usuário vira comando SQL.
function montarFiltros($get) {
    // REGRA DO PROJETO: incidente excluído (exclusão lógica) nunca aparece.
    // Lista, dashboard e relatório usam este filtro. Detalhes e sistemas filtram diretamente.
    $where = ['i.excluido_em IS NULL'];
    $valores = [];

    $busca = param($get, 'q');
    if ($busca !== '') {
        $where[] = '(i.titulo LIKE ? OR i.descricao LIKE ? OR i.sistema_afetado LIKE ?)';
        $like = '%' . $busca . '%';
        array_push($valores, $like, $like, $like);
    }

    if (array_key_exists('id_tipo', $get) && $get['id_tipo'] !== '') {
        $idTipo = idPositivo($get['id_tipo']);
        $where[] = 'i.id_tipo = ?';
        $valores[] = $idTipo;
    }

    $sistema = param($get, 'sistema');
    if ($sistema !== '') {
        $where[] = 'i.sistema_afetado = ?';
        $valores[] = $sistema;
    }

    $prioridade = param($get, 'prioridade');
    if ($prioridade !== '') {
        if (!in_array($prioridade, PRIORIDADES, true)) {
            responderJson(['erro' => 'Prioridade inválida'], 400);
        }
        $where[] = 'i.prioridade = ?';
        $valores[] = $prioridade;
    }

    $status = param($get, 'status');
    if ($status !== '') {
        if (!in_array($status, STATUS_VALIDOS, true)) {
            responderJson(['erro' => 'Status inválido'], 400);
        }
        $where[] = 'i.status = ?';
        $valores[] = $status;
    }

    $inicio = param($get, 'data_inicio');
    $fim = param($get, 'data_fim');
    if ($inicio !== '' && !dataValida($inicio)) {
        responderJson(['erro' => 'Data inicial inválida'], 400);
    }
    if ($fim !== '' && !dataValida($fim)) {
        responderJson(['erro' => 'Data final inválida'], 400);
    }
    if ($inicio !== '' && $fim !== '' && $inicio > $fim) {
        responderJson(['erro' => 'A data inicial não pode ser maior que a final'], 400);
    }
    if ($inicio !== '') {
        $where[] = 'i.data_ocorrencia >= ?';
        $valores[] = $inicio;
    }
    if ($fim !== '') {
        $where[] = 'i.data_ocorrencia <= ?';
        $valores[] = $fim;
    }

    return [implode(' AND ', $where), $valores];
}

// Indicadores gerais: total, abertos, resolvidos e tempo médio de resolução (em horas)
function calcularResumo($pdo, $where, $valores) {
    $sql = "SELECT COUNT(*) AS total,
                   SUM(i.status IN ('Pendente', 'Em andamento')) AS abertos,
                   SUM(i.status = 'Resolvido') AS resolvidos,
                   AVG(CASE WHEN i.status = 'Resolvido'
                            THEN TIMESTAMPDIFF(HOUR, i.data_criacao, i.data_resolucao) END) AS tempo_medio
            FROM incidentes i
            WHERE $where";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($valores);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    return [
        'total' => (int) $r['total'],
        'abertos' => (int) $r['abertos'],
        'resolvidos' => (int) $r['resolvidos'],
        'tempo_medio_horas' => $r['tempo_medio'] === null ? null : round((float) $r['tempo_medio'], 1),
    ];
}

// Conta incidentes agrupando por um campo. O $campo é sempre um texto fixo
// escrito aqui no código (nunca vem do usuário), por isso é seguro.
// ATENÇÃO: o apelido é "categoria" (e não "nome") porque tipos_ataque também tem
// uma coluna "nome", e o MySQL agruparia por ela em vez de agrupar pelo apelido.
function contarPor($pdo, $campo, $where, $valores, $ordem = 'total DESC, categoria') {
    $sql = "SELECT $campo AS categoria, COUNT(*) AS total
            FROM incidentes i
            JOIN tipos_ataque t ON t.id_tipo = i.id_tipo
            WHERE $where
            GROUP BY categoria
            ORDER BY $ordem";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($valores);

    $linhas = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $linhas[] = ['nome' => $l['categoria'], 'total' => (int) $l['total']];
    }
    return $linhas;
}

// Garante todas as categorias na ordem certa, mesmo as com total 0
// (ex.: as 4 prioridades sempre aparecem no gráfico)
function completarCategorias($linhas, $categorias) {
    $mapa = [];
    foreach ($linhas as $l) {
        $mapa[$l['nome']] = $l['total'];
    }
    $resultado = [];
    foreach ($categorias as $c) {
        $resultado[] = ['nome' => $c, 'total' => $mapa[$c] ?? 0];
    }
    return $resultado;
}
