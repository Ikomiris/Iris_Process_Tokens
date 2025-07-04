<?php
if (!defined('ABSPATH')) {
    exit;
}

class Iris_Process_Admin_Pages {
    
    public function __construct() {
        // Cette classe peut être étendue pour des pages admin spécifiques
    }
    
    /**
     * Afficher une page d'erreur générique
     */
    public static function display_error_page($title, $message) {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>
            <div class="notice notice-error">
                <p><?php echo esc_html($message); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Afficher une page de succès générique
     */
    public static function display_success_page($title, $message) {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>
            <div class="notice notice-success">
                <p><?php echo esc_html($message); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Styles communs pour les pages admin
     */
    public static function common_admin_styles() {
        ?>
        <style>
        .iris-admin-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .iris-admin-header {
            background: linear-gradient(135deg, #0C2D39 0%, #15697B 100%);
            color: #F4F4F2;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }
        
        .iris-admin-header h1 {
            color: #3de9f4;
            margin: 0;
        }
        
        .iris-admin-section {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        
        .iris-admin-section h2 {
            color: #0C2D39;
            border-bottom: 2px solid #3de9f4;
            padding-bottom: 10px;
        }
        
        .iris-button-primary {
            background: #3de9f4;
            color: #0C2D39;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .iris-button-primary:hover {
            background: #2bc9d4;
            transform: translateY(-2px);
        }
        
        .iris-button-secondary {
            background: #F05A28;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .iris-button-secondary:hover {
            background: #e04a1a;
            transform: translateY(-2px);
        }
        </style>
        <?php
    }
}