<?php
/**
 * Fonctions d'API pour Iris Process Tokens
 * 
 * @package IrisProcessTokens
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Envoie une demande de traitement à l'API Python
 * 
 * @since 1.0.0
 * @param int $user_id ID de l'utilisateur WordPress
 * @param string $job_id Job ID (external_id)
 * @param string $source_file Nom du fichier avec préfixe date
 * @param string $preset_file Nom/ID du preset utilisé
 * @return array|WP_Error Réponse de l'API ou erreur
 */
function iris_send_to_python_api($user_id, $job_id, $source_file, $preset_file = 'default') {
    // URL de l'API Python
    $api_url = 'https://btrjln6o7e.execute-api.eu-west-1.amazonaws.com/iris4pro/customers/process';
    
    // Clé d'authentification
    $api_key = 'sk-JYNhme53XA61qLy0DQ7uT9FcofWarvSV';
    
    // Préparer les données en form-data
    $api_payload = array(
        'client_id' => strval($user_id),
        'external_id' => strval($job_id),
        'source_file' => $source_file,
        'preset_file' => $preset_file
    );
    
    // Configuration de la requête
    $api_args = array(
        'headers' => array(
            'api_key' => $api_key,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ),
        'body' => http_build_query($api_payload),
        'timeout' => 30,
        'method' => 'POST'
    );
    
    // Log de la requête
    iris_log_error('IRIS API: Envoi vers ' . $api_url . ' avec payload: ' . json_encode($api_payload));
    
    // Envoi de la requête
    $api_response = wp_remote_post($api_url, $api_args);
    
    // Gestion de la réponse
    if (is_wp_error($api_response)) {
        $error_message = $api_response->get_error_message();
        iris_log_error('IRIS API ERROR: ' . $error_message);
        return new WP_Error('api_request_failed', 'Erreur lors de l\'appel API: ' . $error_message);
    }
    
    $response_code = wp_remote_retrieve_response_code($api_response);
    $response_body = wp_remote_retrieve_body($api_response);
    
    iris_log_error('IRIS API: Réponse ' . $response_code . ' - ' . $response_body);
    
    // Vérifier le code de réponse
    if ($response_code < 200 || $response_code >= 300) {
        return new WP_Error('api_error', 'Erreur API (code ' . $response_code . '): ' . $response_body);
    }
    
    // Décoder la réponse JSON
    $decoded_response = json_decode($response_body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('api_json_error', 'Erreur de décodage JSON: ' . json_last_error_msg());
    }
    
    return $decoded_response;
}