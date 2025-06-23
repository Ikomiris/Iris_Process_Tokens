<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe pour gérer les communications avec l'API ExtractIris
 * 
 * @since 1.0.6
 */
class Iris_API_Client {
    
    private $api_url;
    private $timeout;
    private $stats_option = 'iris_api_stats';
    
    public function __construct() {
        $this->api_url = IRIS_API_URL;
        $this->timeout = apply_filters('iris_api_timeout', 120);
        
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Hooks pour les statistiques
        add_action('iris_api_request_sent', array($this, 'track_api_request'), 10, 2);
        add_action('iris_api_request_failed', array($this, 'track_api_failure'), 10, 2);
    }
    
    /**
     * Fonction principale d'envoi vers l'API Python
     * (Remplace iris_send_to_python_api)
     */
    public function send_to_python_api($file_path, $user_id, $process_id) {
        // ... Code complet de iris_send_to_python_api ici ...
        // (Le code que j'ai fourni précédemment)
        
        return $this->execute_api_request($file_path, $user_id, $process_id);
    }
    
    /**
     * Test de connectivité avec l'API
     */
    public function test_connectivity() {
        $start_time = microtime(true);
        
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $this->api_url . '/health',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Iris-Process-WordPress/' . IRIS_PLUGIN_VERSION
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $response_time = round((microtime(true) - $start_time) * 1000, 2);
        
        curl_close($ch);
        
        $result = array(
            'success' => false,
            'response_time' => $response_time,
            'timestamp' => current_time('mysql')
        );
        
        if ($curl_error) {
            $result['error'] = $curl_error;
            $result['error_type'] = 'curl_error';
        } elseif ($http_code === 200) {
            $data = json_decode($response, true);
            $result['success'] = true;
            $result['api_status'] = $data['status'] ?? 'unknown';
            $result['api_version'] = $data['version'] ?? 'unknown';
            $result['service'] = $data['service'] ?? 'ExtractIris API';
        } else {
            $result['error'] = "HTTP $http_code";
            $result['error_type'] = 'http_error';
            $result['response_body'] = substr($response, 0, 500);
        }
        
        // Enregistrer le résultat du test
        update_option('iris_last_api_test', $result);
        
        return $result;
    }
    
    /**
     * Récupère les statistiques d'utilisation de l'API
     */
    public function get_statistics() {
        $stats = get_option($this->stats_option, array());
        
        $requests_sent = $stats['request_sent']['count'] ?? 0;
        $requests_failed = $stats['request_failed']['count'] ?? 0;
        $success_rate = $requests_sent > 0 ? round((($requests_sent - $requests_failed) / $requests_sent) * 100, 2) : 0;
        
        return array(
            'requests_sent' => $requests_sent,
            'requests_failed' => $requests_failed,
            'success_rate' => $success_rate,
            'most_used_preset' => $this->get_most_used_preset($stats),
            'average_file_size' => $this->get_average_file_size($stats),
            'last_24h_activity' => $this->get_recent_activity($stats),
            'peak_hour' => $this->get_peak_hour($stats)
        );
    }
    
    /**
     * Détecte le preset approprié basé sur le modèle de caméra
     */
    public function detect_camera_preset($camera_make, $camera_model) {
        // Normalisation des noms
        $make = strtolower(trim($camera_make));
        $model = strtolower(trim($camera_model));
        
        // Mapping des modèles de caméra vers les presets
        $camera_mappings = apply_filters('iris_camera_preset_mappings', array(
            'canon' => array(
                'eos r' => 'canon_eos_r',
                'eos r5' => 'canon_eos_r5', 
                'eos r6' => 'canon_eos_r6',
                'eos r6 mark ii' => 'canon_eos_r6_mark_ii',
                'eos 5d mark iv' => 'canon_5d_mark_iv',
                'eos 6d mark ii' => 'canon_6d_mark_ii'
            ),
            'nikon' => array(
                'd850' => 'nikon_d850',
                'd750' => 'nikon_d750',
                'z7' => 'nikon_z7',
                'z6' => 'nikon_z6',
                'z9' => 'nikon_z9'
            ),
            'sony' => array(
                'α7r iv' => 'sony_a7r_iv',
                'ilce-7rm4' => 'sony_a7r_iv',
                'α7 iii' => 'sony_a7_iii',
                'ilce-7m3' => 'sony_a7_iii',
                'α7r iii' => 'sony_a7r_iii',
                'ilce-7rm3' => 'sony_a7r_iii'
            )
        ));
        
        // Recherche directe
        if (isset($camera_mappings[$make])) {
            foreach ($camera_mappings[$make] as $model_pattern => $preset_id) {
                if (strpos($model, $model_pattern) !== false) {
                    if ($this->preset_exists($preset_id)) {
                        return $preset_id;
                    }
                }
            }
        }
        
        // Recherche dans les presets uploadés
        $uploaded_presets = $this->get_uploaded_presets_for_camera($make, $model);
        if (!empty($uploaded_presets)) {
            return $uploaded_presets[0];
        }
        
        return null;
    }
    
    /**
     * Vérifie si un preset existe
     */
    public function preset_exists($preset_id) {
        $upload_dir = wp_upload_dir();
        $presets_dir = $upload_dir['basedir'] . '/iris-presets/';
        
        // Vérifier les presets par défaut
        if (file_exists($presets_dir . $preset_id . '.json')) {
            return true;
        }
        
        // Vérifier les presets uploadés
        if (file_exists($presets_dir . 'uploads/' . $preset_id . '.json')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Récupère les presets uploadés compatibles avec un modèle de caméra
     */
    public function get_uploaded_presets_for_camera($camera_make, $camera_model) {
        $upload_dir = wp_upload_dir();
        $uploads_dir = $upload_dir['basedir'] . '/iris-presets/uploads/';
        
        if (!is_dir($uploads_dir)) {
            return array();
        }
        
        $compatible_presets = array();
        $preset_files = glob($uploads_dir . '*.json');
        
        foreach ($preset_files as $preset_file) {
            $preset_data = json_decode(file_get_contents($preset_file), true);
            
            if (isset($preset_data['camera_models'])) {
                foreach ($preset_data['camera_models'] as $supported_model) {
                    $supported_lower = strtolower($supported_model);
                    
                    if (strpos($supported_lower, strtolower($camera_make)) !== false &&
                        strpos($supported_lower, strtolower($camera_model)) !== false) {
                        $compatible_presets[] = basename($preset_file, '.json');
                        break;
                    }
                }
            }
        }
        
        return $compatible_presets;
    }
    
    /**
     * Met à jour les statistiques d'utilisation
     */
    public function update_stats($event_type, $data = array()) {
        $stats = get_option($this->stats_option, array());
        
        if (!isset($stats[$event_type])) {
            $stats[$event_type] = array(
                'count' => 0,
                'last_occurrence' => null,
                'data' => array()
            );
        }
        
        $stats[$event_type]['count']++;
        $stats[$event_type]['last_occurrence'] = current_time('mysql');
        
        // Garder seulement les 100 derniers événements
        if (count($stats[$event_type]['data']) >= 100) {
            array_shift($stats[$event_type]['data']);
        }
        
        $stats[$event_type]['data'][] = array(
            'timestamp' => current_time('mysql'),
            'data' => $data
        );
        
        update_option($this->stats_option, $stats);
    }
    
    /**
     * Hooks pour tracker les requêtes
     */
    public function track_api_request($data, $result) {
        $this->update_stats('request_sent', array(
            'file_type' => $data['file_type'] ?? 'unknown',
            'file_size' => $data['file_size'] ?? 0,
            'preset_used' => $data['preset_used'] ?? 'default',
            'success' => !is_wp_error($result)
        ));
    }
    
    public function track_api_failure($error_message, $context) {
        $this->update_stats('request_failed', array(
            'error' => $error_message,
            'context' => $context
        ));
    }
    
    // Méthodes privées pour les statistiques
    private function get_most_used_preset($stats) {
        $preset_usage = array();
        
        if (isset($stats['request_sent']['data'])) {
            foreach ($stats['request_sent']['data'] as $entry) {
                $preset = $entry['data']['preset_used'] ?? 'default';
                $preset_usage[$preset] = ($preset_usage[$preset] ?? 0) + 1;
            }
        }
        
        if (empty($preset_usage)) {
            return 'Aucun';
        }
        
        arsort($preset_usage);
        return key($preset_usage);
    }
    
    private function get_average_file_size($stats) {
        $sizes = array();
        
        if (isset($stats['request_sent']['data'])) {
            foreach ($stats['request_sent']['data'] as $entry) {
                if (isset($entry['data']['file_size'])) {
                    $sizes[] = $entry['data']['file_size'];
                }
            }
        }
        
        return !empty($sizes) ? array_sum($sizes) / count($sizes) : 0;
    }
    
    private function get_recent_activity($stats) {
        $now = time();
        $last_24h = $now - (24 * 60 * 60);
        $activity = 0;
        
        if (isset($stats['request_sent']['data'])) {
            foreach ($stats['request_sent']['data'] as $entry) {
                $timestamp = strtotime($entry['timestamp']);
                if ($timestamp >= $last_24h) {
                    $activity++;
                }
            }
        }
        
        return $activity;
    }
    
    private function get_peak_hour($stats) {
        $hourly_counts = array_fill(0, 24, 0);
        
        if (isset($stats['request_sent']['data'])) {
            foreach ($stats['request_sent']['data'] as $entry) {
                $hour = intval(date('H', strtotime($entry['timestamp'])));
                $hourly_counts[$hour]++;
            }
        }
        
        $peak_hour = array_search(max($hourly_counts), $hourly_counts);
        return $peak_hour . ':00';
    }
}

// Instance globale
global $iris_api_client;
$iris_api_client = new Iris_API_Client();