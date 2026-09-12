<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/services/CodigoVerificacaoService.php';
require_once dirname(__DIR__) . '/services/TokenAtivacaoService.php';
require_once dirname(__DIR__) . '/services/EmailService.php';

function responder(
    bool $sucesso,
    string $mensagem,
    int $status = 200,
    array $dados = []
): never {
    http_response_code($status);
    echo json_encode(
        array_merge(
            ['sucesso' => $sucesso, 'mensagem' => $mensagem],
            $dados
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Método não permitido.', 405);
}

$csrfRecebido = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$csrfSessao = (string) ($_SESSION['csrf_confirmacao_codigo'] ?? '');

if (
    $csrfRecebido === ''
    || $csrfSessao === ''
    || !hash_equals($csrfSessao, $csrfRecebido)
) {
    responder(false, 'Solicitação inválida ou sessão expirada.', 403);
}

$pdo = getDB();
$codigoService = new CodigoVerificacaoService();
$tokenService = new TokenAtivacaoService();

$tokenReferencia = strtolower(trim((string) ($_SERVER['HTTP_X_ACTIVATION_TOKEN'] ?? '')));
$modo = $tokenReferencia !== '' ? 'profissional' : 'administrador';
$usuarioId = 0;
$usuario = false;

try {
    if ($modo === 'profissional') {
        $usuarioId = $tokenService->validar($pdo, $tokenReferencia);

        $stmt = $pdo->prepare(
            'SELECT
                u.email,
                u.ativo AS usuario_ativo,
                pr.ativo AS profissional_ativo,
                p.nome_completo,
                p.ativo AS pessoa_ativa,
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
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            responder(false, 'Não foi possível localizar o acesso pendente.', 404);
        }

        if (
            (int) $usuario['usuario_ativo'] === 1
            || (int) $usuario['profissional_ativo'] !== 1
            || (int) $usuario['pessoa_ativa'] !== 1
            || (int) $usuario['empresa_ativa'] !== 1
        ) {
            responder(false, 'Este acesso não está disponível para ativação.', 409);
        }
    } else {
        $usuarioId = (int) ($_SESSION['cadastro_usuario_id'] ?? 0);

        if ($usuarioId <= 0) {
            responder(
                false,
                'Sua sessão de verificação expirou. Inicie o cadastro novamente.',
                401
            );
        }

        $stmt = $pdo->prepare(
            'SELECT
                u.email,
                u.ativo AS usuario_ativo,
                a.nome_completo,
                a.ativo AS administrador_ativo,
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
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            responder(false, 'Não foi possível localizar o cadastro pendente.', 404);
        }

        if (
            (int) $usuario['usuario_ativo'] === 1
            || (int) $usuario['administrador_ativo'] === 1
        ) {
            responder(false, 'Esta conta já foi ativada.', 409);
        }
    }

    try {
        $codigo = $codigoService->reenviar($pdo, $usuarioId);
    } catch (RuntimeException $e) {
        responder(false, $e->getMessage(), 429);
    }

    $nome = (string) $usuario['nome_completo'];
    $empresa = (string) $usuario['nome_fantasia'];
    $validadeMinutos = 10;

    if ($modo === 'profissional') {
        $appUrl = rtrim((string) (getenv('APP_URL') ?: 'http://localhost:8096'), '/');
        $linkConfirmacao = $appUrl
            . '/confirmar-codigo.php?token='
            . urlencode($tokenReferencia);

        ob_start();
        require dirname(__DIR__) . '/templates/emails/acesso-profissional-codigo.php';
        $html = (string) ob_get_clean();
        $assunto = 'Seu código de acesso profissional - Salão Agenda';
        $texto = "Olá, {$nome}.\n\nSeu novo código de verificação é: {$codigo}\n\nAbra este link para confirmar seu e-mail:\n{$linkConfirmacao}\n\nEle expira em 10 minutos.";
    } else {
        ob_start();
        require dirname(__DIR__) . '/templates/emails/codigo-verificacao.php';
        $html = (string) ob_get_clean();
        $assunto = 'Seu código de verificação - Salão Agenda';
        $texto = "Olá, {$nome}.\n\nSeu novo código de verificação é: {$codigo}\n\nEle expira em 10 minutos.\n\nSe você não solicitou este código, ignore este e-mail.";
    }

    try {
        $emailService = new EmailService();
        $emailService->enviar(
            (string) $usuario['email'],
            $nome,
            $assunto,
            $html,
            $texto
        );
    } catch (RuntimeException $e) {
        error_log(
            'Erro SMTP ao reenviar código para usuario_id='
            . $usuarioId
            . ': '
            . $e->getMessage()
        );

        responder(
            false,
            'Não foi possível enviar o e-mail agora. Tente novamente em alguns instantes.',
            503
        );
    }

    $cooldownSegundos = $codigoService->segundosRestantesCooldown($pdo, $usuarioId);

    responder(
        true,
        'Enviamos um novo código para o seu e-mail.',
        200,
        ['cooldown_segundos' => $cooldownSegundos]
    );
} catch (RuntimeException $e) {
    responder(false, 'O convite é inválido ou expirou.', 401);
} catch (Throwable $e) {
    error_log(
        'Erro ao reenviar código para usuario_id='
        . $usuarioId
        . ': '
        . $e->getMessage()
    );

    responder(false, 'Não foi possível reenviar o código. Tente novamente.', 500);
}
