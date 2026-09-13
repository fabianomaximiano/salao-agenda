<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    empty($_SESSION['csrf_confirmacao_codigo'])
    || !is_string($_SESSION['csrf_confirmacao_codigo'])
) {
    $_SESSION['csrf_confirmacao_codigo'] = bin2hex(random_bytes(32));
}

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/services/CodigoVerificacaoService.php';
require_once dirname(__DIR__) . '/services/TokenAtivacaoService.php';
require_once dirname(__DIR__) . '/services/EmailService.php';

$pdo = getDB();
$codigoService = new CodigoVerificacaoService();
$tokenService = new TokenAtivacaoService();

$erro = '';
$sucesso = '';
$emailMascarado = '';
$modo = 'administrador';
$tokenReferencia = strtolower(trim((string) ($_POST['token'] ?? $_GET['token'] ?? '')));
$usuarioId = (int) ($_SESSION['cadastro_usuario_id'] ?? 0);
$usuario = false;

function mascararEmail(string $email): string
{
    [$local, $dominio] = array_pad(explode('@', $email, 2), 2, '');

    if ($dominio === '') {
        return $email;
    }

    return mb_substr($local, 0, 2) . '***@' . $dominio;
}

function buscarAdministradorPendente(PDO $pdo, int $usuarioId): array|false
{
    $stmt = $pdo->prepare(
        'SELECT
            u.id AS usuario_id,
            u.email,
            u.ativo AS usuario_ativo,
            a.id AS administrador_id,
            a.nome_completo,
            a.ativo AS contexto_ativo,
            e.id AS empresa_id,
            e.nome_fantasia,
            e.ativo AS empresa_ativa
         FROM usuarios u
         INNER JOIN administradores a
           ON a.usuario_id = u.id
         INNER JOIN empresas e
           ON e.id = a.empresa_id
         WHERE u.id = :usuario_id
         LIMIT 1'
    );
    $stmt->execute([':usuario_id' => $usuarioId]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function buscarProfissionalPendente(PDO $pdo, int $usuarioId): array|false
{
    $stmt = $pdo->prepare(
        'SELECT
            u.id AS usuario_id,
            u.email,
            u.ativo AS usuario_ativo,
            pr.id AS profissional_id,
            pr.ativo AS contexto_ativo,
            p.nome_completo,
            p.ativo AS pessoa_ativa,
            e.id AS empresa_id,
            e.nome_fantasia,
            e.ativo AS empresa_ativa
         FROM usuarios u
         INNER JOIN profissionais pr
           ON pr.usuario_id = u.id
         INNER JOIN pessoas p
           ON p.id = pr.pessoa_id
          AND p.empresa_id = pr.empresa_id
         INNER JOIN empresas e
           ON e.id = pr.empresa_id
         WHERE u.id = :usuario_id
         LIMIT 1'
    );
    $stmt->execute([':usuario_id' => $usuarioId]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}


function buscarColaboradorPendente(PDO $pdo, int $usuarioId): array|false
{
    $stmt = $pdo->prepare(
        'SELECT
            u.id AS usuario_id,
            u.email,
            u.ativo AS usuario_ativo,
            c.id AS colaborador_id,
            c.ativo AS contexto_ativo,
            p.nome_completo,
            p.ativo AS pessoa_ativa,
            e.id AS empresa_id,
            e.nome_fantasia,
            e.ativo AS empresa_ativa
         FROM usuarios u
         INNER JOIN colaboradores c ON c.usuario_id = u.id
         INNER JOIN pessoas p ON p.id = c.pessoa_id AND p.empresa_id = c.empresa_id
         INNER JOIN empresas e ON e.id = c.empresa_id
         WHERE u.id = :usuario_id
         LIMIT 1'
    );
    $stmt->execute([':usuario_id' => $usuarioId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($tokenReferencia !== '') {
    $modo = 'profissional';

    try {
        $usuarioId = $tokenService->validar($pdo, $tokenReferencia);
        $usuario = buscarProfissionalPendente($pdo, $usuarioId);

        if (!$usuario) {
            $usuario = buscarColaboradorPendente($pdo, $usuarioId);
            $modo = 'colaborador';
        }

        if (!$usuario) {
            throw new RuntimeException('Convite de acesso inválido.');
        }

        if (
            (int) $usuario['usuario_ativo'] === 1
            || (int) $usuario['contexto_ativo'] !== 1
            || (int) $usuario['pessoa_ativa'] !== 1
            || (int) $usuario['empresa_ativa'] !== 1
        ) {
            throw new RuntimeException('Este convite não está disponível para ativação.');
        }
    } catch (Throwable $e) {
        $erro = 'O link de confirmação é inválido ou expirou.';
        $usuario = false;
        $usuarioId = 0;
    }
} elseif ($usuarioId > 0) {
    $usuario = buscarAdministradorPendente($pdo, $usuarioId);

    if (!$usuario) {
        unset($_SESSION['cadastro_usuario_id'], $_SESSION['cadastro_email']);
        header('Location: cadastro-empresa.php');
        exit;
    }
} else {
    header('Location: login.php');
    exit;
}

if ($usuario) {
    $emailDestinatario = (string) $usuario['email'];
    $emailMascarado = mascararEmail($emailDestinatario);
    $segundosRestantesCooldown = $codigoService->segundosRestantesCooldown($pdo, $usuarioId);
} else {
    $emailDestinatario = '';
    $segundosRestantesCooldown = 0;
}

$erroSessao = (string) ($_SESSION['cadastro_verificacao_erro'] ?? '');
unset($_SESSION['cadastro_verificacao_erro']);

if ($erro === '' && $erroSessao !== '') {
    $erro = $erroSessao;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuario) {
    $csrfRecebido = (string) ($_POST['csrf_token'] ?? '');
    $csrfSessao = (string) ($_SESSION['csrf_confirmacao_codigo'] ?? '');

    if (
        $csrfRecebido === ''
        || $csrfSessao === ''
        || !hash_equals($csrfSessao, $csrfRecebido)
    ) {
        $erro = 'Sua sessão expirou ou a solicitação é inválida.';
    } else {
        $codigo = trim((string) ($_POST['codigo'] ?? ''));

        try {
            $codigoService->validar($pdo, $usuarioId, $codigo);

            $tokenSenha = $tokenService->gerar($pdo, $usuarioId);
            $appUrl = rtrim((string) (getenv('APP_URL') ?: 'http://localhost:8096'), '/');
            $linkAtivacao = $appUrl . '/definir-senha.php?token=' . urlencode($tokenSenha);

            $nome = (string) $usuario['nome_completo'];
            $empresa = (string) $usuario['nome_fantasia'];

            if ($modo === 'profissional' || $modo === 'colaborador') {
                ob_start();
                require dirname(__DIR__) . ($modo === 'profissional'
                    ? '/templates/emails/acesso-profissional-ativacao.php'
                    : '/templates/emails/acesso-colaborador-ativacao.php');
                $html = (string) ob_get_clean();
                $assunto = $modo === 'profissional' ? 'Crie sua senha profissional - Salão Agenda' : 'Crie sua senha de colaborador - Salão Agenda';
                $texto = "Olá, {$nome}.\n\nSeu e-mail foi confirmado.\n\nCrie sua senha através do link:\n{$linkAtivacao}\n\nEste link é temporário e de uso único.";
            } else {
                $dados = [
                    'nome' => $nome,
                    'empresa' => $empresa,
                    'email' => $emailDestinatario,
                    'link_ativacao' => $linkAtivacao,
                ];

                ob_start();
                require dirname(__DIR__) . '/templates/emails/cadastro-confirmacao.php';
                $html = (string) ob_get_clean();
                $assunto = 'Crie sua senha - Salão Agenda';
                $texto = "Olá, {$nome}.\n\nSeu e-mail foi confirmado.\n\nCrie sua senha através do link:\n{$linkAtivacao}\n\nEste link é temporário e de uso único.";
            }

            $emailService = new EmailService();
            $emailService->enviar(
                $emailDestinatario,
                $nome,
                $assunto,
                $html,
                $texto
            );

            if ($modo === 'administrador') {
                unset($_SESSION['cadastro_usuario_id'], $_SESSION['cadastro_email']);
            }

            $sucesso = 'E-mail confirmado. Enviamos um link para você criar sua senha.';
        } catch (RuntimeException $e) {
            $erro = $e->getMessage();
        } catch (Throwable $e) {
            error_log('Erro ao confirmar código: ' . $e->getMessage());
            $erro = 'Não foi possível concluir a verificação. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirmar e-mail</title>
    <style>
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; background:#f5f5f5; font-family:Arial,sans-serif; color:#222; }
        .card { width:100%; max-width:440px; padding:32px; background:#fff; border-radius:10px; }
        h1 { margin-top:0; margin-bottom:12px; font-size:26px; }
        p { line-height:1.5; }
        .email { font-weight:700; }
        label { display:block; margin-bottom:8px; font-weight:700; }
        input[type="text"] { width:100%; padding:14px; border:1px solid #bbb; border-radius:6px; font-size:22px; text-align:center; letter-spacing:6px; }
        button { width:100%; margin-top:16px; padding:14px; border:0; border-radius:6px; cursor:pointer; font-size:16px; }
        .mensagem { margin-bottom:20px; padding:12px; border-radius:6px; }
        .erro { background:#fdecec; }
        .sucesso { background:#eaf7ec; }
        .reenvio { margin-top:20px; text-align:center; font-size:14px; }
        .reenvio button { width:auto; margin:0; padding:0; background:transparent; text-decoration:underline; }
        .reenvio button:disabled { cursor:default; text-decoration:none; }
        .link-login { display:inline-block; margin-top:16px; }
    </style>
</head>
<body>
<div class="card">
    <?php if ($sucesso !== ''): ?>
        <div class="mensagem sucesso"><?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?></div>
        <p>Confira sua caixa de entrada para continuar.</p>
    <?php elseif (!$usuario): ?>
        <h1>Link inválido</h1>
        <div class="mensagem erro"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
        <a class="link-login" href="login.php">Ir para o login</a>
    <?php else: ?>
        <h1>Confirme seu e-mail</h1>
        <p>
            Enviamos um código de 6 dígitos para
            <span class="email"><?= htmlspecialchars($emailMascarado, ENT_QUOTES, 'UTF-8') ?></span>.
        </p>
        <p>O código é válido por 10 minutos.</p>

        <?php if ($erro !== ''): ?>
            <div class="mensagem erro"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_confirmacao_codigo'], ENT_QUOTES, 'UTF-8') ?>">
            <?php if ($modo === 'profissional' || $modo === 'colaborador'): ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($tokenReferencia, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>

            <label for="codigo">Código de verificação</label>
            <input type="text" id="codigo" name="codigo" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required autofocus>
            <button type="submit">Confirmar código</button>
        </form>

        <div class="reenvio">
            <p>Não recebeu o código?</p>
            <button type="button" id="btn-reenviar" <?= $segundosRestantesCooldown > 0 ? 'disabled' : '' ?>>
                <?php if ($segundosRestantesCooldown > 0): ?>
                    Reenviar código em <span id="contador"><?= $segundosRestantesCooldown ?></span>s
                <?php else: ?>
                    Reenviar código
                <?php endif; ?>
            </button>
            <p id="mensagem-reenvio"></p>
        </div>
    <?php endif; ?>
</div>

<?php if ($sucesso === '' && $usuario): ?>
<script>
(() => {
    const botao = document.getElementById('btn-reenviar');
    const mensagem = document.getElementById('mensagem-reenvio');
    let segundos = <?= json_encode($segundosRestantesCooldown, JSON_UNESCAPED_UNICODE) ?>;
    let intervalo = null;

    function atualizarBotao() {
        if (segundos <= 0) {
            botao.disabled = false;
            botao.textContent = 'Reenviar código';
            return;
        }
        botao.disabled = true;
        botao.textContent = `Reenviar código em ${segundos}s`;
    }

    function iniciarContagem() {
        if (intervalo !== null) {
            window.clearInterval(intervalo);
        }
        atualizarBotao();
        if (segundos <= 0) return;

        intervalo = window.setInterval(() => {
            segundos--;
            atualizarBotao();
            if (segundos <= 0) {
                window.clearInterval(intervalo);
                intervalo = null;
            }
        }, 1000);
    }

    iniciarContagem();

    botao.addEventListener('click', async () => {
        if (botao.disabled) return;

        botao.disabled = true;
        mensagem.textContent = 'Enviando...';

        try {
            const headers = {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': <?= json_encode($_SESSION['csrf_confirmacao_codigo'], JSON_UNESCAPED_UNICODE) ?>
            };

            <?php if ($modo === 'profissional' || $modo === 'colaborador'): ?>
            headers['X-Activation-Token'] = <?= json_encode($tokenReferencia, JSON_UNESCAPED_UNICODE) ?>;
            <?php endif; ?>

            const resposta = await fetch('reenviar-codigo.php', {
                method: 'POST',
                headers
            });
            const dados = await resposta.json();
            mensagem.textContent = dados.mensagem;

            if (!resposta.ok || !dados.sucesso) {
                botao.disabled = false;
                return;
            }

            segundos = Number(dados.cooldown_segundos ?? 60);
            iniciarContagem();
        } catch (erro) {
            mensagem.textContent = 'Não foi possível reenviar o código.';
            botao.disabled = false;
        }
    });
})();
</script>
<?php endif; ?>
</body>
</html>
