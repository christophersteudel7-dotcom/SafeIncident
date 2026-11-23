<?php
require_once __DIR__ . '/../helpers/util.php';

exigirLogin();
exigirMetodo('POST');

$dados = lerJson();
$id = idPositivo($dados['id_incidente'] ?? null);

try {
    $pdo = conectarBanco();
    buscarIncidenteAtivo($pdo, $id);

    // Exclusão LÓGICA: o registro continua no banco, só ganha a data da exclusão.
    $stmt = $pdo->prepare('UPDATE incidentes SET excluido_em = NOW() WHERE id_incidente = ? AND excluido_em IS NULL');
    $stmt->execute([$id]);

    responderJson(['sucesso' => true]);
} catch (PDOException $e) {
    erroInterno($e);
}
