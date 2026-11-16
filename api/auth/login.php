<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

iniciarSessao();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(['erro' => 'Método não permitido'], 405);
}

// Lê o JSON enviado pelo JavaScript
$dados = json_decode(file_get_contents('php://input'), true);
$email = trim($dados['email'] ?? '');
$senha = $dados['senha'] ?? '';

// Validação no servidor (nunca confie só na validação do navegador)
if ($email === '' || $senha === '') {
    responderJson(['erro' => 'Preencha e-mail e senha'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responderJson(['erro' => 'E-mail inválido'], 400);
}

// Prepared statement: o "?" recebe o valor separado do SQL
$stmt = $pdo->prepare('SELECT id_usuario, nome, senha_hash FROM usuarios WHERE email = ?');
$stmt->execute([$email]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Mesma mensagem para "e-mail não existe" e "senha errada"
if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {
    responderJson(['erro' => 'E-mail ou senha inválidos'], 401);
}

// Login válido: gera novo id de sessão e guarda quem está logado
session_regenerate_id(true);
$_SESSION['id_usuario'] = $usuario['id_usuario'];
$_SESSION['nome'] = $usuario['nome'];

responderJson(['sucesso' => true, 'nome' => $usuario['nome']]);