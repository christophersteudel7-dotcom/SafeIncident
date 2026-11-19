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
    $campos = validarIncidente($dados, $pdo);

    // Só os campos editáveis. Quem registrou, datas automáticas e status NÃO mudam aqui.
    $sql = "UPDATE incidentes
            SET titulo = ?, id_tipo = ?, sistema_afetado = ?, prioridade = ?, descricao = ?, data_ocorrencia = ?
            WHERE id_incidente = ? AND excluido_em IS NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $campos['titulo'],
        $campos['id_tipo'],
        $campos['sistema_afetado'],
        $campos['prioridade'],
        $campos['descricao'],
        $campos['data_ocorrencia'],
        $id,
    ]);

    responderJson(['sucesso' => true]);
} catch (PDOException $e) {
    erroInterno($e);
}
