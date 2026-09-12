<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../services/TokenRecuperacaoSenhaService.php';

if (!isset($_SESSION['csrf_redefinir_senha']) || !is_string($_SESSION['csrf_redefinir_senha'])) {
    $_SESSION['csrf_redefinir_senha'] = bin2hex(random_bytes(32));
}

$pdo = getDB();
$service = new TokenRecuperacaoSenhaService();
$erro = '';
$sucesso = '';
$tokenDisponivel = false;
$token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));

function validarSenhaRecuperacao(string $senha): ?string
{
    if (strlen($senha) < 12) {
        return 'A senha deve ter pelo menos 12 caracteres.';
    }

    if (strlen($senha) > 255) {
        return 'A senha deve ter no máximo 255 caracteres.';
    }

    if (!preg_match('/[A-Z]/', $senha)) {
        return 'A senha deve conter pelo menos uma letra maiúscula.';
    }

    if (!preg_match('/[a-z]/', $senha)) {
        return 'A senha deve conter pelo menos uma letra minúscula.';
    }

    if (!preg_match('/[0-9]/', $senha)) {
        return 'A senha deve conter pelo menos um número.';
    }

    return null;
}

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $erro = 'O link de recuperação é inválido.';
} else {
    try {
        $service->validar($pdo, $token);
        $tokenDisponivel = true;
    } catch (RuntimeException $e) {
        $erro = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Erro ao validar token de recuperação: ' . $e->getMessage());
        $erro = 'Não foi possível validar o link de recuperação agora.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenDisponivel) {
    $csrfRecebido = (string) ($_POST['csrf_token'] ?? '');
    $csrfSessao = (string) ($_SESSION['csrf_redefinir_senha'] ?? '');
    $senha = (string) ($_POST['senha'] ?? '');
    $confirmacaoSenha = (string) ($_POST['confirmacao_senha'] ?? '');

    if ($csrfRecebido === '' || $csrfSessao === '' || !hash_equals($csrfSessao, $csrfRecebido)) {
        $erro = 'Sua sessão expirou ou a solicitação é inválida.';
    } else {
        $erroSenha = validarSenhaRecuperacao($senha);

        if ($erroSenha !== null) {
            $erro = $erroSenha;
        } elseif ($senha !== $confirmacaoSenha) {
            $erro = 'A confirmação da senha não confere.';
        } else {
            try {
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

                if (!is_string($senhaHash) || $senhaHash === '') {
                    throw new RuntimeException('Não foi possível proteger a nova senha.');
                }

                $service->redefinirSenha($pdo, $token, $senhaHash);
                $_SESSION['csrf_redefinir_senha'] = bin2hex(random_bytes(32));
                $tokenDisponivel = false;
                $erro = '';
                $sucesso = 'Senha alterada com sucesso. Você já pode entrar com a nova senha.';
            } catch (RuntimeException $e) {
                $tokenDisponivel = false;
                $erro = 'Este link é inválido, expirou ou já foi utilizado.';
            } catch (Throwable $e) {
                error_log('Erro ao redefinir senha: ' . $e->getMessage());
                $erro = 'Não foi possível alterar sua senha agora. Tente novamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Redefinir senha | Agenda</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
<div class="login-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-7 col-lg-5">
                <div class="login-card">
                    <div class="text-center mb-4">
                        <img src="assets/img/logo-placeholder.svg" alt="Agenda" class="login-logo">
                        <h1 class="h3 font-weight-bold mt-3 mb-2">Redefinir senha</h1>
                    </div>

                    <?php if ($sucesso !== ''): ?>
                        <div class="alert alert-success" role="status">
                            <?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <a href="login.php" class="btn btn-primary btn-lg btn-block">Ir para o login</a>
                    <?php elseif (!$tokenDisponivel): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <a href="esqueci-senha.php" class="btn btn-outline-secondary btn-block">Solicitar novo link</a>
                    <?php else: ?>
                        <?php if ($erro !== ''): ?>
                            <div class="alert alert-danger" role="alert">
                                <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>

                        <p class="text-muted">Use pelo menos 12 caracteres, incluindo uma letra maiúscula, uma minúscula e um número.</p>

                        <form method="post" action="redefinir-senha.php" autocomplete="off">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['csrf_redefinir_senha'], ENT_QUOTES, 'UTF-8') ?>">

                            <div class="form-group">
                                <label for="senha">Nova senha</label>
                                <input type="password" class="form-control" id="senha" name="senha" minlength="12" maxlength="255" autocomplete="new-password" required autofocus>
                            </div>

                            <div class="form-group">
                                <label for="confirmacao_senha">Confirme a nova senha</label>
                                <input type="password" class="form-control" id="confirmacao_senha" name="confirmacao_senha" minlength="12" maxlength="255" autocomplete="new-password" required>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg btn-block mt-4">Alterar senha</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
