<?php
/**
 * TESTE DE CARREGAMENTO DE ASSETS
 * Verifique se CSS e JavaScript estão sendo carregados
 */

// Detectar o diretório base igual ao header.php
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\','/', $_SERVER['SCRIPT_NAME']) : '';
$scriptDir = '/' . trim(dirname($scriptName), '/');
$projectBase = $scriptDir === '/' ? '' : $scriptDir;

// Arquivos CSS esperados
$cssFiles = [
    'style.css' => 'CSS Principal',
    'assets/css/login.css' => 'CSS do Login',
    'assets/css/header-footer.css' => 'CSS Header/Footer',
    'assets/css/forgot-password.css' => 'CSS Recuperar Senha',
];

// Arquivos JS esperados
$jsFiles = [
    'assets/js/login.js' => 'JS do Login',
    'assets/js/header-footer.js' => 'JS Header/Footer',
    'assets/js/profile.js' => 'JS do Perfil',
    'assets/js/dashboard.js' => 'JS do Dashboard',
    'assets/js/create-account.js' => 'JS Criar Conta',
    'assets/js/delete-confirmation.js' => 'JS Excluir',
    'assets/js/settings.js' => 'JS Configurações',
    'assets/js/cadastro.js' => 'JS Cadastro',
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Carregamento de Assets</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        h2 { color: #0066cc; margin-top: 20px; }
        .ok { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .file-list {
            list-style: none;
            padding: 0;
        }
        .file-list li {
            padding: 8px 12px;
            border-left: 4px solid #0066cc;
            margin: 5px 0;
            background: #f9f9f9;
        }
        .file-list li.ok {
            border-left-color: green;
            background: #f0fff0;
        }
        .file-list li.error {
            border-left-color: red;
            background: #fff0f0;
        }
        .status {
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✅ Teste de Carregamento de Assets</h1>
        <p><strong>Project Base:</strong> <code><?= h($projectBase ?: '/') ?></code></p>
        <p><strong>Data:</strong> <?= date('d/m/Y H:i:s') ?></p>

        <h2>📁 Arquivos CSS</h2>
        <ul class="file-list">
            <?php foreach ($cssFiles as $file => $desc): ?>
                <?php 
                    $fullPath = __DIR__ . '/' . $file;
                    $exists = file_exists($fullPath);
                    $class = $exists ? 'ok' : 'error';
                    $status = $exists ? '✓ ENCONTRADO' : '✗ NÃO ENCONTRADO';
                ?>
                <li class="<?= $class ?>">
                    <strong><?= $desc ?></strong><br>
                    Arquivo: <code><?= $file ?></code><br>
                    Caminho relativo: <code><?= $projectBase ?>/<?= $file ?></code><br>
                    Status: <span class="<?= $class ?>"><?= $status ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <h2>⚙️ Arquivos JavaScript</h2>
        <ul class="file-list">
            <?php foreach ($jsFiles as $file => $desc): ?>
                <?php 
                    $fullPath = __DIR__ . '/' . $file;
                    $exists = file_exists($fullPath);
                    $class = $exists ? 'ok' : 'error';
                    $status = $exists ? '✓ ENCONTRADO' : '✗ NÃO ENCONTRADO';
                ?>
                <li class="<?= $class ?>">
                    <strong><?= $desc ?></strong><br>
                    Arquivo: <code><?= $file ?></code><br>
                    Caminho relativo: <code><?= $projectBase ?>/<?= $file ?></code><br>
                    Status: <span class="<?= $class ?>"><?= $status ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <h2>📊 Resumo</h2>
        <?php 
            $cssCount = count(array_filter($cssFiles, fn($f) => file_exists(__DIR__ . '/' . $f)));
            $jsCount = count(array_filter($jsFiles, fn($f) => file_exists(__DIR__ . '/' . $f)));
            $cssTotal = count($cssFiles);
            $jsTotal = count($jsFiles);
            $allOk = ($cssCount === $cssTotal) && ($jsCount === $jsTotal);
        ?>
        <div class="status <?= $allOk ? 'success' : 'warning' ?>">
            <p>
                <strong>CSS:</strong> <?= $cssCount ?>/<?= $cssTotal ?> arquivos encontrados<br>
                <strong>JavaScript:</strong> <?= $jsCount ?>/<?= $jsTotal ?> arquivos encontrados<br>
                <strong>Total:</strong> <?= ($cssCount + $jsCount) ?>/<?= ($cssTotal + $jsTotal) ?> arquivos
            </p>
            <?php if ($allOk): ?>
                <p style="color: green; font-weight: bold;">✅ TUDO OK! Todos os arquivos foram encontrados.</p>
            <?php else: ?>
                <p style="color: orange; font-weight: bold;">⚠️ ALGUNS ARQUIVOS ESTÃO FALTANDO!</p>
            <?php endif; ?>
        </div>

        <h2>💡 Dicas para Verificar</h2>
        <ol>
            <li>Se todos os arquivos estão <span class="ok">✓ ENCONTRADOS</span>, tudo está funcionando!</li>
            <li>No navegador, abra as Developer Tools (F12) → Console</li>
            <li>Verifique se não há erros de 404 para os arquivos</li>
            <li>Vá para a aba Network e verifique cada arquivo CSS/JS</li>
            <li>Se algum arquivo não carregar, verifique o caminho relativo</li>
        </ol>

        <h2>🔗 Links para Testar</h2>
        <ul>
            <li><a href="login.php">Login</a> - Testa assets/css/login.css e assets/js/login.js</li>
            <li><a href="create-account.php">Criar Conta</a> - Testa assets/js/create-account.js</li>
            <li><a href="forgot-password.php">Recuperar Senha</a> - Testa assets/css/forgot-password.css</li>
            <li><a href="dashboard.html">Dashboard</a> - Testa assets/js/dashboard.js</li>
        </ul>

        <hr style="margin-top: 30px; border: none; border-top: 1px solid #ddd;">
        <p style="color: #999; font-size: 12px;">
            Página de teste criada em: 16/01/2026<br>
            Para remover este arquivo, delete: <code>test-assets.php</code>
        </p>
    </div>

    <script>
        console.log('✅ JavaScript está funcionando!');
        console.log('Project Base:', '<?= $projectBase ?>');
        console.log('Arquivos CSS esperados:', <?= json_encode(count($cssFiles)) ?>);
        console.log('Arquivos JS esperados:', <?= json_encode(count($jsFiles)) ?>);
    </script>
</body>
</html>
