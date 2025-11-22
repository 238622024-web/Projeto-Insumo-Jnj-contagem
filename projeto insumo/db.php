<?php
// Conexão com MySQL usando PDO
// Ajuste as credenciais conforme seu ambiente.
// SQL de criação da tabela:
/*
CREATE TABLE insumos_jnj (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    posicao VARCHAR(20) NOT NULL,
    quantidade INT NOT NULL DEFAULT 0,
    data_entrada DATE NOT NULL,
    validade DATE NOT NULL,
    observacoes TEXT
);

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        // Carregar configuração externa se existir (fallback se não houver variáveis de ambiente)
        if (file_exists(__DIR__ . '/config.php')) {
            require_once __DIR__ . '/config.php';
        }

        // Permitir configuração via variáveis de ambiente (Docker/Kubernetes)
        $host = getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : 'localhost');
        $db   = getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : 'controle_insumos_jnj');
        $user = getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : 'root');
        $pass = getenv('DB_PASS') ?: (defined('DB_PASS') ? DB_PASS : '');
        $port = getenv('DB_PORT') ?: (defined('DB_PORT') ? DB_PORT : '');
        $charset = 'utf8mb4';
        $portPart = $port ? ';port=' . $port : '';
        $dsn = "mysql:host=$host;dbname=$db;charset=$charset" . $portPart;
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

function flash(string $key, ?string $message = null) {
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

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }

?>