<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/util.php';

exigirLogin();
exigirMetodo('GET');

try {
    [$where, $valores] = montarFiltros($_GET);

    // Paginação: os números são convertidos para inteiro, então é seguro usá-los no SQL
    $porPagina = min(50, max(1, (int) ($_GET['por_pagina'] ?? 10)));
    $pagina = max(1, (int) ($_GET['pagina'] ?? 1));

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM incidentes i WHERE $where");
    $stmt->execute($valores);
    $total = (int) $stmt->fetchColumn();

    $totalPaginas = max(1, (int) ceil($total / $porPagina));
    $pagina = min($pagina, $totalPaginas);
    $offset = ($pagina - 1) * $porPagina;

    $sql = "SELECT i.id_incidente, i.titulo, t.nome AS tipo, i.sistema_afetado,
                   i.prioridade, i.status, i.data_ocorrencia, i.data_criacao
            FROM incidentes i
            JOIN tipos_ataque t ON t.id_tipo = i.id_tipo
            WHERE $where
            ORDER BY i.data_criacao DESC, i.id_incidente DESC
            LIMIT $porPagina OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($valores);

    responderJson([
        'dados' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'total' => $total,
        'pagina' => $pagina,
        'total_paginas' => $totalPaginas,
    ]);
} catch (PDOException $e) {
    erroInterno($e);
}
