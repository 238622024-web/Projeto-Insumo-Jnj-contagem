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

    // Migração: data_contagem em insumos_jnj
    try {
        $rowDataContagem = $pdo->query("SHOW COLUMNS FROM `insumos_jnj` LIKE 'data_contagem'")->fetch();
        if (!$rowDataContagem) {
            $sqlDataContagem = file_get_contents(__DIR__ . '/migration_add_data_contagem.sql');
            $pdo->exec($sqlDataContagem);
            echo "Migração aplicada: coluna 'data_contagem' adicionada com sucesso.\n";
        } else {
            echo "Coluna 'data_contagem' já existe.\n";
        }
    } catch (Exception $e) {
        echo "Aviso migração data_contagem: " . $e->getMessage() . "\n";
    }

    // Migração: unidade em insumos_jnj
    try {
        $rowUnidade = $pdo->query("SHOW COLUMNS FROM `insumos_jnj` LIKE 'unidade'")->fetch();
        if (!$rowUnidade) {
            $sqlUnidade = file_get_contents(__DIR__ . '/migration_add_unidade.sql');
            $pdo->exec($sqlUnidade);
            echo "Migração aplicada: coluna 'unidade' adicionada com sucesso.\n";
        } else {
            echo "Coluna 'unidade' já existe.\n";
        }
    } catch (Exception $e) {
        echo "Aviso migração unidade: " . $e->getMessage() . "\n";
    }

    // Migração: codigo_barra em insumos_jnj
    try {
        $rowCodigoBarra = $pdo->query("SHOW COLUMNS FROM `insumos_jnj` LIKE 'codigo_barra'")->fetch();
        if (!$rowCodigoBarra) {
            $sqlCodigoBarra = file_get_contents(__DIR__ . '/migration_add_codigo_barra.sql');
            $pdo->exec($sqlCodigoBarra);
            echo "Migração aplicada: coluna 'codigo_barra' adicionada com sucesso.\n";
        } else {
            echo "Coluna 'codigo_barra' já existe.\n";
        }
    } catch (Exception $e) {
        echo "Aviso migração codigo_barra: " . $e->getMessage() . "\n";
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
