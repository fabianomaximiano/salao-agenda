<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../services/TokenRecuperacaoSenhaService.php';

if (!isset($_SESSION['csrf_esqueci_senha']) || !is_string($_SESSION['csrf_esqueci_senha'])) {
    $_SESSION['csrf_esqueci_senha'] = bin2hex(random_bytes(32));
}

$erro = '';
$sucesso = '';
$email = '';

function obterIpRecuperacao(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function buscarUsuarioRecuperacao(PDO $pdo, string $email): array|false
{
    $stmt = $pdo->prepare(
        'SELECT
            u.id,
            u.email,
            COALESCE(
                (
                    SELECT a.nome_completo
                    FROM administradores a
                    WHERE a.usuario_id = u.id
                    LIMIT 1
                ),
                (
                    SELECT p.nome_completo
                    FROM profissionais pr
                    INNER JOIN pessoas p
                      ON p.id = pr.pessoa_id
                     AND p.empresa_id = pr.empresa_id
                    WHERE pr.usuario_id = u.id
                    LIMIT 1
                ),
                "Usuário"
            ) AS nome_completo
         FROM usuarios u
         WHERE u.email = :email
           AND u.ativo = 1
           AND u.senha_hash IS NOT NULL
         LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfRecebido = (string) ($_POST['csrf_token'] ?? '');
    $csrfSessao = (string) ($_SESSION['csrf_esqueci_senha'] ?? '');
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));

    if ($csrfRecebido === '' || $csrfSessao === '' || !hash_equals($csrfSessao, $csrfRecebido)) {
        $erro = 'Sua sessão expirou ou a solicitação é inválida.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        $erro = 'Informe um e-mail válido.';
    } else {
        $sucesso = 'Se esse e-mail estiver cadastrado, enviaremos as instruções para redefinir sua senha.';

        try {
            $pdo = getDB();
            $service = new TokenRecuperacaoSenhaService();
            $ipHash = hash('sha256', obterIpRecuperacao());
            $permitido = $service->registrarSolicitacao($pdo, $email, $ipHash);

            if ($permitido) {
                $usuario = buscarUsuarioRecuperacao($pdo, $email);

                if ($usuario) {
                    $token = $service->gerar($pdo, (int) $usuario['id'], $ipHash);

                    try {
                        $appUrl = rtrim((string) (getenv('APP_URL') ?: 'http://localhost:8096'), '/');
                        $link = $appUrl . '/redefinir-senha.php?token=' . urlencode($token);
                        $nome = trim((string) $usuario['nome_completo']);
                        $dados = [
                            'nome' => $nome !== '' ? $nome : 'Usuário',
                            'link' => $link,
                        ];

                        ob_start();
                        require __DIR__ . '/../templates/emails/recuperacao-senha.php';
                        $html = (string) ob_get_clean();

                        $emailService = new EmailService();
                        $emailService->enviar(
                            (string) $usuario['email'],
                            $nome !== '' ? $nome : 'Usuário',
                            'Redefina sua senha - Salão Agenda',
                            $html,
                            "Recebemos uma solicitação para redefinir sua senha.\n\n"
                            . "Use o link abaixo em até 30 minutos:\n{$link}\n\n"
                            . 'Se você não solicitou essa alteração, ignore esta mensagem.'
                        );
                    } catch (Throwable $e) {
                        $service->revogar($pdo, $token);
                        error_log('Erro ao enviar recuperação de senha: ' . $e->getMessage());
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('Erro ao solicitar recuperação de senha: ' . $e->getMessage());
        }

        $_SESSION['csrf_esqueci_senha'] = bin2hex(random_bytes(32));
        $email = '';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Esqueci minha senha | Agenda</title>
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
                        <h1 class="h3 font-weight-bold mt-3 mb-2">Recuperar senha</h1>
                        <p class="text-muted mb-0">Informe seu e-mail corporativo.</p>
                    </div>

                    <?php if ($erro !== ''): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($sucesso !== ''): ?>
                        <div class="alert alert-success" role="status">
                            <?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <a href="login.php" class="btn btn-outline-secondary btn-block">Voltar para o login</a>
                    <?php else: ?>
                        <form method="post" action="esqueci-senha.php" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['csrf_esqueci_senha'], ENT_QUOTES, 'UTF-8') ?>">

                            <div class="form-group">
                                <label for="email">E-mail</label>
                                <input
                                    type="email"
                                    class="form-control"
                                    id="email"
                                    name="email"
                                    maxlength="190"
                                    autocomplete="email"
                                    value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                                    required
                                    autofocus
                                >
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg btn-block mt-4">Enviar instruções</button>
                            <a href="login.php" class="btn btn-link btn-block mt-2">Voltar para o login</a>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
