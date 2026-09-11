<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function usuarioLogado(): bool
{
    return isset(
        $_SESSION['user_id'],
        $_SESSION['empresa_id'],
        $_SESSION['contexto']
    );
}

function exigirLogin(): void
{
    if (!usuarioLogado()) {
        header('Location: login.php');
        exit;
    }
}

function exigirAdministrador(): void
{
    exigirLogin();

    if ($_SESSION['contexto'] !== 'administrador') {
        http_response_code(403);

        exit('Acesso negado.');
    }
}

function encerrarSessao(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}