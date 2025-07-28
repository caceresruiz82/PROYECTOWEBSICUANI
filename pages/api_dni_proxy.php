<?php
/**
 * Proxy seguro para consultar la API de DNI (miapi.cloud).
 * Utiliza el método de autenticación Bearer Token.
 */

require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_GET['dni']) || !is_numeric($_GET['dni']) || strlen(trim($_GET['dni'])) != 8) {
    http_response_code(400);
    echo json_encode(['error' => 'DNI no válido.']);
    exit;
}

$dni = trim($_GET['dni']);

// Construir la URL completa para la API externa
$url = DNI_API_URL . $dni;

// Iniciar cURL
$ch = curl_init();

// Configurar las opciones de cURL
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Configurar la cabecera de autorización con el Bearer Token
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Bearer ' . DNI_API_TOKEN 
]);

// Ejecutar la solicitud
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión: ' . $error]);
    exit;
}

if ($http_code == 200) {
    echo $response;
} else {
    http_response_code($http_code);
    echo json_encode(['error' => 'No se pudo obtener la información. Código de error: ' . $http_code]);
}

exit;