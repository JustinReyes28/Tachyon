<?php
// debug_site.php
// This script is designed to output errors directly to the browser
// to help diagnose HTTP 500 errors.

// 1. Enable full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Tachyon Debug Script</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// 2. Check strict types/syntax support (basic check)
echo "<h2>1. Syntax Check</h2>";
echo "<p>If you see this, basic PHP is working.</p>";

// 3. Test Database Connection
echo "<h2>2. Database Connection Test</h2>";

$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    echo "<p>Found .env file.</p>";
    $env = parse_ini_file($envFile);

    if ($env) {
        $host = $env['DB_HOST'] ?? 'undefined';
        $user = $env['DB_USER'] ?? 'undefined';
        $name = $env['DB_NAME'] ?? 'undefined';

        echo "<p>Attempting to connect to <strong>$host</strong> over DB <strong>$name</strong>...</p>";

        try {
            $conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);

            if ($conn->connect_error) {
                echo "<p style='color:red'>Connection Failed: " . $conn->connect_error . "</p>";
            } else {
                echo "<p style='color:green'><strong>Database connection successful!</strong></p>";

                // Try a simple query
                $result = $conn->query("SELECT 1");
                if ($result) {
                    echo "<p>Simple query executing correctly.</p>";
                } else {
                    echo "<p style='color:red'>Query failed: " . $conn->error . "</p>";
                }

                $conn->close();
            }
        } catch (Exception $e) {
            echo "<p style='color:red'>Exception: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color:red'>Could not parse .env file!</p>";
    }
} else {
    echo "<p style='color:red'>.env file NOT FOUND at $envFile</p>";
}

// 4. Test Application Logic
echo "<h2>3. Application Logic Test</h2>";

echo "<p>Testing <code>includes/functions.php</code>...</p>";
$functionsFile = __DIR__ . '/includes/functions.php';

if (file_exists($functionsFile)) {
    try {
        require_once $functionsFile;
        echo "<p style='color:green'>Successfully loaded <code>functions.php</code></p>";

        // Test asset_url
        $url = asset_url('style.css');
        echo "<p>asset_url('style.css') returned: " . htmlspecialchars($url) . "</p>";

        // Test sanitize_html (checks DOMDocument)
        if (class_exists('DOMDocument')) {
            echo "<p>DOMDocument class exists.</p>";
            $clean = sanitize_html('<script>alert(1)</script><b>Safe</b>');
            echo "<p>sanitize_html output: " . htmlspecialchars($clean) . "</p>";
        } else {
            echo "<p style='color:red'><strong>CRITICAL: DOMDocument class NOT found.</strong> Enable php-xml/php-dom extension.</p>";
        }


    } catch (Throwable $e) {
        echo "<p style='color:red'>Error loading functions.php: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:red'>Missing <code>includes/functions.php</code></p>";

    // Help user debug path issues
    echo "<div style='background:#f8d7da; padding:10px; border:1px solid #f5c6cb;'>";
    echo "<h3>Debugging File Structure:</h3>";
    echo "<p>Script is running in: <code>" . __DIR__ . "</code></p>";

    echo "<p><strong>Root Directory Contents:</strong></p><pre>";
    $files = scandir(__DIR__);
    print_r($files);
    echo "</pre>";

    $includesDir = __DIR__ . '/includes';
    if (is_dir($includesDir)) {
        echo "<p><strong>'includes' Directory Contents:</strong></p><pre>";
        $incFiles = scandir($includesDir);
        print_r($incFiles);
        echo "</pre>";
    } else {
        echo "<p><strong>'includes' directory NOT FOUND.</strong> Did you upload the folder?</p>";
    }
    echo "</div>";
}


// 5. Session Test
echo "<h2>4. Session Test</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['debug_test'] = 'working';
echo "<p>Session status: " . session_status() . "</p>";
if (isset($_SESSION['debug_test']) && $_SESSION['debug_test'] === 'working') {
    echo "<p style='color:green'>Sessions are working.</p>";
} else {
    echo "<p style='color:red'>Sessions might be broken.</p>";
}

echo "<hr>";
echo "<p><em>If you see this entire page, the critical backend components are functional.</em></p>";
?>