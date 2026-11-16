<?php
// Inicia a sessão com cookie protegido
function iniciarSessao() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,   // JavaScript não consegue ler o cookie (protege contra roubo via XSS)
            'samesite' => 'Lax',  // o cookie não é enviado em requisições de outros sites
        ]);
        session_start();
    }
}

// Devolve uma resposta JSON padronizada e encerra o script
function responderJson($dados, $codigo = 200) {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

// Chamada no início de todo endpoint protegido
function exigirLogin() {
    iniciarSessao();
    if (!isset($_SESSION['id_usuario'])) {
        responderJson(['erro' => 'Não autenticado'], 401);
    }
}