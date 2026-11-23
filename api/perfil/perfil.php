<?php
require_once __DIR__ . '/../helpers/util.php';

exigirLogin();
exigirMetodo('GET');

try {
    $pdo = conectarBanco();
    // Nunca devolvemos senha_hash
    $stmt = $pdo->prepare('SELECT nome, email, departamento FROM usuarios WHERE id_usuario = ?');
    $stmt->execute([$_SESSION['id_usuario']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        responderJson(['erro' => 'Usuário não encontrado'], 404);
    }
    responderJson($usuario);
} catch (PDOException $e) {
    erroInterno($e);
}
