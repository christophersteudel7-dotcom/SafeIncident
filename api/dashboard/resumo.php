<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/util.php';

exigirLogin();
exigirMetodo('GET');

try {
    // Sem filtros: o dashboard mostra a situação geral (só não conta os excluídos)
    [$where, $valores] = montarFiltros([]);

    $resumo = calcularResumo($pdo, $where, $valores);
    $porTipo = contarPor($pdo, 't.nome', $where, $valores);
    $porPrioridade = completarCategorias(contarPor($pdo, 'i.prioridade', $where, $valores), PRIORIDADES);

    $stmt = $pdo->prepare("SELECT i.id_incidente, i.titulo, i.prioridade, i.status, i.data_criacao
                           FROM incidentes i
                           WHERE $where
                           ORDER BY i.data_criacao DESC, i.id_incidente DESC
                           LIMIT 5");
    $stmt->execute($valores);

    responderJson([
        'total' => $resumo['total'],
        'abertos' => $resumo['abertos'],
        'resolvidos' => $resumo['resolvidos'],
        'tempo_medio_horas' => $resumo['tempo_medio_horas'],
        'por_tipo' => $porTipo,
        'por_prioridade' => array_map(function ($l) {
            return ['prioridade' => $l['nome'], 'total' => $l['total']];
        }, $porPrioridade),
        'recentes' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
} catch (PDOException $e) {
    erroInterno($e);
}
