<?php
require_once __DIR__ . '/config/database.php';

$nome = 'Administrador';
$email = 'admin@safeincident.com';
$senha = 'Admin@123';
$departamento = 'Segurança da Informação';

$hash = password_hash($senha, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, departamento) VALUES (?, ?, ?, ?)');
$stmt->execute([$nome, $email, $hash, $departamento]);

echo 'Usuário criado. APAGUE este arquivo agora.';