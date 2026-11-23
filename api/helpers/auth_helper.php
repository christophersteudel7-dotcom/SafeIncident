<?php
// Ajuste o tempo de inatividade somente aqui, em minutos.
const MINUTOS_INATIVIDADE = 30;

// Inicia a sessão com cookie protegido
function iniciarSessao() {
    if (session_status() === PHP_SESSION_NONE) {
        // O coletor do PHP precisa guardar a sessão pelo menos durante o prazo da aplicação.
        $vida = max((int) ini_get('session.gc_maxlifetime'), MINUTOS_INATIVIDADE * 60);
        ini_set('session.gc_maxlifetime', (string) $vida);
        session_set_cookie_params([
            'httponly' => true,   // Impede a leitura do cookie pelo JavaScript.
            'samesite' => 'Lax',  // Restringe o envio do cookie entre sites.
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

function encerrarSessao() {
    $_SESSION = [];
    session_destroy();
}

// A verificação do relógio de uma aba parada não renova a sessão.
function exigirLogin($renovar = true) {
    iniciarSessao();
    if (!isset($_SESSION['id_usuario'])) {
        responderJson(['erro' => 'Não autenticado'], 401);
    }

    $agora = time();
    // Sessões abertas antes desta atualização ganham o relógio no primeiro acesso.
    $ultima = $_SESSION['ultima_atividade'] ?? $agora;
    if ($agora - $ultima >= MINUTOS_INATIVIDADE * 60) {
        encerrarSessao();
        responderJson(['erro' => 'Sessão expirada'], 401);
    }
    if ($renovar || !isset($_SESSION['ultima_atividade'])) {
        $_SESSION['ultima_atividade'] = $agora;
    }
}