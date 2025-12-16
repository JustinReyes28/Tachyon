<?php
// Load environment variables from .env file
$envFile = __DIR__ . '/.env';

// Enable error handling to prevent 500 errors from being displayed
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
// Custom function to load environment variables safely
    function loadEnv($path) {
        if (!file_exists($path)) {
            throw new Exception('Configuration file not found. Please contact the administrator.');
        }

        // Try using native parse_ini_file first
        $env = @parse_ini_file($path);

        // Fallback to manual parsing if native function fails or returns empty
        if (!$env) {
            $env = [];
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Skip comments and empty lines
                if (empty($line) || strpos($line, '#') === 0 || strpos($line, ';') === 0) {
                    continue;
                }

                // Parse key=value
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    
                    // Remove quotes if present (both single and double)
                    $value = trim($value, '"\'');
                    
                    $env[$key] = $value;
                }
            }
        }
        
        return $env;
    }

    $env = loadEnv($envFile);

    // Check if we managed to load any configuration
    if (empty($env)) {
        throw new Exception('Configuration file is unreadable or empty. Please contact the administrator.');
    }

    // Validate required environment variables
    $required_keys = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'];
    foreach ($required_keys as $key) {
        if (!array_key_exists($key, $env)) {
            throw new Exception("Missing required configuration. Please contact the administrator.");
        }
    }

    // Database connection parameters from .env
    define('DB_HOST', $env['DB_HOST']);
    define('DB_USER', $env['DB_USER']);
    define('DB_PASS', $env['DB_PASS']);
    define('DB_NAME', $env['DB_NAME']);

    // Create a new MySQLi connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Check the connection
    if ($conn->connect_error) {
        // Generate a unique request ID for correlation
        $requestId = session_id() ?: bin2hex(random_bytes(16));

        // Log detailed error (safe logging that won't cause 500)
        error_log("Database connection failed [$requestId]: " . $conn->connect_error);

        // User-friendly error
        throw new Exception('Unable to connect to the database. Please try again later.');
    }

    // Set character set to UTF-8 for proper encoding
    $conn->set_charset('utf8mb4');

} catch (Exception $e) {
    // Log the actual error for debugging
    error_log("DB Connect Error: " . $e->getMessage());

    // Show user-friendly error page instead of 500
    http_response_code(503);
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Service Temporarily Unavailable</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
            .error-box { background: white; padding: 40px; border-radius: 10px; max-width: 500px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #dc3545; }
            p { color: #666; }
            a { color: #007bff; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>Service Temporarily Unavailable</h1>
            <p>' . htmlspecialchars($e->getMessage()) . '</p>
            <p><a href="javascript:location.reload()">Try Again</a> | <a href="/">Go Home</a></p>
        </div>
    </body>
    </html>';
    exit();
}
?>