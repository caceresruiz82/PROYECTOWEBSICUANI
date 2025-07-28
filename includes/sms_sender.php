<?php
/**
 * Función centralizada para enviar mensajes SMS a través de la API proporcionada.
 */

require_once 'config.php';

/**
 * Envía un mensaje SMS.
 *
 * @param string $to_number El número de teléfono del destinatario (sin el prefijo '11').
 * @param string $message El mensaje a enviar (debe ser corto).
 * @return bool Devuelve true si la API responde con éxito, false en caso contrario.
 */
function send_sms($to_number, $message) {
    if (empty($to_number) || empty($message)) {
        error_log("SMS no enviado: número o mensaje vacío.");
        return false;
    }

    $cleaned_number = preg_replace('/[^0-9]/', '', $to_number);
    $final_number = '11' . $cleaned_number;

    if (mb_strlen($message, 'UTF-8') > 160) {
        $message = mb_substr($message, 0, 157, 'UTF-8') . '...';
    }
    
    // --- CORRECCIÓN CLAVE ---
    // Eliminamos la línea urlencode($message) de aquí.
    // http_build_query se encargará de la codificación necesaria.
    
    $now = new DateTime();
    $schedule_time = $now->format('Y-m-d H:i:s');

    $url = SMS_API_URL . '?' . http_build_query([
        'app' => 'ws',
        'u' => SMS_API_USER,
        'h' => SMS_API_TOKEN,
        'op' => 'pv',
        'to' => $final_number,
        'msg' => $message, // Pasamos el mensaje directamente
        'schedule' => $schedule_time
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response_str = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $response_json = json_decode($response_str, true);
    
    if ($http_code == 200 && isset($response_json['data'][0]['status']) && $response_json['data'][0]['status'] === 'OK') {
        error_log("SMS enviado a {$final_number}. Respuesta API: {$response_str}");
        return true;
    } else {
        error_log("Error al enviar SMS a {$final_number}. Código HTTP: {$http_code}. Respuesta API: {$response_str}");
        return false;
    }
}
?>