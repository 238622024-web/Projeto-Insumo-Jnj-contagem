<?php
require_once __DIR__ . '/../db.php';

header('Content-Type: text/plain; charset=utf-8');

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetch();
}

function indexExists(PDO $pdo, string $table, string $indexName): bool {
    $stmt = $pdo->prepare("SHOW INDEX FROM `$table` WHERE Key_name = ?");
    $stmt->execute([$indexName]);
    return (bool)$stmt->fetch();
}

function ensureTable(PDO $pdo, string $sql): void {
    $pdo->exec($sql);
}

function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void {
    if (!columnExists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        echo "[OK] Coluna adicionada: $table.$column\n";
    } else {
        echo "[OK] Coluna já existe: $table.$column\n";
    }
}

function ensureIndex(PDO $pdo, string $table, string $indexName, string $columnsSql): void {
    if (!indexExists($pdo, $table, $indexName)) {
        $pdo->exec("ALTER TABLE `$table` ADD INDEX `$indexName` ($columnsSql)");
        echo "[OK] Índice adicionado: $table.$indexName\n";
    } else {
        echo "[OK] Índice já existe: $table.$indexName\n";
    }
}

try {
    $pdo = getPDO();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    ensureTable($pdo, "CREATE TABLE IF NOT EXISTS insumo_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_nome VARCHAR(150) NOT NULL,
        user_email VARCHAR(190) NOT NULL,
        user_role VARCHAR(20) NOT NULL DEFAULT 'user',
        batch_id VARCHAR(64) NULL,
        setor VARCHAR(120) NULL,
        data_solicitada_entrega DATE NULL,
        insumo_nome VARCHAR(190) NOT NULL,
        quantidade DECIMAL(10,2) NOT NULL DEFAULT 1,
        unidade VARCHAR(30) NOT NULL DEFAULT 'UN',
        quantidade_entregue DECIMAL(10,2) NULL,
        lote VARCHAR(100) NULL,
        fabricacao DATE NULL,
        validade DATE NULL,
        motivo_usuario TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        admin_note TEXT NULL,
        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        processed_at DATETIME NULL,
        processed_by INT NULL,
        INDEX idx_ir_status (status),
        INDEX idx_ir_batch_id (batch_id),
        INDEX idx_ir_user (user_id),
        INDEX idx_ir_requested_at (requested_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    echo "[OK] Tabela insumo_requests verificada/criada\n";

    ensureColumn($pdo, 'insumo_requests', 'batch_id', "VARCHAR(64) NULL AFTER user_role");
    ensureColumn($pdo, 'insumo_requests', 'setor', "VARCHAR(120) NULL AFTER batch_id");
    ensureColumn($pdo, 'insumo_requests', 'data_solicitada_entrega', "DATE NULL AFTER setor");
    ensureColumn($pdo, 'insumo_requests', 'quantidade_entregue', "DECIMAL(10,2) NULL AFTER unidade");
    ensureColumn($pdo, 'insumo_requests', 'lote', "VARCHAR(100) NULL AFTER quantidade_entregue");
    ensureColumn($pdo, 'insumo_requests', 'fabricacao', "DATE NULL AFTER lote");
    ensureColumn($pdo, 'insumo_requests', 'validade', "DATE NULL AFTER fabricacao");
    ensureColumn($pdo, 'insumo_requests', 'status', "VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER motivo_usuario");
    ensureColumn($pdo, 'insumo_requests', 'admin_note', "TEXT NULL AFTER status");
    ensureColumn($pdo, 'insumo_requests', 'requested_at', "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER admin_note");
    ensureColumn($pdo, 'insumo_requests', 'processed_at', "DATETIME NULL AFTER requested_at");
    ensureColumn($pdo, 'insumo_requests', 'processed_by', "INT NULL AFTER processed_at");

    ensureIndex($pdo, 'insumo_requests', 'idx_ir_status', '`status`');
    ensureIndex($pdo, 'insumo_requests', 'idx_ir_batch_id', '`batch_id`');
    ensureIndex($pdo, 'insumo_requests', 'idx_ir_user', '`user_id`');
    ensureIndex($pdo, 'insumo_requests', 'idx_ir_requested_at', '`requested_at`');

    echo "\nConcluído. Se não apareceram erros, o schema está pronto para atender/rejeitar pedidos.\n";
} catch (Throwable $e) {
    echo '[ERRO] ' . $e->getMessage() . "\n";
    exit(1);
}
