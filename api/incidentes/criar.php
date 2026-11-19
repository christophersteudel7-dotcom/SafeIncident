<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/util.php';

exigirLogin();
exigirMetodo('POST');

try {
    $campos = validarIncidente(lerJson(), $pdo);

    // Quem registrou vem da SESSÃO (não do formulário), então não dá para forjar.
    // Status começa 'Pendente' e data_criacao é preenchida pelo próprio MySQL.
    $sql = "INSERT INTO incidentes
              (titulo, id_tipo, sistema_afetado, prioridade, descricao, data_ocorrencia, status, id_usuario_registro)
            VALUES (?, ?, ?, ?, ?, ?, 'Pendente', ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $campos['titulo'],
        $campos['id_tipo'],
        $campos['sistema_afetado'],
        $campos['prioridade'],
        $campos['descricao'],
        $campos['data_ocorrencia'],
        $_SESSION['id_usuario'],
    ]);

    responderJson(['sucesso' => true, 'id_incidente' => (int) $pdo->lastInsertId()], 201);
} catch (PDOException $e) {
    erroInterno($e);
}
