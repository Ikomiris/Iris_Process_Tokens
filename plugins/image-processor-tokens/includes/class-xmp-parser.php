<?php
if (!defined('ABSPATH')) {
    exit;
}

class Iris_XMP_Parser {
    
    public function parse_file($xmp_file_path) {
        if (!file_exists($xmp_file_path)) {
            throw new Exception('Fichier XMP non trouvé');
        }
        
        $xmp_content = file_get_contents($xmp_file_path);
        return $this->parse_xmp_content($xmp_content);
    }
    
    public function parse_xmp_content($xmp_content) {
        // Chargement XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmp_content);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            throw new Exception('Erreur parsing XML: ' . implode(', ', array_column($errors, 'message')));
        }
        
        // Extraction des namespaces
        $namespaces = $xml->getNamespaces(true);
        $crs_namespace = 'http://ns.adobe.com/camera-raw-settings/1.0/';
        
        // Recherche de la section Camera Raw Settings
        $crs_data = array();
        
        foreach ($xml->xpath('//rdf:Description') as $description) {
            $attributes = $description->attributes($crs_namespace);
            if ($attributes) {
                foreach ($attributes as $name => $value) {
                    $crs_data[$name] = (string)$value;
                }
            }
        }
        
        if (empty($crs_data)) {
            throw new Exception('Aucune donnée Camera Raw trouvée dans le fichier XMP');
        }
        
        return $this->organize_crs_data($crs_data);
    }
    
    private function organize_crs_data($raw_data) {
        return array(
            'basic' => $this->extract_basic_settings($raw_data),
            'tone_curve' => $this->extract_tone_curve($raw_data),
            'color' => $this->extract_color_settings($raw_data),
            'detail' => $this->extract_detail_settings($raw_data),
            'lens' => $this->extract_lens_settings($raw_data),
            'effects' => $this->extract_effects_settings($raw_data),
            'metadata' => $this->extract_metadata($raw_data)
        );
    }
    
    private function extract_basic_settings($data) {
        return array(
            'white_balance' => $data['WhiteBalance'] ?? 'As Shot',
            'temperature' => isset($data['Temperature']) ? intval($data['Temperature']) : null,
            'tint' => isset($data['Tint']) ? intval($data['Tint']) : null,
            'exposure' => isset($data['Exposure2012']) ? floatval($data['Exposure2012']) : 0,
            'contrast' => isset($data['Contrast2012']) ? intval($data['Contrast2012']) : 0,
            'highlights' => isset($data['Highlights2012']) ? intval($data['Highlights2012']) : 0,
            'shadows' => isset($data['Shadows2012']) ? intval($data['Shadows2012']) : 0,
            'whites' => isset($data['Whites2012']) ? intval($data['Whites2012']) : 0,
            'blacks' => isset($data['Blacks2012']) ? intval($data['Blacks2012']) : 0,
            'texture' => isset($data['Texture']) ? intval($data['Texture']) : 0,
            'clarity' => isset($data['Clarity2012']) ? intval($data['Clarity2012']) : 0,
            'dehaze' => isset($data['Dehaze']) ? intval($data['Dehaze']) : 0,
            'vibrance' => isset($data['Vibrance']) ? intval($data['Vibrance']) : 0,
            'saturation' => isset($data['Saturation']) ? intval($data['Saturation']) : 0
        );
    }
    
    private function extract_color_settings($data) {
        $color_settings = array(
            'hue_adjustments' => array(),
            'saturation_adjustments' => array(),
            'luminance_adjustments' => array()
        );
        
        $colors = array('Red', 'Orange', 'Yellow', 'Green', 'Aqua', 'Blue', 'Purple', 'Magenta');
        
        foreach ($colors as $color) {
            $color_settings['hue_adjustments'][strtolower($color)] = isset($data["HueAdjustment$color"]) ? intval($data["HueAdjustment$color"]) : 0;
            $color_settings['saturation_adjustments'][strtolower($color)] = isset($data["SaturationAdjustment$color"]) ? intval($data["SaturationAdjustment$color"]) : 0;
            $color_settings['luminance_adjustments'][strtolower($color)] = isset($data["LuminanceAdjustment$color"]) ? intval($data["LuminanceAdjustment$color"]) : 0;
        }
        
        return $color_settings;
    }
    
    private function extract_detail_settings($data) {
        return array(
            'sharpness' => isset($data['Sharpness']) ? intval($data['Sharpness']) : 40,
            'sharpen_radius' => isset($data['SharpenRadius']) ? floatval($data['SharpenRadius']) : 1.0,
            'sharpen_detail' => isset($data['SharpenDetail']) ? intval($data['SharpenDetail']) : 25,
            'noise_reduction' => isset($data['ColorNoiseReduction']) ? intval($data['ColorNoiseReduction']) : 25,
            'luminance_smoothing' => isset($data['LuminanceSmoothing']) ? intval($data['LuminanceSmoothing']) : 0
        );
    }
    
    private function extract_lens_settings($data) {
        return array(
            'lens_profile_enable' => isset($data['LensProfileEnable']) ? boolval($data['LensProfileEnable']) : false,
            'auto_lateral_ca' => isset($data['AutoLateralCA']) ? boolval($data['AutoLateralCA']) : false,
            'vignette_amount' => isset($data['VignetteAmount']) ? intval($data['VignetteAmount']) : 0
        );
    }
    
    private function extract_effects_settings($data) {
        return array(
            'grain_amount' => isset($data['GrainAmount']) ? intval($data['GrainAmount']) : 0,
            'post_crop_vignette' => isset($data['PostCropVignetteAmount']) ? intval($data['PostCropVignetteAmount']) : 0
        );
    }
    
    private function extract_tone_curve($data) {
        return array(
            'tone_curve_name' => $data['ToneCurveName2012'] ?? 'Linear'
        );
    }
    
    private function extract_metadata($data) {
        return array(
            'version' => $data['Version'] ?? null,
            'process_version' => $data['ProcessVersion'] ?? null,
            'preset_type' => $data['PresetType'] ?? null,
            'has_settings' => isset($data['HasSettings']) ? boolval($data['HasSettings']) : false
        );
    }
}