<?php
require_once __DIR__ . '/../db.php';
try {
    $pdo = getPDO();

    // Migração: avatar em usuarios
    try {
        $row = $pdo->query("SHOW COLUMNS FROM `usuarios` LIKE 'avatar'")->fetch();
        if (!$row) {
            $sql = file_get_contents(__DIR__ . '/migration_add_avatar.sql');
            $pdo->exec($sql);
            echo "Migração aplicada: coluna 'avatar' adicionada com sucesso.\n";
        } else {
            echo "Coluna 'avatar' já existe.\n";
        }
    } catch (Exception $e) {
        echo "Aviso migração avatar: " . $e->getMessage() . "\n";
    }

    // Migração: lote em insumos_jnj
    try {
        $row2 = $pdo->query("SHOW COLUMNS FROM `insumos_jnj` LIKE 'lote'")->fetch();
        if (!$row2) {
            $sql2 = file_get_contents(__DIR__ . '/migration_add_lote.sql');
            $pdo->exec($sql2);
            echo "Migração aplicada: coluna 'lote' adicionada com sucesso.\n";
        } else {
            echo "Coluna 'lote' já existe.\n";
        }
    } catch (Exception $e) {
        echo "Aviso migração lote: " . $e->getMessage() . "\n";
    }

    // Migração: tabela configuracoes
    try {
        $pdo->exec(file_get_contents(__DIR__ . '/migration_add_config_table.sql'));
        echo "Migração aplicada: tabela 'configuracoes' criada/confirmada.\n";
    } catch (Exception $e) {
        echo "Aviso migração configuracoes: " . $e->getMessage() . "\n";
    }
} catch (Exception $e) {
    echo "Erro ao aplicar migrações: " . $e->getMessage() . "\n";
}
