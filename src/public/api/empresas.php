<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/services/EmpresaService.php';
require_once dirname(__DIR__, 2) . '/services/CodigoVerificacaoService.php';
require_once dirname(__DIR__, 2) . '/services/EmailService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    exit('Método não permitido.');
}

$csrfRecebido = (string) ($_POST['csrf_token'] ?? '');
$csrfSessao = (string) (
    $_SESSION['csrf_cadastro_empresa']
    ?? ''
);

if (
    $csrfRecebido === ''
    || $csrfSessao === ''
    || !hash_equals($csrfSessao, $csrfRecebido)
) {
    http_response_code(403);

    $_SESSION['cadastro_empresa_erro'] =
        'Sua sessão expirou ou a solicitação é inválida. Tente novamente.';

    header('Location: ../cadastro-empresa.php');
    exit;
}

/*
 * Remove referências de um cadastro pendente anterior.
 *
 * Somente depois que o novo cadastro for efetivamente criado
 * gravaremos o novo usuario_id na sessão.
 */
unset(
    $_SESSION['cadastro_usuario_id'],
    $_SESSION['cadastro_email'],
    $_SESSION['cadastro_verificacao_erro']
);

try {
    $pdo = getDB();

    /*
     * Cria, dentro de transação:
     *
     * - empresa inativa;
     * - vínculo com segmento;
     * - usuário inativo e sem senha;
     * - administrador inativo.
     */
    $empresaService = new EmpresaService();

    $resultado = $empresaService->criar(
        $pdo,
        $_POST
    );

    $usuarioId = (int) $resultado['usuario_id'];

    /*
     * A partir daqui o cadastro já foi persistido.
     *
     * Guardamos imediatamente o usuário pendente para
     * permitir recuperar o processo caso o envio do
     * e-mail falhe.
     */
    $_SESSION['cadastro_usuario_id'] = $usuarioId;

    /*
     * Gera o primeiro código de seis dígitos.
     */
    $codigoService = new CodigoVerificacaoService();

    $codigo = $codigoService->gerar(
        $pdo,
        $usuarioId
    );

    /*
     * Recupera exclusivamente os dados vinculados ao
     * usuário recém-criado.
     */
    $stmt = $pdo->prepare(
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

    $stmt->execute([
        ':usuario_id' => $usuarioId,
    ]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        throw new RuntimeException(
            'Não foi possível localizar o cadastro criado.'
        );
    }

    $nome = (string) $usuario['nome_completo'];
    $empresa = (string) $usuario['nome_fantasia'];
    $email = (string) $usuario['email'];
    $validadeMinutos = 10;

    /*
     * Monta o e-mail com o código.
     */
    ob_start();

    require dirname(__DIR__, 2)
        . '/templates/emails/codigo-verificacao.php';

    $html = (string) ob_get_clean();

    /*
     * Envia o código.
     *
     * O valor bruto do código nunca é devolvido ao navegador.
     */
    $emailService = new EmailService();

    $emailService->enviar(
        $email,
        $nome,
        'Seu código de verificação - Salão Agenda',
        $html,
        "Olá, {$nome}.\n\n"
        . "Seu código de verificação é: {$codigo}\n\n"
        . "Ele expira em 10 minutos.\n\n"
        . "Se você não solicitou este cadastro, ignore este e-mail."
    );

    $_SESSION['cadastro_email'] = $email;

    /*
     * Cadastro criado e código enviado.
     */
    header('Location: ../confirmar-codigo.php');
    exit;
} catch (PDOException $e) {
    error_log(
        'Erro de banco no cadastro da empresa: '
        . $e->getMessage()
    );

    /*
     * Se já existe usuario_id na sessão, a criação da
     * empresa foi concluída antes da falha.
     *
     * Não permitimos que o usuário tente cadastrar
     * novamente a mesma empresa.
     */
    if (!empty($_SESSION['cadastro_usuario_id'])) {
        $_SESSION['cadastro_verificacao_erro'] =
            'Seu cadastro foi criado, mas ocorreu um problema ao preparar a verificação. Tente reenviar o código.';

        header('Location: ../confirmar-codigo.php');
        exit;
    }

    $_SESSION['cadastro_empresa_erro'] =
        'Não foi possível concluir o cadastro. Verifique os dados e tente novamente.';

    header('Location: ../cadastro-empresa.php');
    exit;
} catch (RuntimeException $e) {
    /*
     * Quando o usuario_id já está na sessão, o cadastro
     * foi persistido. Uma falha posterior não deve mandar
     * o usuário novamente para o formulário da empresa.
     */
    if (!empty($_SESSION['cadastro_usuario_id'])) {
        error_log(
            'Erro após criação do cadastro: '
            . $e->getMessage()
        );

        $_SESSION['cadastro_verificacao_erro'] =
            'Seu cadastro foi criado, mas não conseguimos enviar o código agora. Tente reenviar.';

        header('Location: ../confirmar-codigo.php');
        exit;
    }

    /*
     * Aqui são erros de validação ocorridos antes da
     * criação definitiva do cadastro.
     */
    $_SESSION['cadastro_empresa_erro'] =
        $e->getMessage();

    header('Location: ../cadastro-empresa.php');
    exit;
} catch (Throwable $e) {
    error_log(
        'Erro inesperado no cadastro da empresa: '
        . $e->getMessage()
    );

    if (!empty($_SESSION['cadastro_usuario_id'])) {
        $_SESSION['cadastro_verificacao_erro'] =
            'Seu cadastro foi criado, mas não foi possível concluir a verificação. Tente reenviar o código.';

        header('Location: ../confirmar-codigo.php');
        exit;
    }

    $_SESSION['cadastro_empresa_erro'] =
        'Não foi possível concluir o cadastro. Tente novamente.';

    header('Location: ../cadastro-empresa.php');
    exit;
}