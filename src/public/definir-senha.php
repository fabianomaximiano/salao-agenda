<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once dirname(__DIR__) . '/includes/db.php';

$pdo = getDB();
$erro = '';
$sucesso = '';
$token = '';

if (
    empty($_SESSION['csrf_definir_senha'])
    || !is_string($_SESSION['csrf_definir_senha'])
) {
    $_SESSION['csrf_definir_senha'] = bin2hex(random_bytes(32));
}

function obterToken(): string
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return strtolower(trim((string) ($_POST['token'] ?? '')));
    }

    return strtolower(trim((string) ($_GET['token'] ?? '')));
}

function tokenTemFormatoValido(string $token): bool
{
    return preg_match('/^[a-f0-9]{64}$/', $token) === 1;
}

function buscarContextoAtivacao(
    PDO $pdo,
    string $tokenHash,
    bool $bloquear = false
): array|false {
    $sql = '
        SELECT
            t.id AS token_id,
            t.usuario_id,
            t.expira_em,
            t.utilizado_em,
            t.revogado_em,
            CASE
                WHEN t.expira_em <= NOW() THEN 1
                ELSE 0
            END AS token_expirado,

            u.email,
            u.senha_hash,
            u.ativo AS usuario_ativo,

            a.id AS administrador_id,
            a.nome_completo AS administrador_nome,
            a.empresa_id AS administrador_empresa_id,
            a.ativo AS administrador_ativo,

            pr.id AS profissional_id,
            pr.pessoa_id,
            pr.empresa_id AS profissional_empresa_id,
            pr.ativo AS profissional_ativo,

            c.id AS colaborador_id,
            c.pessoa_id AS colaborador_pessoa_id,
            c.empresa_id AS colaborador_empresa_id,
            c.ativo AS colaborador_ativo,

            COALESCE(pp.nome_completo, pc.nome_completo) AS pessoa_nome,
            COALESCE(pp.ativo, pc.ativo) AS pessoa_ativa,

            e.id AS empresa_id,
            e.nome_fantasia,
            e.ativo AS empresa_ativa

        FROM usuario_tokens_ativacao t

        INNER JOIN usuarios u
            ON u.id = t.usuario_id

        LEFT JOIN administradores a
            ON a.usuario_id = u.id

        LEFT JOIN profissionais pr
            ON pr.usuario_id = u.id

        LEFT JOIN colaboradores c
            ON c.usuario_id = u.id

        LEFT JOIN pessoas pp
            ON pp.id = pr.pessoa_id
           AND pp.empresa_id = pr.empresa_id

        LEFT JOIN pessoas pc
            ON pc.id = c.pessoa_id
           AND pc.empresa_id = c.empresa_id

        LEFT JOIN empresas e
            ON e.id = COALESCE(a.empresa_id, pr.empresa_id, c.empresa_id)

        WHERE t.token_hash = :token_hash
        LIMIT 1
    ';

    if ($bloquear) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':token_hash' => $tokenHash]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function tokenEstaDisponivel(array $contexto): bool
{
    if (
        $contexto['utilizado_em'] !== null
        || $contexto['revogado_em'] !== null
    ) {
        return false;
    }

    return (int) ($contexto['token_expirado'] ?? 1) === 0;
}

function obterTipoContexto(array $contexto): ?string
{
    $tipos = [];
    if (!empty($contexto['administrador_id'])) $tipos[] = 'administrador';
    if (!empty($contexto['profissional_id'])) $tipos[] = 'profissional';
    if (!empty($contexto['colaborador_id'])) $tipos[] = 'colaborador';

    return count($tipos) === 1 ? $tipos[0] : null;
}

function contextoPodeSerAtivado(array $contexto, string $tipo): bool
{
    if ((int) $contexto['usuario_ativo'] === 1) {
        return false;
    }

    if ($tipo === 'administrador') {
        return (int) ($contexto['administrador_ativo'] ?? 0) !== 1;
    }

    if ($tipo === 'profissional') {
        return (int) ($contexto['profissional_ativo'] ?? 0) === 1
            && (int) ($contexto['pessoa_ativa'] ?? 0) === 1
            && (int) ($contexto['empresa_ativa'] ?? 0) === 1;
    }

    return (int) ($contexto['colaborador_ativo'] ?? 0) === 1
        && (int) ($contexto['pessoa_ativa'] ?? 0) === 1
        && (int) ($contexto['empresa_ativa'] ?? 0) === 1;
}

function validarSenha(string $senha): ?string
{
    if (strlen($senha) < 12) {
        return 'A senha deve ter pelo menos 12 caracteres.';
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

$token = obterToken();
$contexto = false;
$tipoContexto = null;
$tokenDisponivel = false;

if ($token === '' || !tokenTemFormatoValido($token)) {
    $erro = 'O link de ativação é inválido.';
} else {
    $tokenHash = hash('sha256', $token);

    try {
        $contexto = buscarContextoAtivacao($pdo, $tokenHash);

        if (!$contexto) {
            $erro = 'O link de ativação é inválido.';
        } elseif (!tokenEstaDisponivel($contexto)) {
            $erro = 'Este link de ativação expirou ou já foi utilizado.';
        } else {
            $tipoContexto = obterTipoContexto($contexto);

            if ($tipoContexto === null) {
                $erro = 'Não foi possível determinar o contexto desta conta.';
            } elseif (!contextoPodeSerAtivado($contexto, $tipoContexto)) {
                $erro = $tipoContexto === 'profissional'
                    ? 'Este acesso profissional não está disponível para ativação.'
                    : 'Esta conta já foi ativada.';
            }
        }

        $tokenDisponivel = $erro === ''
            && is_array($contexto)
            && $tipoContexto !== null
            && tokenEstaDisponivel($contexto);
    } catch (Throwable $e) {
        error_log('Erro ao validar token de ativação: ' . $e->getMessage());
        $erro = 'Não foi possível validar o link de ativação agora.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $erro === '') {
    $csrfRecebido = (string) ($_POST['csrf_token'] ?? '');
    $csrfSessao = (string) ($_SESSION['csrf_definir_senha'] ?? '');

    if (
        $csrfRecebido === ''
        || $csrfSessao === ''
        || !hash_equals($csrfSessao, $csrfRecebido)
    ) {
        $erro = 'Sua sessão expirou ou a solicitação é inválida.';
    } else {
        $senha = (string) ($_POST['senha'] ?? '');
        $confirmacaoSenha = (string) ($_POST['confirmacao_senha'] ?? '');
        $erroSenha = validarSenha($senha);

        if ($erroSenha !== null) {
            $erro = $erroSenha;
        } elseif ($senha !== $confirmacaoSenha) {
            $erro = 'A confirmação da senha não confere.';
        } else {
            try {
                $pdo->beginTransaction();

                $contextoBloqueado = buscarContextoAtivacao($pdo, $tokenHash, true);

                if (!$contextoBloqueado || !tokenEstaDisponivel($contextoBloqueado)) {
                    throw new RuntimeException('Token de ativação expirado ou já utilizado.');
                }

                $tipoBloqueado = obterTipoContexto($contextoBloqueado);

                if (
                    $tipoBloqueado === null
                    || !contextoPodeSerAtivado($contextoBloqueado, $tipoBloqueado)
                ) {
                    throw new RuntimeException('Conta indisponível para ativação.');
                }

                $usuarioId = (int) $contextoBloqueado['usuario_id'];
                $tokenId = (int) $contextoBloqueado['token_id'];
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

                if ($senhaHash === false) {
                    throw new RuntimeException('Não foi possível gerar o hash da senha.');
                }

                $stmtUsuario = $pdo->prepare(
                    'UPDATE usuarios
                     SET senha_hash = :senha_hash,
                         ativo = 1
                     WHERE id = :usuario_id
                       AND ativo = 0'
                );
                $stmtUsuario->execute([
                    ':senha_hash' => $senhaHash,
                    ':usuario_id' => $usuarioId,
                ]);

                if ($stmtUsuario->rowCount() !== 1) {
                    throw new RuntimeException('Não foi possível ativar o usuário.');
                }

                if ($tipoBloqueado === 'administrador') {
                    $administradorId = (int) $contextoBloqueado['administrador_id'];
                    $empresaId = (int) $contextoBloqueado['administrador_empresa_id'];

                    $stmtAdministrador = $pdo->prepare(
                        'UPDATE administradores
                         SET ativo = 1
                         WHERE id = :administrador_id
                           AND usuario_id = :usuario_id
                           AND empresa_id = :empresa_id'
                    );
                    $stmtAdministrador->execute([
                        ':administrador_id' => $administradorId,
                        ':usuario_id' => $usuarioId,
                        ':empresa_id' => $empresaId,
                    ]);

                    if ($stmtAdministrador->rowCount() !== 1) {
                        throw new RuntimeException('Não foi possível ativar o administrador.');
                    }

                    $stmtEmpresa = $pdo->prepare(
                        'UPDATE empresas
                         SET ativo = 1
                         WHERE id = :empresa_id'
                    );
                    $stmtEmpresa->execute([':empresa_id' => $empresaId]);

                    if ($stmtEmpresa->rowCount() !== 1) {
                        throw new RuntimeException('Não foi possível ativar a empresa.');
                    }
                }

                $stmtToken = $pdo->prepare(
                    'UPDATE usuario_tokens_ativacao
                     SET utilizado_em = NOW()
                     WHERE id = :token_id
                       AND utilizado_em IS NULL
                       AND revogado_em IS NULL
                       AND expira_em > NOW()'
                );
                $stmtToken->execute([':token_id' => $tokenId]);

                if ($stmtToken->rowCount() !== 1) {
                    throw new RuntimeException('Não foi possível consumir o token de ativação.');
                }

                $stmtRevogar = $pdo->prepare(
                    'UPDATE usuario_tokens_ativacao
                     SET revogado_em = NOW()
                     WHERE usuario_id = :usuario_id
                       AND id <> :token_id
                       AND utilizado_em IS NULL
                       AND revogado_em IS NULL'
                );
                $stmtRevogar->execute([
                    ':usuario_id' => $usuarioId,
                    ':token_id' => $tokenId,
                ]);

                $pdo->commit();

                unset(
                    $_SESSION['csrf_definir_senha'],
                    $_SESSION['csrf_confirmacao_codigo'],
                    $_SESSION['cadastro_usuario_id'],
                    $_SESSION['cadastro_email'],
                    $_SESSION['cadastro_verificacao_erro']
                );

                session_regenerate_id(true);

                $sucesso = match ($tipoBloqueado) {
                    'profissional' => 'Senha criada com sucesso. Seu acesso profissional está ativo e você já pode entrar.',
                    'colaborador' => 'Senha criada com sucesso. Seu acesso de colaborador está ativo e você já pode entrar.',
                    default => 'Senha criada com sucesso. Sua empresa foi ativada e você já pode entrar.',
                };

                $tipoContexto = $tipoBloqueado;
            } catch (RuntimeException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Falha controlada na ativação do usuário: ' . $e->getMessage());
                $erro = 'Não foi possível concluir a ativação. O link pode ter expirado ou já ter sido utilizado.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Erro inesperado na ativação do usuário: ' . $e->getMessage());
                $erro = 'Não foi possível concluir a ativação agora. Tente novamente.';
            }
        }
    }
}

$nome = '';
if (is_array($contexto)) {
    $nome = trim((string) (
        $tipoContexto === 'administrador'
            ? ($contexto['administrador_nome'] ?? '')
            : ($contexto['pessoa_nome'] ?? '')
    ));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Criar senha | Salão Agenda</title>
    <style>
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; font-family:Arial,Helvetica,sans-serif; background:#f5f5f5; color:#222; }
        .card { width:100%; max-width:460px; padding:32px; background:#fff; border:1px solid #ddd; border-radius:10px; }
        h1 { margin-top:0; margin-bottom:12px; font-size:28px; }
        p { line-height:1.5; }
        label { display:block; margin-top:18px; margin-bottom:6px; font-weight:700; }
        input { width:100%; padding:13px 14px; border:1px solid #bbb; border-radius:6px; font-size:16px; }
        button, .botao { display:inline-block; width:100%; margin-top:22px; padding:14px 18px; border:0; border-radius:6px; cursor:pointer; text-align:center; text-decoration:none; font-size:16px; background:#222; color:#fff; }
        .mensagem { margin-bottom:20px; padding:13px 14px; border-radius:6px; line-height:1.4; }
        .erro { background:#fdecec; }
        .sucesso { background:#eaf7ec; }
        .regras { margin-top:10px; font-size:14px; color:#555; }
    </style>
</head>
<body>
<div class="card">
    <?php if ($sucesso !== ''): ?>
        <div class="mensagem sucesso"><?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?></div>
        <a class="botao" href="login.php">Ir para o login</a>
    <?php elseif ($erro !== '' && !$tokenDisponivel): ?>
        <h1>Link inválido</h1>
        <div class="mensagem erro"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
        <a class="botao" href="login.php">Ir para o login</a>
    <?php else: ?>
        <h1>Crie sua senha</h1>

        <?php if ($nome !== ''): ?>
            <p>Olá, <strong><?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
        <?php endif; ?>

        <p>
            <?= $tipoContexto === 'profissional'
                ? 'Defina a senha que será usada para acessar sua conta profissional no Salão Agenda.'
                : ($tipoContexto === 'colaborador'
                    ? 'Defina a senha que será usada para acessar sua conta de colaborador no Salão Agenda.'
                    : 'Defina a senha que será usada para acessar sua empresa no Salão Agenda.') ?>
        </p>

        <?php if ($erro !== ''): ?>
            <div class="mensagem erro"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_definir_senha'], ENT_QUOTES, 'UTF-8') ?>">

            <label for="senha">Nova senha</label>
            <input type="password" id="senha" name="senha" minlength="12" maxlength="255" autocomplete="new-password" required autofocus>
            <div class="regras">Use pelo menos 12 caracteres, incluindo uma letra maiúscula, uma minúscula e um número.</div>

            <label for="confirmacao_senha">Confirme a senha</label>
            <input type="password" id="confirmacao_senha" name="confirmacao_senha" minlength="12" maxlength="255" autocomplete="new-password" required>

            <button type="submit">
                <?= in_array($tipoContexto, ['profissional', 'colaborador'], true) ? 'Criar senha e ativar acesso' : 'Criar senha e ativar empresa' ?>
            </button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
