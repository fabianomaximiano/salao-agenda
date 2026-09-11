<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/services/CodigoVerificacaoService.php';
require_once dirname(__DIR__) . '/services/EmailService.php';

function responder(
    bool $sucesso,
    string $mensagem,
    int $status = 200
): never {
    http_response_code($status);

    echo json_encode(
        [
            'sucesso' => $sucesso,
            'mensagem' => $mensagem,
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(
        false,
        'Método não permitido.',
        405
    );
}

$csrfRecebido = (string) (
    $_SERVER['HTTP_X_CSRF_TOKEN']
    ?? ''
);

$csrfSessao = (string) (
    $_SESSION['csrf_confirmacao_codigo']
    ?? ''
);

if (
    $csrfRecebido === ''
    || $csrfSessao === ''
    || !hash_equals($csrfSessao, $csrfRecebido)
) {
    responder(
        false,
        'Solicitação inválida ou sessão expirada.',
        403
    );
}

$usuarioId = (int) (
    $_SESSION['cadastro_usuario_id']
    ?? 0
);

if ($usuarioId <= 0) {
    responder(
        false,
        'Sua sessão de verificação expirou. Inicie o cadastro novamente.',
        401
    );
}

try {
    $pdo = getDB();

    /*
     * Localiza o cadastro pendente e confirma que usuário,
     * administrador e empresa pertencem ao mesmo relacionamento.
     *
     * A empresa permanece inativa até a definição da senha.
     */
    $stmt = $pdo->prepare(
        '
        SELECT
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
        LIMIT 1
        '
    );

    $stmt->execute([
        ':usuario_id' => $usuarioId,
    ]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        responder(
            false,
            'Não foi possível localizar o cadastro pendente.',
            404
        );
    }

    /*
     * Se usuário ou administrador já estiverem ativos,
     * o fluxo de verificação já foi concluído.
     *
     * A empresa pode continuar inativa nesta etapa porque
     * sua ativação ocorre somente após a criação da senha.
     */
    if (
        (int) $usuario['usuario_ativo'] === 1
        || (int) $usuario['administrador_ativo'] === 1
    ) {
        responder(
            false,
            'Esta conta já foi ativada.',
            409
        );
    }

    $codigoService = new CodigoVerificacaoService();

    /*
     * Erros desta etapa representam regras de negócio:
     *
     * - cooldown de 60 segundos;
     * - limite máximo de códigos por hora;
     * - impossibilidade de emitir um novo código.
     */
    try {
        $codigo = $codigoService->reenviar(
            $pdo,
            $usuarioId
        );
    } catch (RuntimeException $e) {
        responder(
            false,
            $e->getMessage(),
            429
        );
    }

    $nome = (string) $usuario['nome_completo'];
    $empresa = (string) $usuario['nome_fantasia'];
    $validadeMinutos = 10;

    ob_start();

    try {
        require dirname(__DIR__)
            . '/templates/emails/codigo-verificacao.php';

        $html = (string) ob_get_clean();
    } catch (Throwable $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        throw $e;
    }

    /*
     * A geração do código já ocorreu no banco.
     * Falha SMTP não é regra de cooldown, portanto retorna 503.
     *
     * O código permanece válido e pode ser substituído por um novo
     * reenvio depois que o cooldown permitido pelo backend terminar.
     */
    try {
        $emailService = new EmailService();

        $emailService->enviar(
            (string) $usuario['email'],
            $nome,
            'Seu código de verificação - Salão Agenda',
            $html,
            "Olá, {$nome}.\n\n"
            . "Seu novo código de verificação é: {$codigo}\n\n"
            . "Ele expira em 10 minutos.\n\n"
            . "Se você não solicitou este código, ignore este e-mail."
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

    responder(
        true,
        'Enviamos um novo código para o seu e-mail.'
    );
} catch (Throwable $e) {
    error_log(
        'Erro ao reenviar código para usuario_id='
        . $usuarioId
        . ': '
        . $e->getMessage()
    );

    responder(
        false,
        'Não foi possível reenviar o código. Tente novamente.',
        500
    );
}
