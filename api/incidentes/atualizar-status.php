<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/util.php';

exigirLogin();
exigirMetodo('POST');

$dados = lerJson();
$id = (int) ($dados['id_incidente'] ?? 0);
$novoStatus = param($dados, 'status');

if ($id <= 0) {
    responderJson(['erro' => 'Identificador inválido'], 400);
}
if (!in_array($novoStatus, STATUS_VALIDOS, true)) {
    responderJson(['erro' => 'Status inválido'], 400);
}

try {
    $atual = buscarIncidenteAtivo($pdo, $id);

    // Se o status não mudou, não há nada a fazer (evita refazer a data de resolução)
    if ($atual['status'] === $novoStatus) {
        responderJson(['sucesso' => true]);
    }

    // Regra de negócio: ao virar "Resolvido" grava a data/hora da resolução;
    // se o incidente for reaberto, a data de resolução é apagada.
    if ($novoStatus === 'Resolvido') {
        $sql = 'UPDATE incidentes SET status = ?, data_resolucao = NOW() WHERE id_incidente = ? AND excluido_em IS NULL';
    } else {
        $sql = 'UPDATE incidentes SET status = ?, data_resolucao = NULL WHERE id_incidente = ? AND excluido_em IS NULL';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$novoStatus, $id]);

    responderJson(['sucesso' => true]);
} catch (PDOException $e) {
    erroInterno($e);
}
