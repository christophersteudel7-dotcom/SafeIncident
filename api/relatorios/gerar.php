<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/util.php';

exigirLogin();
exigirMetodo('GET');

try {
    // Os filtros escolhidos na tela valem para TUDO abaixo: resumo, tabela e gráficos
    [$where, $valores] = montarFiltros($_GET);

    $sql = "SELECT i.id_incidente, i.titulo, t.nome AS tipo, i.sistema_afetado, i.prioridade,
                   i.status, i.data_ocorrencia, i.data_resolucao
            FROM incidentes i
            JOIN tipos_ataque t ON t.id_tipo = i.id_tipo
            WHERE $where
            ORDER BY i.data_ocorrencia DESC, i.id_incidente DESC
            LIMIT 1000";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($valores);

    responderJson([
        'resumo' => calcularResumo($pdo, $where, $valores),
        'dados' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'por_tipo' => contarPor($pdo, 't.nome', $where, $valores),
        'por_status' => completarCategorias(contarPor($pdo, 'i.status', $where, $valores), STATUS_VALIDOS),
        'por_prioridade' => completarCategorias(contarPor($pdo, 'i.prioridade', $where, $valores), PRIORIDADES),
        'por_sistema' => contarPor($pdo, 'i.sistema_afetado', $where, $valores),
        'por_mes' => array_map(function ($l) {
            return ['mes' => $l['nome'], 'total' => $l['total']];
        }, contarPor($pdo, "DATE_FORMAT(i.data_ocorrencia, '%Y-%m')", $where, $valores, 'categoria ASC')),
    ]);
} catch (PDOException $e) {
    erroInterno($e);
}
