<?php
require_once __DIR__ . '/../helpers/auth_helper.php';

// A aba usa verificar=1 para consultar o prazo sem contar como atividade.
exigirLogin(!isset($_GET['verificar']));
$restante = $_SESSION['ultima_atividade'] + MINUTOS_INATIVIDADE * 60 - time();
header('Cache-Control: no-store');
responderJson(['nome' => $_SESSION['nome'], 'segundos_restantes' => max(0, $restante)]);