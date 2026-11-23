<?php
require_once __DIR__ . '/../helpers/util.php';

exigirMetodo('POST');
$dados = lerJson();
$email = param($dados, 'email');
$senha = $dados['senha'] ?? null; // Mantém os espaços da senha.

if (!is_string($senha) || $senha === '' || $email === '') {
    responderJson(['erro' => 'Preencha e-mail e senha'], 400);
}
if (strpos($senha, "\0") !== false) {
    responderJson(['erro' => 'Senha inválida'], 400);
}
if (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responderJson(['erro' => 'E-mail inválido'], 400);
}

// O login consulta o banco para verificar as credenciais. A sessão é autenticada depois.
try {
    $pdo = conectarBanco();
    $stmt = $pdo->prepare('SELECT id_usuario, nome, senha_hash FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    erroInterno($e);
}

if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {
    responderJson(['erro' => 'E-mail ou senha inválidos'], 401);
}

iniciarSessao();
session_regenerate_id(true);
$_SESSION['id_usuario'] = $usuario['id_usuario'];
$_SESSION['nome'] = $usuario['nome'];
$_SESSION['ultima_atividade'] = time();

responderJson(['sucesso' => true, 'nome' => $usuario['nome']]);
