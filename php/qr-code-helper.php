<?php
/**
 * QR Code Generator Helper
 * Generates QR code images for books
 */

function generateQRCode($qr_code_text, $size = 300) {
    try {
        // Create qr_codes directory if it doesn't exist
        $qr_dir = __DIR__ . '/../qr_codes';
        if (!file_exists($qr_dir)) {
            mkdir($qr_dir, 0755, true);
        }
        
        $filename = $qr_code_text . '.png';
        $filepath = $qr_dir . '/' . $filename;
        
        // Method 1: Use Google Charts API (Simple, no dependencies)
        $google_qr_url = "https://chart.googleapis.com/chart?chs={$size}x{$size}&cht=qr&chl=" . urlencode($qr_code_text) . "&choe=UTF-8";
        
        $qr_image = @file_get_contents($google_qr_url);
        
        if ($qr_image !== false) {
            file_put_contents($filepath, $qr_image);
            return [
                'success' => true,
                'filename' => $filename,
                'path' => "qr_codes/{$filename}",
                'full_path' => $filepath
            ];
        }
        
        // Method 2: Fallback - Create a simple placeholder image if Google API fails
        $image = imagecreate($size, $size);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        
        imagefilledrectangle($image, 0, 0, $size, $size, $white);
        
        // Add border
        imagerectangle($image, 10, 10, $size-10, $size-10, $black);
        
        // Add text
        imagestring($image, 5, $size/2 - 50, $size/2 - 10, "QR: " . $qr_code_text, $black);
        imagestring($image, 3, $size/2 - 80, $size/2 + 10, "Scan with camera", $black);
        
        imagepng($image, $filepath);
        imagedestroy($image);
        
        return [
            'success' => true,
            'filename' => $filename,
            'path' => "qr_codes/{$filename}",
            'full_path' => $filepath,
            'method' => 'fallback'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Check if QR code image exists
 */
function qrCodeExists($qr_code_text) {
    $filepath = __DIR__ . '/../qr_codes/' . $qr_code_text . '.png';
    return file_exists($filepath);
}

/**
 * Get QR code image path
 */
function getQRCodePath($qr_code_text) {
    $filename = $qr_code_text . '.png';
    $filepath = __DIR__ . '/../qr_codes/' . $filename;
    
    if (file_exists($filepath)) {
        return "qr_codes/{$filename}";
    }
    
    return null;
}
?>
