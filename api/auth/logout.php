<?php
require_once __DIR__ . '/../helpers/auth_helper.php';

iniciarSessao();
$_SESSION = [];
session_destroy();

responderJson(['sucesso' => true]);