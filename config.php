<?php
// AZARO - Own Your Style
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'azaro_fashion');
define('DB_USER', 'root');
define('DB_PASS', '');

define('SITE_NAME', 'AZARO');
define('SITE_TAGLINE', 'Own Your Style');
define('BASE_URL', '/azaro_fashion'); // change if your folder name is different

// Gmail SMTP. Keep the App Password private and never commit a real password to GitHub.
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'safetechitbd@gmail.com');
define('SMTP_PASS', '');
define('SMTP_FROM_EMAIL', SMTP_USER);
define('SMTP_FROM_NAME', 'AZARO | Own Your Style');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
