<?php
// Inicializa o banco de dados local (XAMPP)
// Uso: http://localhost/projeto-insumo/database/init_db.php
// Após concluir, APAGUE este arquivo por segurança.

header('Content-Type: text/plain; charset=utf-8');

$ok = function ($msg) { echo "[OK] $msg\n"; };
$err = function ($msg) { echo "[ERRO] $msg\n"; };

try {
    require_once __DIR__ . '/../config.php';

    // Permitir variáveis de ambiente (Docker) com fallback para config.php
    $host = getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : 'localhost');
    $user = getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : 'root');
    $pass = getenv('DB_PASS') ?: (defined('DB_PASS') ? DB_PASS : '');
    $port = getenv('DB_PORT') ?: (defined('DB_PORT') ? DB_PORT : 3306);
    $db   = getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : 'controle_insumos_jnj');

    $dsnServer = "mysql:host=$host;port=$port;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ];

    $pdoServer = new PDO($dsnServer, $user, $pass, $options);
    $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `$db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $ok("Database criada/confirmada: $db");

    // Importar schema.sql
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new RuntimeException('Arquivo schema.sql não encontrado');
    }
    $sqlSchema = file_get_contents($schemaFile);
    // Garantir USE correto
    $sqlSchema = preg_replace('/USE\s+`?controle_insumos_jnj`?;?/i', 'USE `' . $db . '`;', $sqlSchema);
    $pdoServer->exec($sqlSchema);
    $ok('Schema importado');

    // Importar seed.sql (opcional)
    $seedFile = __DIR__ . '/seed.sql';
    if (file_exists($seedFile)) {
        $sqlSeed = file_get_contents($seedFile);
        $sqlSeed = preg_replace('/USE\s+`?controle_insumos_jnj`?;?/i', 'USE `' . $db . '`;', $sqlSeed);
        $pdoServer->exec($sqlSeed);
        $ok('Seed importado');
    } else {
        echo "[INFO] Seed.sql não encontrado (ok).\n";
    }

    echo "\nConcluído. Acesse /health.php para verificar.\n";
    echo "IMPORTANTE: Apague este arquivo (database/init_db.php) após a inicialização.\n";
} catch (Throwable $e) {
    $err($e->getMessage());
}

?>
