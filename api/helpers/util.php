<?php
// Funções auxiliares compartilhadas pelos endpoints do SafeIncident.
require_once __DIR__ . '/auth_helper.php';

date_default_timezone_set('America/Sao_Paulo');

// Valores permitidos (iguais aos ENUM do banco)
const PRIORIDADES = ['Baixa', 'Media', 'Alta', 'Critica'];
const STATUS_VALIDOS = ['Pendente', 'Em andamento', 'Resolvido'];

// Garante que o endpoint só aceita o método HTTP esperado (GET ou POST)
function exigirMetodo($metodo) {
    if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
        responderJson(['erro' => 'Método não permitido'], 405);
    }
}

// Lê o corpo JSON enviado pelo JavaScript
function lerJson() {
    $dados = json_decode(file_get_contents('php://input'), true);
    if (!is_array($dados)) {
        responderJson(['erro' => 'Requisição inválida'], 400);
    }
    return $dados;
}

// Erro de banco: registra no log do servidor e responde sem revelar detalhes
function erroInterno($e) {
    error_log('SafeIncident: ' . $e->getMessage());
    responderJson(['erro' => 'Erro interno do servidor'], 500);
}

// Lê um parâmetro como texto limpo (ignora valores estranhos, como arrays)
function param($origem, $chave) {
    $valor = $origem[$chave] ?? '';
    return is_string($valor) ? trim($valor) : '';
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
    $d = DateTime::createFromFormat('Y-m-d', $texto);
    return $d && $d->format('Y-m-d') === $texto;
}

// Valida os campos de um incidente (usado no cadastro e na edição)
// Retorna os campos já limpos; se algo estiver errado, responde 400 e encerra.
function validarIncidente($dados, $pdo) {
    $titulo = param($dados, 'titulo');
    $sistema = param($dados, 'sistema_afetado');
    $descricao = param($dados, 'descricao');
    $prioridade = param($dados, 'prioridade');
    $dataOcorrencia = param($dados, 'data_ocorrencia');
    $idTipo = (int) ($dados['id_tipo'] ?? 0);

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
    // Toda consulta de leitura passa por aqui, então o filtro fica em um único lugar.
    $where = ['i.excluido_em IS NULL'];
    $valores = [];

    $busca = param($get, 'q');
    if ($busca !== '') {
        $where[] = '(i.titulo LIKE ? OR i.descricao LIKE ? OR i.sistema_afetado LIKE ?)';
        $like = '%' . $busca . '%';
        array_push($valores, $like, $like, $like);
    }

    $idTipo = (int) param($get, 'id_tipo');
    if ($idTipo > 0) {
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
