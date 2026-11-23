<?php
require_once __DIR__ . '/../helpers/util.php';

exigirMetodo('POST');
lerJson(); // O botão Sair envia o objeto vazio {} com o cabeçalho da aplicação.
iniciarSessao();
encerrarSessao();

responderJson(['sucesso' => true]);
