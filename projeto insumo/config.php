<?php
// Configurações padrão para uso local no XAMPP.
// Variáveis de ambiente continuam tendo prioridade quando existirem.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'controle_insumos_jnj');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('APP_ENV', getenv('APP_ENV') ?: 'local');

?>
