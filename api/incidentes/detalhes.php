<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/util.php';

exigirLogin();
exigirMetodo('GET');

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    responderJson(['erro' => 'Identificador inválido'], 400);
}

try {
    $sql = "SELECT i.id_incidente, i.titulo, i.id_tipo, t.nome AS tipo, i.sistema_afetado,
                   i.prioridade, i.descricao, i.data_ocorrencia, i.data_criacao,
                   i.data_resolucao, i.status, u.nome AS registrado_por
            FROM incidentes i
            JOIN tipos_ataque t ON t.id_tipo = i.id_tipo
            JOIN usuarios u ON u.id_usuario = i.id_usuario_registro
            WHERE i.id_incidente = ? AND i.excluido_em IS NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $incidente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$incidente) {
        responderJson(['erro' => 'Incidente não encontrado'], 404);
    }
    responderJson($incidente);
} catch (PDOException $e) {
    erroInterno($e);
}
