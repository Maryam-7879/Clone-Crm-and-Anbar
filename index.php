<?php
// Front Controller - Handle all requests
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];

// Remove query string from request URI
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// Remove the script directory from the path
$basePath = dirname($scriptName);
if ($basePath !== '/') {
    $requestPath = substr($requestPath, strlen($basePath));
}

// If request is for root, serve the main application
if ($requestPath === '/' || $requestPath === '') {
    // Include the main application file
    require_once __DIR__ . '/public/index.php';
    exit();
}

// Handle other requests by serving files from public directory
$publicPath = __DIR__ . '/public' . $requestPath;

if (file_exists($publicPath) && is_file($publicPath)) {
    // Check if it's a PHP file
    if (pathinfo($publicPath, PATHINFO_EXTENSION) === 'php') {
        // Include PHP files
        require_once $publicPath;
    } else {
        // Serve static files
        $mimeType = mime_content_type($publicPath);
        if ($mimeType) {
            header('Content-Type: ' . $mimeType);
        }
        readfile($publicPath);
    }
} else {
    // File not found, show 404 or redirect to main app
    http_response_code(404);
    require_once __DIR__ . '/public/index.php';
}
?>
