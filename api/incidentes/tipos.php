<?php
require_once __DIR__ . '/../helpers/util.php';

exigirLogin();
exigirMetodo('GET');

try {
    $pdo = conectarBanco();
    // "Outro" sempre por último
    $stmt = $pdo->query("SELECT id_tipo, nome FROM tipos_ataque ORDER BY nome = 'Outro', nome");
    responderJson($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    erroInterno($e);
}
