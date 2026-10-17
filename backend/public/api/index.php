<?php
ini_set('display_errors', '0');
$entry = dirname(__DIR__, 2) . '/padaria-private/api.php';
if (!is_file($entry)) {
 http_response_code(503);
 header('Content-Type: application/json; charset=utf-8');
 header('Cache-Control: no-store');
 echo json_encode(['error' => 'Pasta privada da aplicação não encontrada. Confira a instalação.']);
 exit;
}
require $entry;
