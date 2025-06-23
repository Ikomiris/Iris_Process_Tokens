<?php
if (!defined('ABSPATH')) {
    exit;
}

class Iris_Preset_Converter {
    
    public function xmp_to_json($xmp_data) {
        $preset = array(
            'name' => $this->generate_preset_name($xmp_data),
            'description' => 'Preset importé depuis Lightroom',
            'version' => '1.0',
            'raw_params' => $this->convert_raw_params($xmp_data['basic']),
            'tone_adjustments' => $this->convert_tone_adjustments($xmp_data['basic']),
            'color_adjustments' => $xmp_data['color'],
            'detail' => $xmp_data['detail'],
            'lens_corrections' => $xmp_data['lens'],
            'effects' => $xmp_data['effects'],
            'original_metadata' => $xmp_data['metadata']
        );
        
        return $preset;
    }
    
    private function generate_preset_name($xmp_data) {
        $metadata = $xmp_data['metadata'];
        
        if (isset($metadata['preset_type']) && $metadata['preset_type'] !== 'Normal') {
            return "Preset Lightroom - " . $metadata['preset_type'];
        }
        
        return "Preset Lightroom - " . date('Y-m-d H:i:s');
    }
    
    private function convert_raw_params($basic_data) {
        $white_balance = 'custom';
        if ($basic_data['white_balance'] === 'As Shot') {
            $white_balance = 'camera';
        }
        
        return array(
            'white_balance' => $white_balance,
            'temperature' => $basic_data['temperature'] ?? 5500,
            'tint' => $basic_data['tint'] ?? 0,
            'output_color' => 'adobe',
            'output_bps' => 16,
            'gamma' => array(2.2, 4.5),
            'no_auto_bright' => true,
            'noise_thr' => 100,
            'use_camera_wb' => ($white_balance === 'camera')
        );
    }
    
    private function convert_tone_adjustments($basic_data) {
        return array(
            'exposure' => ($basic_data['exposure'] ?? 0) / 100.0,  // Lightroom utilise des centièmes
            'contrast' => ($basic_data['contrast'] ?? 0) / 100.0,
            'highlights' => ($basic_data['highlights'] ?? 0) / 100.0,
            'shadows' => ($basic_data['shadows'] ?? 0) / 100.0,
            'whites' => ($basic_data['whites'] ?? 0) / 100.0,
            'blacks' => ($basic_data['blacks'] ?? 0) / 100.0,
            'texture' => ($basic_data['texture'] ?? 0) / 100.0,
            'clarity' => ($basic_data['clarity'] ?? 0) / 100.0,
            'dehaze' => ($basic_data['dehaze'] ?? 0) / 100.0,
            'vibrance' => ($basic_data['vibrance'] ?? 0) / 100.0,
            'saturation' => ($basic_data['saturation'] ?? 0) / 100.0
        );
    }
}