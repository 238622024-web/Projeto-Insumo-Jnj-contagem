<?php
require_once __DIR__ . '/db.php';

function login(string $email, string $senha): bool {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($senha, $user['senha_hash'])) {
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['usuario_email'] = $user['email'];
        $_SESSION['usuario_nome'] = $user['nome'];
        return true;
    }

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
    if (!empty($_SESSION['usuario_id'])) {
        // Fetch fresh data from DB to include avatar and updated info
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare('SELECT id,nome,email,avatar FROM usuarios WHERE id = ? LIMIT 1');
            $stmt->execute([$_SESSION['usuario_id']]);
            $user = $stmt->fetch();
            if ($user) return $user;
        } catch (Exception $e) {
            // fallback to session data
            return [
                'id' => $_SESSION['usuario_id'],
                'email' => $_SESSION['usuario_email'],
                'nome' => $_SESSION['usuario_nome'],
            ];
        }
    }
    return null;
}

function requireLogin(): void {
    if (!currentUser()) {
        flash('error', 'Faça login para acessar.');
        header('Location: login.php');
        exit;
    }
}
?>