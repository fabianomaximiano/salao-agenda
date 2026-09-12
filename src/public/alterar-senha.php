<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigirLogin();

$pdo = getDB();
$usuarioId = (int) $_SESSION['user_id'];
$contexto = (string) ($_SESSION['contexto'] ?? '');
$erro = null;

const ALTERAR_SENHA_MAX_TENTATIVAS = 3;
const ALTERAR_SENHA_BLOQUEIO_MINUTOS = 15;

if (!isset($_SESSION['csrf_alterar_senha'])) {
    $_SESSION['csrf_alterar_senha'] = bin2hex(random_bytes(32));
}

function senhaForte(string $senha): bool
{
    return strlen($senha) >= 12
        && strlen($senha) <= 255
        && preg_match('/[A-Z]/', $senha) === 1
        && preg_match('/[a-z]/', $senha) === 1
        && preg_match('/[0-9]/', $senha) === 1;
}

function alteracaoSenhaEstaBloqueada(PDO $pdo, int $usuarioId, string $ipHash): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM usuario_tentativas_alteracao_senha
         WHERE usuario_id = :usuario_id
           AND ip_hash = :ip_hash
           AND bloqueado_ate IS NOT NULL
           AND bloqueado_ate > NOW()
         LIMIT 1'
    );
    $stmt->execute([
        ':usuario_id' => $usuarioId,
        ':ip_hash' => $ipHash,
    ]);

    return (bool) $stmt->fetchColumn();
}

function registrarFalhaAlteracaoSenha(PDO $pdo, int $usuarioId, string $ipHash): void
{
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'SELECT id, tentativas,
                    CASE
                        WHEN bloqueado_ate IS NOT NULL AND bloqueado_ate > NOW() THEN 1
                        ELSE 0
                    END AS bloqueado
             FROM usuario_tentativas_alteracao_senha
             WHERE usuario_id = :usuario_id
               AND ip_hash = :ip_hash
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':ip_hash' => $ipHash,
        ]);

        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$registro) {
            $insert = $pdo->prepare(
                'INSERT INTO usuario_tentativas_alteracao_senha
                    (usuario_id, ip_hash, tentativas, ultimo_erro_em)
                 VALUES
                    (:usuario_id, :ip_hash, 1, NOW())'
            );
            $insert->execute([
                ':usuario_id' => $usuarioId,
                ':ip_hash' => $ipHash,
            ]);
        } elseif ((int) $registro['bloqueado'] === 1) {
            $update = $pdo->prepare(
                'UPDATE usuario_tentativas_alteracao_senha
                 SET ultimo_erro_em = NOW()
                 WHERE id = :id'
            );
            $update->execute([':id' => (int) $registro['id']]);
        } else {
            $tentativas = (int) $registro['tentativas'] + 1;

            if ($tentativas >= ALTERAR_SENHA_MAX_TENTATIVAS) {
                $update = $pdo->prepare(
                    'UPDATE usuario_tentativas_alteracao_senha
                     SET tentativas = :tentativas,
                         bloqueado_ate = DATE_ADD(NOW(), INTERVAL 15 MINUTE),
                         ultimo_erro_em = NOW()
                     WHERE id = :id'
                );
                $update->execute([
                    ':tentativas' => $tentativas,
                    ':id' => (int) $registro['id'],
                ]);
            } else {
                $update = $pdo->prepare(
                    'UPDATE usuario_tentativas_alteracao_senha
                     SET tentativas = :tentativas,
                         bloqueado_ate = NULL,
                         ultimo_erro_em = NOW()
                     WHERE id = :id'
                );
                $update->execute([
                    ':tentativas' => $tentativas,
                    ':id' => (int) $registro['id'],
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function limparFalhasAlteracaoSenha(PDO $pdo, int $usuarioId, string $ipHash): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM usuario_tentativas_alteracao_senha
         WHERE usuario_id = :usuario_id
           AND ip_hash = :ip_hash'
    );
    $stmt->execute([
        ':usuario_id' => $usuarioId,
        ':ip_hash' => $ipHash,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf_token'] ?? '');
    $senhaAtual = (string) ($_POST['senha_atual'] ?? '');
    $novaSenha = (string) ($_POST['nova_senha'] ?? '');
    $confirmacao = (string) ($_POST['confirmar_senha'] ?? '');
    $ipHash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));

    if (!hash_equals((string) $_SESSION['csrf_alterar_senha'], $csrf)) {
        $erro = 'Não foi possível validar a solicitação. Atualize a página e tente novamente.';
    } elseif ($senhaAtual === '' || $novaSenha === '' || $confirmacao === '') {
        $erro = 'Preencha todos os campos.';
    } elseif (alteracaoSenhaEstaBloqueada($pdo, $usuarioId, $ipHash)) {
        $erro = 'Não foi possível alterar a senha agora. Tente novamente mais tarde.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'SELECT senha_hash, ativo
                 FROM usuarios
                 WHERE id = :id
                 LIMIT 1'
            );
            $stmt->execute([':id' => $usuarioId]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$usuario
                || (int) $usuario['ativo'] !== 1
                || empty($usuario['senha_hash'])
            ) {
                $erro = 'Não foi possível alterar a senha desta conta.';
            } elseif (!password_verify($senhaAtual, (string) $usuario['senha_hash'])) {
                registrarFalhaAlteracaoSenha($pdo, $usuarioId, $ipHash);

                $erro = alteracaoSenhaEstaBloqueada($pdo, $usuarioId, $ipHash)
                    ? 'Não foi possível alterar a senha agora. Tente novamente mais tarde.'
                    : 'A senha atual está incorreta.';
            } elseif ($novaSenha !== $confirmacao) {
                $erro = 'A confirmação da nova senha não confere.';
            } elseif (!senhaForte($novaSenha)) {
                $erro = 'A nova senha deve ter de 12 a 255 caracteres, com letra maiúscula, letra minúscula e número.';
            } elseif (password_verify($novaSenha, (string) $usuario['senha_hash'])) {
                $erro = 'A nova senha deve ser diferente da senha atual.';
            } else {
                $novoHash = password_hash($novaSenha, PASSWORD_DEFAULT);

                if ($novoHash === false) {
                    throw new RuntimeException('Falha ao gerar o hash da nova senha.');
                }

                $pdo->beginTransaction();

                try {
                    $update = $pdo->prepare(
                        'UPDATE usuarios
                         SET senha_hash = :senha_hash
                         WHERE id = :id
                           AND ativo = 1'
                    );
                    $update->execute([
                        ':senha_hash' => $novoHash,
                        ':id' => $usuarioId,
                    ]);

                    if ($update->rowCount() !== 1) {
                        throw new RuntimeException('Usuário não atualizado.');
                    }

                    $limpar = $pdo->prepare(
                        'DELETE FROM usuario_tentativas_alteracao_senha
                         WHERE usuario_id = :usuario_id'
                    );
                    $limpar->execute([':usuario_id' => $usuarioId]);

                    $revogar = $pdo->prepare(
                        'UPDATE usuario_tokens_recuperacao_senha
                         SET revogado_em = NOW()
                         WHERE usuario_id = :usuario_id
                           AND utilizado_em IS NULL
                           AND revogado_em IS NULL'
                    );
                    $revogar->execute([':usuario_id' => $usuarioId]);

                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    throw $e;
                }

                session_regenerate_id(true);
                $_SESSION['csrf_alterar_senha'] = bin2hex(random_bytes(32));
                $_SESSION['flash_success'] = 'Senha alterada com sucesso.';

                $destino = $contexto === 'profissional'
                    ? 'dashboard-profissional.php'
                    : 'dashboard.php';

                header('Location: ' . $destino);
                exit;
            }
        } catch (Throwable $e) {
            error_log('Erro ao alterar senha: ' . $e->getMessage());
            $erro = 'Não foi possível alterar sua senha agora.';
        }
    }
}

$voltar = $contexto === 'profissional'
    ? 'dashboard-profissional.php'
    : 'dashboard.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Alterar senha | Salão Agenda</title>
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
                        <h1 class="h3 font-weight-bold mt-3 mb-2">Alterar senha</h1>
                        <p class="text-muted mb-0">
                            Confirme sua senha atual e escolha uma nova senha.
                        </p>
                    </div>

                    <?php if ($erro): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="alterar-senha.php" novalidate>
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars((string) $_SESSION['csrf_alterar_senha'], ENT_QUOTES, 'UTF-8') ?>"
                        >

                        <div class="form-group">
                            <label for="senha_atual">Senha atual</label>
                            <input
                                type="password"
                                class="form-control"
                                id="senha_atual"
                                name="senha_atual"
                                autocomplete="current-password"
                                required
                                autofocus
                            >
                        </div>

                        <div class="form-group">
                            <label for="nova_senha">Nova senha</label>
                            <input
                                type="password"
                                class="form-control"
                                id="nova_senha"
                                name="nova_senha"
                                autocomplete="new-password"
                                minlength="12"
                                maxlength="255"
                                required
                            >
                            <small class="form-text text-muted">
                                Mínimo de 12 caracteres, com maiúscula, minúscula e número.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="confirmar_senha">Confirmar nova senha</label>
                            <input
                                type="password"
                                class="form-control"
                                id="confirmar_senha"
                                name="confirmar_senha"
                                autocomplete="new-password"
                                minlength="12"
                                maxlength="255"
                                required
                            >
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg btn-block mt-4">
                            Alterar senha
                        </button>

                        <a href="<?= htmlspecialchars($voltar, ENT_QUOTES, 'UTF-8') ?>"
                           class="btn btn-link btn-block mt-2">
                            Voltar
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
