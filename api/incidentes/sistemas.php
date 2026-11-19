<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/util.php';

exigirLogin();
exigirMetodo('GET');

try {
    $stmt = $pdo->query('SELECT DISTINCT sistema_afetado FROM incidentes WHERE excluido_em IS NULL ORDER BY sistema_afetado');
    responderJson($stmt->fetchAll(PDO::FETCH_COLUMN));
} catch (PDOException $e) {
    erroInterno($e);
}
