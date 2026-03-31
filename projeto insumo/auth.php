<?php
require_once __DIR__ . '/db.php';

function ensureUserAuthSchema(): void {
    static $checked = false;
    if ($checked) {
        return;
    }

    $pdo = getPDO();
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
    if (empty($existing['must_change_password'])) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER aprovado_por');
        $pdo->exec('ALTER TABLE usuarios ADD INDEX idx_usuarios_must_change_password (must_change_password)');
    }
    if (empty($existing['temp_password_expires_at'])) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN temp_password_expires_at DATETIME NULL AFTER must_change_password');
    }

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

function login(string $email, string $senha): bool {
    ensureUserAuthSchema();
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id,nome,email,senha_hash,role,aprovado,must_change_password,temp_password_expires_at FROM usuarios WHERE email = ? LIMIT 1');
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
            return true;
        }
    }

    setLastAuthError('E-mail ou senha inválidos.');

    return false;
}

function logout(): void {
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
            $stmt = $pdo->prepare('SELECT id,nome,email,avatar,role,aprovado,must_change_password,temp_password_expires_at FROM usuarios WHERE id = ? LIMIT 1');
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