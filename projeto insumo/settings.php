<?php
require_once __DIR__ . '/db.php';

function ensureSettingsTable(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) { return; }

    $pdo->exec("CREATE TABLE IF NOT EXISTS configuracoes (
        chave VARCHAR(100) NOT NULL,
        valor TEXT NOT NULL,
        PRIMARY KEY (chave)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $ensured = true;
}

function getSetting(string $key, $default = null) {
    try {
        $pdo = getPDO();
        ensureSettingsTable($pdo);
        $stmt = $pdo->prepare('SELECT valor FROM configuracoes WHERE chave = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if ($row) {
            return $row['valor'];
        }
    } catch (Throwable $e) {
        // tabela pode não existir ainda; usar default
    }
    return $default;
}

function setSetting(string $key, $value): bool {
    try {
        $pdo = getPDO();
        ensureSettingsTable($pdo);
        $stmt = $pdo->prepare('REPLACE INTO configuracoes (chave, valor) VALUES (?, ?)');
        return $stmt->execute([$key, $value]);
    } catch (Throwable $e) {
        return false;
    }
}

function getAllSettings(): array {
    try {
        $pdo = getPDO();
        ensureSettingsTable($pdo);
        $rows = $pdo->query('SELECT chave, valor FROM configuracoes')->fetchAll();
        $out = [];
        foreach ($rows as $r) { $out[$r['chave']] = $r['valor']; }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}
