<?php
require_once __DIR__ . '/db.php';

function getSetting(string $key, $default = null) {
    try {
        $pdo = getPDO();
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
        $stmt = $pdo->prepare('REPLACE INTO configuracoes (chave, valor) VALUES (?, ?)');
        return $stmt->execute([$key, $value]);
    } catch (Throwable $e) {
        return false;
    }
}

function getAllSettings(): array {
    try {
        $pdo = getPDO();
        $rows = $pdo->query('SELECT chave, valor FROM configuracoes')->fetchAll();
        $out = [];
        foreach ($rows as $r) { $out[$r['chave']] = $r['valor']; }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}
