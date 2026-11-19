<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/util.php';

exigirLogin();
exigirMetodo('POST');

$dados = lerJson();
$id = (int) ($dados['id_incidente'] ?? 0);
if ($id <= 0) {
    responderJson(['erro' => 'Identificador inválido'], 400);
}

try {
    buscarIncidenteAtivo($pdo, $id);

    // Exclusão LÓGICA: o registro continua no banco, só ganha a data da exclusão.
    $stmt = $pdo->prepare('UPDATE incidentes SET excluido_em = NOW() WHERE id_incidente = ? AND excluido_em IS NULL');
    $stmt->execute([$id]);

    responderJson(['sucesso' => true]);
} catch (PDOException $e) {
    erroInterno($e);
}
