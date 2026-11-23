<?php
require_once __DIR__ . '/../helpers/util.php';

exigirLogin();
exigirMetodo('POST');

$dados = lerJson();
$id = idPositivo($dados['id_incidente'] ?? null);
$novoStatus = param($dados, 'status');

if (!in_array($novoStatus, STATUS_VALIDOS, true)) {
    responderJson(['erro' => 'Status inválido'], 400);
}

try {
    $pdo = conectarBanco();
    // O banco muda a data só quando o status da linha realmente muda.
    $sql = "UPDATE incidentes
            SET status = ?, data_resolucao = CASE WHEN ? = 'Resolvido' THEN NOW() ELSE NULL END
            WHERE id_incidente = ? AND excluido_em IS NULL AND status <> ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$novoStatus, $novoStatus, $id, $novoStatus]);

    if ($stmt->rowCount() === 0) {
        // Repetir o status de um ativo é sucesso. Um ID ausente ou excluído recebe 404.
        buscarIncidenteAtivo($pdo, $id);
    }

    responderJson(['sucesso' => true]);
} catch (PDOException $e) {
    erroInterno($e);
}
