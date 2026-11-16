<?php
require_once __DIR__ . '/../helpers/auth_helper.php';

exigirLogin(); // se não estiver logado, responde 401 e para aqui

responderJson(['nome' => $_SESSION['nome']]);