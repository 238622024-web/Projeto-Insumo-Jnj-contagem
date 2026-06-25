<?php

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isProductionEnvironment() {
    return defined('APP_ENV') && APP_ENV === 'production';
}

function getPDO() {

    static $pdo = null;

    if ($pdo === null) {

        $host = getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : 'localhost');
        $db   = getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : 'controle_insumos_jnj');
        $user = getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : 'root');
        $pass = getenv('DB_PASS') ?: (defined('DB_PASS') ? DB_PASS : '');
        $port = getenv('DB_PORT') ?: (defined('DB_PORT') ? DB_PORT : 3306);

        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {

            $pdo = new PDO($dsn, $user, $pass, $options);

        } catch (PDOException $e) {

            die('Erro de conexão: ' . $e->getMessage());

        }
    }

    return $pdo;
}

function flash($key, $message = null) {

    if ($message === null) {

        if (!empty($_SESSION['flash'][$key])) {

            $msg = $_SESSION['flash'][$key];

            unset($_SESSION['flash'][$key]);

            return $msg;
        }

        return null;
    }

    $_SESSION['flash'][$key] = $message;
}

function h($value) {

    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

?>