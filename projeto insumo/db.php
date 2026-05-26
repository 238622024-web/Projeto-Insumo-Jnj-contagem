<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isProductionEnvironment() {
    return true;
}

function getPDO() {

    static $pdo = null;

    if ($pdo === null) {

        $host = "sql202.infinityfree.com";
        $db   = "if0_41860253_projeto_insumo";
        $user = "if0_41860253";
        $pass = "weder64664158";

        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

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