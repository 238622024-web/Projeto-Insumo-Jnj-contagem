<?php
require_once __DIR__ . '/db.php';

function ensureUserAuthSchema(): void {
    static $checked = false;
    if ($checked) {
        return;
    }

    $pdo = getPDO();
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        senha_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'user',
        aprovado TINYINT(1) NOT NULL DEFAULT 0,
        aprovado_em DATETIME NULL,
        aprovado_por INT NULL,
        remember_token_hash CHAR(64) NULL,
        remember_token_expires_at DATETIME NULL,
        avatar VARCHAR(255) NULL,
        preferred_theme VARCHAR(20) NOT NULL DEFAULT 'claro',
        preferred_language VARCHAR(10) NOT NULL DEFAULT 'pt-br',
        email_notifications TINYINT(1) NOT NULL DEFAULT 1,
        security_notifications TINYINT(1) NOT NULL DEFAULT 1,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_usuarios_aprovado (aprovado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $cols = $pdo->query('SHOW COLUMNS FROM usuarios')->fetchAll();
    $existing = [];
    foreach ($cols as $col) {
        $existing[$col['Field']] = true;
    }

    if (empty($existing['role'])) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user' AFTER senha_hash");
    }
    if (empty($existing['aprovado'])) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN aprovado TINYINT(1) NOT NULL DEFAULT 0 AFTER role");
        $pdo->exec('ALTER TABLE usuarios ADD INDEX idx_usuarios_aprovado (aprovado)');
    }
    if (empty($existing['aprovado_em'])) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN aprovado_em DATETIME NULL AFTER aprovado');
    }
    if (empty($existing['aprovado_por'])) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN aprovado_por INT NULL AFTER aprovado_em');
    }
    if (empty($existing['remember_token_hash'])) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN remember_token_hash CHAR(64) NULL AFTER aprovado_por');
    }
    if (empty($existing['remember_token_expires_at'])) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN remember_token_expires_at DATETIME NULL AFTER remember_token_hash');
    }
    if (empty($existing['must_change_password'])) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER remember_token_expires_at');
        $pdo->exec('ALTER TABLE usuarios ADD INDEX idx_usuarios_must_change_password (must_change_password)');
    }
    if (empty($existing['temp_password_expires_at'])) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN temp_password_expires_at DATETIME NULL AFTER must_change_password');
    }

    if (empty($existing['last_login_at'])) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN last_login_at DATETIME NULL AFTER avatar');
    }
    if (empty($existing['last_login_ip'])) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN last_login_ip VARCHAR(45) NULL AFTER last_login_at');
    }
    if (empty($existing['criado_em'])) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN criado_em DATETIME DEFAULT CURRENT_TIMESTAMP AFTER last_login_ip');
    }
    if (empty($existing['preferred_theme'])) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN preferred_theme VARCHAR(20) NOT NULL DEFAULT 'claro' AFTER avatar");
    }
    if (empty($existing['preferred_language'])) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN preferred_language VARCHAR(10) NOT NULL DEFAULT 'pt-br' AFTER preferred_theme");
    }
    if (empty($existing['email_notifications'])) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN email_notifications TINYINT(1) NOT NULL DEFAULT 1 AFTER preferred_language');
    }
    if (empty($existing['security_notifications'])) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN security_notifications TINYINT(1) NOT NULL DEFAULT 1 AFTER email_notifications');
    }

    ensureUserActivityLogSchema($pdo);

    ensurePrimaryAdminAccount($pdo);
    ensureContagemTrackingSchema($pdo);

    // Bootstrap: if there is no approved admin yet, promote the oldest user.
    $adminCount = (int)($pdo->query("SELECT COUNT(*) AS c FROM usuarios WHERE role = 'admin' AND aprovado = 1")->fetch()['c'] ?? 0);
    if ($adminCount === 0) {
        $firstUser = $pdo->query('SELECT id FROM usuarios ORDER BY id ASC LIMIT 1')->fetch();
        if (!empty($firstUser['id'])) {
            $stmt = $pdo->prepare("UPDATE usuarios SET role = 'admin', aprovado = 1, aprovado_em = NOW() WHERE id = ?");
            $stmt->execute([(int)$firstUser['id']]);
        }
    }

    $checked = true;
}

function ensureUserActivityLogSchema(PDO $pdo): void {
    static $checked = false;
    if ($checked) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS usuario_atividades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        acao VARCHAR(60) NOT NULL,
        titulo VARCHAR(160) NOT NULL,
        detalhes TEXT NULL,
        ip_address VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_usuario_atividades_usuario_id (usuario_id),
        INDEX idx_usuario_atividades_created_at (created_at),
        CONSTRAINT fk_usuario_atividades_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $checked = true;
}

function logUserActivity(int $userId, string $action, string $title, string $details = ''): void {
    try {
        ensureUserAuthSchema();
        $pdo = getPDO();
        ensureUserActivityLogSchema($pdo);

        $stmt = $pdo->prepare('INSERT INTO usuario_atividades (usuario_id, acao, titulo, detalhes, ip_address) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $userId,
            $action,
            $title,
            $details !== '' ? $details : null,
            substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
        ]);
    } catch (Throwable $e) {
        // Registro de atividade é opcional.
    }
}

function ensureContagemTrackingSchema(PDO $pdo): void {
    static $checked = false;
    if ($checked) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS insumos_jnj (
        id INT AUTO_INCREMENT PRIMARY KEY,
        data_contagem DATE NULL,
        contagem_por_id INT NULL,
        contagem_por_nome VARCHAR(150) NULL,
        contagem_em DATETIME NULL,
        unidade VARCHAR(30) DEFAULT 'UN',
        nome VARCHAR(150) NOT NULL,
        posicao VARCHAR(20) NOT NULL,
        lote VARCHAR(100) NULL,
        codigo_barra VARCHAR(100) NULL,
        quantidade INT NOT NULL DEFAULT 0,
        data_entrada DATE NOT NULL,
        validade DATE NOT NULL,
        observacoes TEXT,
        INDEX idx_validade (validade),
        INDEX idx_posicao (posicao)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $cols = $pdo->query('SHOW COLUMNS FROM insumos_jnj')->fetchAll();
    $existing = [];
    foreach ($cols as $col) {
        $existing[$col['Field']] = true;
    }

    if (empty($existing['data_contagem'])) {
        $pdo->exec('ALTER TABLE insumos_jnj ADD COLUMN data_contagem DATE NULL AFTER id');
    }
    if (empty($existing['contagem_por_id'])) {
        $pdo->exec('ALTER TABLE insumos_jnj ADD COLUMN contagem_por_id INT NULL AFTER data_contagem');
    }
    if (empty($existing['contagem_por_nome'])) {
        $pdo->exec('ALTER TABLE insumos_jnj ADD COLUMN contagem_por_nome VARCHAR(150) NULL AFTER contagem_por_id');
    }
    if (empty($existing['contagem_em'])) {
        $pdo->exec('ALTER TABLE insumos_jnj ADD COLUMN contagem_em DATETIME NULL AFTER contagem_por_nome');
    }
    if (empty($existing['unidade'])) {
        $pdo->exec("ALTER TABLE insumos_jnj ADD COLUMN unidade VARCHAR(30) DEFAULT 'UN' AFTER contagem_em");
    }
    if (empty($existing['lote'])) {
        $pdo->exec('ALTER TABLE insumos_jnj ADD COLUMN lote VARCHAR(100) NULL AFTER posicao');
    }
    if (empty($existing['codigo_barra'])) {
        $pdo->exec('ALTER TABLE insumos_jnj ADD COLUMN codigo_barra VARCHAR(100) NULL AFTER lote');
    }

    $checked = true;
}

function ensureInsumoRequestsSchema(PDO $pdo): void {
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS insumo_requests (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $cols = $pdo->query('SHOW COLUMNS FROM insumo_requests')->fetchAll();
        $existing = [];
        foreach ($cols as $col) {
            $existing[$col['Field']] = true;
        }

        $addColumnIfMissing = static function (string $column, string $sql) use (&$existing, $pdo): void {
            if (empty($existing[$column])) {
                $pdo->exec($sql);
                $existing[$column] = true;
            }
        };

        $addColumnIfMissing('setor', 'ALTER TABLE insumo_requests ADD COLUMN setor VARCHAR(120) NULL AFTER user_role');
        $addColumnIfMissing('batch_id', 'ALTER TABLE insumo_requests ADD COLUMN batch_id VARCHAR(64) NULL AFTER user_role');
        $addColumnIfMissing('data_solicitada_entrega', 'ALTER TABLE insumo_requests ADD COLUMN data_solicitada_entrega DATE NULL AFTER setor');
        $addColumnIfMissing('status', "ALTER TABLE insumo_requests ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER motivo_usuario");
        $addColumnIfMissing('admin_note', 'ALTER TABLE insumo_requests ADD COLUMN admin_note TEXT NULL AFTER status');
        $addColumnIfMissing('requested_at', 'ALTER TABLE insumo_requests ADD COLUMN requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER admin_note');
        $addColumnIfMissing('processed_at', 'ALTER TABLE insumo_requests ADD COLUMN processed_at DATETIME NULL AFTER requested_at');
        $addColumnIfMissing('processed_by', 'ALTER TABLE insumo_requests ADD COLUMN processed_by INT NULL AFTER processed_at');
        $addColumnIfMissing('quantidade_entregue', 'ALTER TABLE insumo_requests ADD COLUMN quantidade_entregue DECIMAL(10,2) NULL AFTER unidade');
        $addColumnIfMissing('lote', 'ALTER TABLE insumo_requests ADD COLUMN lote VARCHAR(100) NULL AFTER quantidade_entregue');
        $addColumnIfMissing('fabricacao', 'ALTER TABLE insumo_requests ADD COLUMN fabricacao DATE NULL AFTER lote');
        $addColumnIfMissing('validade', 'ALTER TABLE insumo_requests ADD COLUMN validade DATE NULL AFTER fabricacao');
    } catch (Throwable $e) {
        // Em hospedagem sem permissão de ALTER TABLE, seguimos com o banco existente.
        // A migração deve ser aplicada manualmente quando faltar alguma coluna.
    }
}

function formatInsumoRequestDate(?string $value): ?string {
    if ($value === null) {
        return null;
    }
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    return $value;
}

function ensurePrimaryAdminAccount(PDO $pdo): void {
    static $seeded = false;
    if ($seeded) {
        return;
    }

    if (isProductionEnvironment()) {
        return;
    }

    $primaryAdminName = 'WEDER MESSIAS DA SILVA PEREIRA';
    $primaryAdminEmail = 'weder.messias@hotmail.com';
    $primaryAdminHash = '$2y$10$FtDWxiRNq9fM9VwflXBoi.US7TU4m/HZUvgKX7x5amq2fZg11nEKq';

    $stmt = $pdo->prepare(
        "INSERT INTO usuarios (nome, email, senha_hash, role, aprovado, aprovado_em)
         VALUES (?, ?, ?, 'admin', 1, NOW())
         ON DUPLICATE KEY UPDATE
           nome = VALUES(nome),
           senha_hash = VALUES(senha_hash),
           role = 'admin',
           aprovado = 1,
           aprovado_em = NOW()"
    );
    $stmt->execute([$primaryAdminName, $primaryAdminEmail, $primaryAdminHash]);

    $seeded = true;
}

function setLastAuthError(string $message): void {
    $_SESSION['last_auth_error'] = $message;
}

function getLastAuthError(): ?string {
    if (!empty($_SESSION['last_auth_error'])) {
        $message = $_SESSION['last_auth_error'];
        unset($_SESSION['last_auth_error']);
        return $message;
    }
    return null;
}

function rememberMeCookieName(): string {
    return 'remember_me_token';
}

function rememberMeCookiePath(): string {
    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '/');
    $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    return $dir === '' ? '/' : $dir . '/';
}

function rememberMeCookieSecure(): bool {
    return !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
}

function setRememberMeCookie(string $token, int $expiresAt): void {
    setcookie(rememberMeCookieName(), $token, [
        'expires' => $expiresAt,
        'path' => rememberMeCookiePath(),
        'secure' => rememberMeCookieSecure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clearRememberMeCookie(): void {
    setcookie(rememberMeCookieName(), '', [
        'expires' => time() - 3600,
        'path' => rememberMeCookiePath(),
        'secure' => rememberMeCookieSecure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function createRememberMeToken(int $userId): void {
    ensureUserAuthSchema();
    $pdo = getPDO();

    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $tokenHash = hash('sha256', $token);
    $expiresAt = time() + (60 * 60 * 24 * 30);

    $stmt = $pdo->prepare('UPDATE usuarios SET remember_token_hash = ?, remember_token_expires_at = FROM_UNIXTIME(?) WHERE id = ?');
    $stmt->execute([$tokenHash, $expiresAt, $userId]);

    setRememberMeCookie($token, $expiresAt);
}

function clearRememberMeToken(?int $userId = null): void {
    try {
        ensureUserAuthSchema();
        if ($userId !== null) {
            $pdo = getPDO();
            $stmt = $pdo->prepare('UPDATE usuarios SET remember_token_hash = NULL, remember_token_expires_at = NULL WHERE id = ?');
            $stmt->execute([$userId]);
        }
    } catch (Throwable $e) {
        // ignore
    }
    clearRememberMeCookie();
}

function authenticateRememberMe(): ?array {
    $token = (string)($_COOKIE[rememberMeCookieName()] ?? '');
    if ($token === '') {
        return null;
    }

    try {
        ensureUserAuthSchema();
        $pdo = getPDO();
        $tokenHash = hash('sha256', $token);
        $stmt = $pdo->prepare('SELECT id,nome,email,role,aprovado,must_change_password FROM usuarios WHERE remember_token_hash = ? AND remember_token_expires_at IS NOT NULL AND remember_token_expires_at > NOW() LIMIT 1');
        $stmt->execute([$tokenHash]);
        $user = $stmt->fetch();
        if (!$user || (int)($user['aprovado'] ?? 0) !== 1) {
            clearRememberMeCookie();
            return null;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['usuario_email'] = $user['email'];
        $_SESSION['usuario_nome'] = $user['nome'];
        $_SESSION['usuario_role'] = $user['role'] ?? 'user';
        $_SESSION['usuario_must_change_password'] = (int)($user['must_change_password'] ?? 0);

        return currentUser();
    } catch (Throwable $e) {
        return null;
    }
}

function login(string $email, string $senha, bool $rememberMe = false): bool {
    ensureUserAuthSchema();
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id,nome,email,senha_hash,role,aprovado,must_change_password,temp_password_expires_at,last_login_at,last_login_ip FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        $hash = (string)($user['senha_hash'] ?? '');
        if ($hash !== '' && password_verify($senha, $hash)) {
            if ((int)($user['aprovado'] ?? 0) !== 1) {
                setLastAuthError('Sua conta ainda está pendente de aprovação do administrador.');
                return false;
            }
            $mustChangePassword = (int)($user['must_change_password'] ?? 0) === 1;
            $tempExpiresAt = (string)($user['temp_password_expires_at'] ?? '');
            if ($mustChangePassword && $tempExpiresAt !== '' && strtotime($tempExpiresAt) !== false && strtotime($tempExpiresAt) < time()) {
                setLastAuthError('Sua senha temporária expirou. Solicite uma nova redefinição ao administrador.');
                return false;
            }
            if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); }
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['usuario_email'] = $user['email'];
            $_SESSION['usuario_nome'] = $user['nome'];
            $_SESSION['usuario_role'] = $user['role'] ?? 'user';
            $_SESSION['usuario_must_change_password'] = $mustChangePassword ? 1 : 0;
            unset($_SESSION['last_auth_error']);

            if ($rememberMe) {
                createRememberMeToken((int)$user['id']);
            } else {
                clearRememberMeToken((int)$user['id']);
            }

            try {
                $stmt = $pdo->prepare('UPDATE usuarios SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?');
                $stmt->execute([
                    substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
                    (int)$user['id'],
                ]);
                logUserActivity((int)$user['id'], 'login', 'Novo login', 'Acesso ao sistema com sucesso.');
            } catch (Throwable $e) {
                // Não bloquear o login se o log falhar.
            }

            return true;
        }
    }

    setLastAuthError('E-mail ou senha inválidos.');

    return false;
}

function logout(): void {
    try {
        if (!empty($_SESSION['usuario_id'])) {
            clearRememberMeToken((int)$_SESSION['usuario_id']);
        } else {
            clearRememberMeCookie();
        }
    } catch (Throwable $e) {
        clearRememberMeCookie();
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function currentUser(): ?array {
    ensureUserAuthSchema();
    if (!empty($_SESSION['usuario_id'])) {
        // Fetch fresh data from DB to include avatar and updated info
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare('SELECT id,nome,email,avatar,role,aprovado,must_change_password,temp_password_expires_at,last_login_at,last_login_ip,criado_em,preferred_theme,preferred_language,email_notifications,security_notifications FROM usuarios WHERE id = ? LIMIT 1');
            $stmt->execute([$_SESSION['usuario_id']]);
            $user = $stmt->fetch();
            if ($user) {
                $_SESSION['usuario_must_change_password'] = (int)($user['must_change_password'] ?? 0);
                return $user;
            }
        } catch (Exception $e) {
            // fallback to session data
            return [
                'id' => $_SESSION['usuario_id'],
                'email' => $_SESSION['usuario_email'],
                'nome' => $_SESSION['usuario_nome'],
                'role' => $_SESSION['usuario_role'] ?? 'user',
                'must_change_password' => (int)($_SESSION['usuario_must_change_password'] ?? 0),
            ];
        }
    }

    $remembered = authenticateRememberMe();
    if ($remembered) {
        return $remembered;
    }

    return null;
}

function mustChangePassword(?array $user = null): bool {
    $u = $user ?? currentUser();
    return !empty($u) && (int)($u['must_change_password'] ?? 0) === 1;
}

function requireLogin(): void {
    $user = currentUser();
    if (!$user) {
        flash('error', 'Faça login para acessar.');
        header('Location: login.php');
        exit;
    }

    if (mustChangePassword($user)) {
        $currentScript = basename((string)($_SERVER['PHP_SELF'] ?? ''));
        $allowed = ['perfil.php', 'logout.php', 'login.php'];
        if (!in_array($currentScript, $allowed, true)) {
            flash('error', 'Troque sua senha temporária para continuar usando o sistema.');
            header('Location: perfil.php?force_password_change=1');
            exit;
        }
    }
}

function isAdmin(): bool {
    $user = currentUser();
    return !empty($user) && (($user['role'] ?? 'user') === 'admin');
}

function getPrimaryAdminId(): int {
    ensureUserAuthSchema();
    $pdo = getPDO();

    // Prefer explicit primary admin account when available.
    $primaryAdminEmail = 'weder.messias@hotmail.com';
    $preferred = $pdo->prepare("SELECT id FROM usuarios WHERE role = 'admin' AND aprovado = 1 AND LOWER(email) = LOWER(?) LIMIT 1");
    $preferred->execute([$primaryAdminEmail]);
    $preferredRow = $preferred->fetch();
    if (!empty($preferredRow['id'])) {
        return (int)$preferredRow['id'];
    }

    $row = $pdo->query("SELECT id FROM usuarios WHERE role = 'admin' AND aprovado = 1 ORDER BY id ASC LIMIT 1")->fetch();
    return (int)($row['id'] ?? 0);
}

function requireAdmin(): void {
    requireLogin();
    ensureUserAuthSchema();

    // Recovery path: avoid admin lockout when there is no approved admin account.
    $pdo = getPDO();
    $approvedAdminCount = (int)($pdo->query("SELECT COUNT(*) AS c FROM usuarios WHERE role = 'admin' AND aprovado = 1")->fetch()['c'] ?? 0);
    if ($approvedAdminCount === 0 && !empty($_SESSION['usuario_id'])) {
        $promote = $pdo->prepare("UPDATE usuarios SET role = 'admin', aprovado = 1, aprovado_em = NOW() WHERE id = ?");
        $promote->execute([(int)$_SESSION['usuario_id']]);
        $_SESSION['usuario_role'] = 'admin';
    }

    if (!isAdmin()) {
        flash('error', 'Acesso restrito ao administrador.');
        header('Location: index.php');
        exit;
    }
}
?>