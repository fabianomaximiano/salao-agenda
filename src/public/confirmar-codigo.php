<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    empty($_SESSION['csrf_confirmacao_codigo'])
    || !is_string($_SESSION['csrf_confirmacao_codigo'])
) {
    $_SESSION['csrf_confirmacao_codigo'] =
        bin2hex(random_bytes(32));
}

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/services/CodigoVerificacaoService.php';
require_once dirname(__DIR__) . '/services/TokenAtivacaoService.php';
require_once dirname(__DIR__) . '/services/EmailService.php';

$usuarioId = (int) ($_SESSION['cadastro_usuario_id'] ?? 0);

if ($usuarioId <= 0) {
    header('Location: cadastro-empresa.php');
    exit;
}

$pdo = getDB();

$erro = (string) (
    $_SESSION['cadastro_verificacao_erro']
    ?? ''
);

$sucesso = '';
$emailMascarado = '';

unset(
    $_SESSION['cadastro_verificacao_erro']
);

$stmtUsuario = $pdo->prepare(
    '
    SELECT
        u.email,
        a.nome_completo,
        e.nome_fantasia
    FROM usuarios u
    INNER JOIN administradores a
        ON a.usuario_id = u.id
    INNER JOIN empresas e
        ON e.id = a.empresa_id
    WHERE u.id = :usuario_id
    LIMIT 1
    '
);

$stmtUsuario->execute([
    ':usuario_id' => $usuarioId,
]);

$usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    unset(
        $_SESSION['cadastro_usuario_id'],
        $_SESSION['cadastro_email']
    );

    header('Location: cadastro-empresa.php');
    exit;
}

$emailDestinatario = (string) $usuario['email'];

function mascararEmail(string $email): string
{
    [$local, $dominio] = array_pad(
        explode('@', $email, 2),
        2,
        ''
    );

    if ($dominio === '') {
        return $email;
    }

    $inicio = mb_substr($local, 0, 2);

    return $inicio . '***@' . $dominio;
}

$emailMascarado = mascararEmail($emailDestinatario);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfRecebido = (string) ($_POST['csrf_token'] ?? '');
    $csrfSessao = (string) (
        $_SESSION['csrf_confirmacao_codigo']
        ?? ''
    );

    if (
        $csrfRecebido === ''
        || $csrfSessao === ''
        || !hash_equals($csrfSessao, $csrfRecebido)
    ) {
        $erro =
            'Sua sessão expirou ou a solicitação é inválida.';
    } else {
        $codigo = trim((string) ($_POST['codigo'] ?? ''));

    try {
        $codigoService = new CodigoVerificacaoService();

        $codigoService->validar(
            $pdo,
            $usuarioId,
            $codigo
        );

        $tokenService = new TokenAtivacaoService();

        $token = $tokenService->gerar(
            $pdo,
            $usuarioId
        );

        /*
         * Em desenvolvimento usamos localhost como fallback.
         *
         * Em produção APP_URL deverá estar configurada
         * explicitamente no ambiente.
         */
        $appUrl = rtrim(
            (string) (
                getenv('APP_URL')
                ?: 'http://localhost:8096'
            ),
            '/'
        );

        $linkAtivacao =
            $appUrl
            . '/definir-senha.php?token='
            . urlencode($token);

        $nome = (string) $usuario['nome_completo'];
        $empresa = (string) $usuario['nome_fantasia'];

        /*
         * O template cadastro-confirmacao.php trabalha com o array $dados
         * e cria variáveis internas no mesmo escopo do require.
         *
         * Por isso, o endereço real do destinatário permanece separado em
         * $emailDestinatario, evitando que o template sobrescreva o valor
         * usado pelo EmailService.
         */
        $dados = [
            'nome' => $nome,
            'empresa' => $empresa,
            'email' => $emailDestinatario,
            'link_ativacao' => $linkAtivacao,
        ];

        ob_start();

        require dirname(__DIR__)
            . '/templates/emails/cadastro-confirmacao.php';

        $html = (string) ob_get_clean();

        $emailService = new EmailService();

        $emailService->enviar(
            $emailDestinatario,
            $nome,
            'Crie sua senha - Salão Agenda',
            $html,
            "Olá, {$nome}.\n\n"
            . "Seu e-mail foi confirmado.\n\n"
            . "Crie sua senha através do link:\n"
            . $linkAtivacao
            . "\n\nEste link é temporário e de uso único."
        );

        /*
         * O código já foi confirmado.
         * Não precisamos mais manter o usuário pendente
         * nessa etapa da sessão.
         */
        unset(
            $_SESSION['cadastro_usuario_id'],
            $_SESSION['cadastro_email']
        );

        $sucesso =
            'E-mail confirmado. Enviamos um link para você criar sua senha.';
    } catch (RuntimeException $e) {
        $erro = $e->getMessage();
    } catch (Throwable $e) {
        error_log(
            'Erro ao confirmar código: '
            . $e->getMessage()
        );

        $erro =
            'Não foi possível concluir a verificação. Tente novamente.';
    }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <title>Confirmar e-mail</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #f5f5f5;
            font-family: Arial, sans-serif;
            color: #222;
        }

        .card {
            width: 100%;
            max-width: 440px;
            padding: 32px;
            background: #fff;
            border-radius: 10px;
        }

        h1 {
            margin-top: 0;
            margin-bottom: 12px;
            font-size: 26px;
        }

        p {
            line-height: 1.5;
        }

        .email {
            font-weight: 700;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
        }

        input {
            width: 100%;
            padding: 14px;
            border: 1px solid #bbb;
            border-radius: 6px;
            font-size: 22px;
            text-align: center;
            letter-spacing: 6px;
        }

        button {
            width: 100%;
            margin-top: 16px;
            padding: 14px;
            border: 0;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
        }

        .mensagem {
            margin-bottom: 20px;
            padding: 12px;
            border-radius: 6px;
        }

        .erro {
            background: #fdecec;
        }

        .sucesso {
            background: #eaf7ec;
        }

        .reenvio {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
        }

        .reenvio button {
            width: auto;
            margin: 0;
            padding: 0;
            background: transparent;
            text-decoration: underline;
        }

        .reenvio button:disabled {
            cursor: default;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="card">

    <?php if ($sucesso !== ''): ?>

        <div class="mensagem sucesso">
            <?= htmlspecialchars(
                $sucesso,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>

        <p>
            Confira sua caixa de entrada para continuar.
        </p>

    <?php else: ?>

        <h1>Confirme seu e-mail</h1>

        <p>
            Enviamos um código de 6 dígitos para
            <span class="email">
                <?= htmlspecialchars(
                    $emailMascarado,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </span>.
        </p>

        <p>
            O código é válido por 10 minutos.
        </p>

        <?php if ($erro !== ''): ?>
            <div class="mensagem erro">
                <?= htmlspecialchars(
                    $erro,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION['csrf_confirmacao_codigo'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <label for="codigo">
                Código de verificação
            </label>

            <input
                type="text"
                id="codigo"
                name="codigo"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                pattern="[0-9]{6}"
                required
                autofocus
            >

            <button type="submit">
                Confirmar código
            </button>

        </form>

        <div class="reenvio">
            <p>
                Não recebeu o código?
            </p>

            <button
                type="button"
                id="btn-reenviar"
                disabled
            >
                Reenviar código em
                <span id="contador">60</span>s
            </button>

            <p id="mensagem-reenvio"></p>
        </div>

    <?php endif; ?>

</div>

<?php if ($sucesso === ''): ?>
<script>
(() => {
    const botao = document.getElementById('btn-reenviar');
    const contador = document.getElementById('contador');
    const mensagem = document.getElementById('mensagem-reenvio');

    let segundos = 60;

    const intervalo = window.setInterval(() => {
        segundos--;

        if (contador) {
            contador.textContent = String(segundos);
        }

        if (segundos <= 0) {
            window.clearInterval(intervalo);

            botao.disabled = false;
            botao.textContent = 'Reenviar código';
        }
    }, 1000);

    botao.addEventListener('click', async () => {
        if (botao.disabled) {
            return;
        }

        botao.disabled = true;
        mensagem.textContent = 'Enviando...';

        try {
            const resposta = await fetch(
                'reenviar-codigo.php',
                {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': <?= json_encode(
                            $_SESSION['csrf_confirmacao_codigo'],
                            JSON_UNESCAPED_UNICODE
                        ) ?>
                    }
                }
            );

            const dados = await resposta.json();

            mensagem.textContent = dados.mensagem;

            if (!resposta.ok || !dados.sucesso) {
                botao.disabled = false;
                return;
            }

            segundos = 60;

            botao.textContent = 'Reenviar código em 60s';

            window.setTimeout(() => {
                botao.disabled = false;
                botao.textContent = 'Reenviar código';
            }, 60000);
        } catch (erro) {
            mensagem.textContent =
                'Não foi possível reenviar o código.';

            botao.disabled = false;
        }
    });
})();
</script>
<?php endif; ?>

</body>
</html>