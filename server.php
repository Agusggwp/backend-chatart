<?php

/**
 * Custom router script for PHP built-in web server (artisan serve).
 * Provides CORS headers for static files (/storage/) so that
 * Flutter Web, React Web, and Blade Web can load media without CORS issues.
 * All /api/* and dynamic routes are delegated to Laravel's index.php and HandleCors.
 */

$publicPath = is_dir(__DIR__ . '/public') ? __DIR__ . '/public' : getcwd();
$storagePath = is_dir(__DIR__ . '/storage/app/public') ? __DIR__ . '/storage/app/public' : dirname($publicPath) . '/storage/app/public';

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? ''
);

// Intercept /storage/ requests to deliver CORS headers with static file contents
if (str_starts_with($uri, '/storage/')) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
    header('Access-Control-Allow-Headers: *');

    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    $filePath = $publicPath . $uri;
    if (file_exists($filePath) && !is_dir($filePath)) {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'mp4' => 'video/mp4',
            'pdf' => 'application/pdf',
        ];
        $mime = $mimeTypes[$ext] ?? (function_exists('mime_content_type') ? mime_content_type($filePath) : 'application/octet-stream');

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: public, max-age=86400');
        readfile($filePath);
        exit;
    }
}

// Emulate mod_rewrite for all other existing static files (like /build/..., /favicon.ico)
if ($uri !== '/' && file_exists($publicPath . $uri)) {
    return false;
}

$formattedDateTime = date('D M j H:i:s Y');
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$remoteAddress = ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') . ':' . ($_SERVER['REMOTE_PORT'] ?? '8000');
file_put_contents('php://stdout', "[$formattedDateTime] $remoteAddress [$requestMethod] URI: $uri\n");

require_once $publicPath . '/index.php';
