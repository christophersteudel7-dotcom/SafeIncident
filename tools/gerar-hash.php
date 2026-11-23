<?php
// Uso exclusivo do terminal, durante o cadastro manual do primeiro usuário.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$linha = fgets(STDIN);
$senha = $linha === false ? '' : rtrim($linha, "\r\n");

// O bcrypt considera até 72 bytes. Espaços fazem parte da senha digitada.
if ($senha === '' || strlen($senha) > 72 || strpos($senha, "\0") !== false) {
    fwrite(STDERR, "Informe de 1 a 72 bytes, sem caractere nulo.\n");
    exit(1);
}

echo password_hash($senha, PASSWORD_BCRYPT), PHP_EOL;
