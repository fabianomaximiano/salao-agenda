<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/db.php';

if (isset($_SESSION['user_id'])) {
    $destino = ($_SESSION['contexto'] ?? '') === 'profissional'
        ? 'dashboard-profissional.php'
        : 'dashboard.php';

    header('Location: ' . $destino);
    exit;
}

$erro = null;

const LOGIN_MAX_TENTATIVAS = 3;
const LOGIN_BLOQUEIO_MINUTOS = 15;

function obterIpLogin(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function hashIpLogin(string $ip): string
{
    return hash('sha256', $ip);
}

function loginEstaBloqueado(PDO $pdo, int $usuarioId, string $ipHash): bool
{
    $stmt = $pdo->prepare(
        'SELECT
            CASE
                WHEN bloqueado_ate IS NOT NULL AND bloqueado_ate > NOW() THEN 1
                ELSE 0
            END AS bloqueado
         FROM usuario_tentativas_login
         WHERE usuario_id = :usuario_id
           AND ip_hash = :ip_hash
         LIMIT 1'
    );
    $stmt->execute([
        ':usuario_id' => $usuarioId,
        ':ip_hash' => $ipHash,
    ]);

    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    return $registro && (int) $registro['bloqueado'] === 1;
}

function registrarFalhaLogin(PDO $pdo, int $usuarioId, string $ipHash): void
{
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'SELECT id, tentativas,
                    CASE
                        WHEN bloqueado_ate IS NOT NULL AND bloqueado_ate > NOW() THEN 1
                        ELSE 0
                    END AS bloqueado
             FROM usuario_tentativas_login
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
                'INSERT INTO usuario_tentativas_login
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
                'UPDATE usuario_tentativas_login
                 SET ultimo_erro_em = NOW()
                 WHERE id = :id'
            );
            $update->execute([':id' => (int) $registro['id']]);
        } else {
            $tentativas = (int) $registro['tentativas'] + 1;

            if ($tentativas >= LOGIN_MAX_TENTATIVAS) {
                $update = $pdo->prepare(
                    'UPDATE usuario_tentativas_login
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
                    'UPDATE usuario_tentativas_login
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

function limparFalhasLogin(PDO $pdo, int $usuarioId, string $ipHash): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM usuario_tentativas_login
         WHERE usuario_id = :usuario_id
           AND ip_hash = :ip_hash'
    );
    $stmt->execute([
        ':usuario_id' => $usuarioId,
        ':ip_hash' => $ipHash,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $senha = (string) ($_POST['senha'] ?? '');

    if ($email === '' || $senha === '') {
        $erro = 'Informe seu e-mail e sua senha.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } else {
        try {
            $pdo = getDB();
            $ipHash = hashIpLogin(obterIpLogin());

            $stmtUsuario = $pdo->prepare(
                'SELECT id, email, senha_hash, ativo
                 FROM usuarios
                 WHERE email = :email
                 LIMIT 1'
            );
            $stmtUsuario->execute([':email' => $email]);
            $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                $erro = 'E-mail ou senha inválidos.';
            } else {
                $usuarioId = (int) $usuario['id'];

                if (loginEstaBloqueado($pdo, $usuarioId, $ipHash)) {
                    $erro = 'Não foi possível realizar o acesso agora. Tente novamente mais tarde.';
                } elseif (
                    (int) $usuario['ativo'] !== 1
                    || empty($usuario['senha_hash'])
                    || !password_verify($senha, (string) $usuario['senha_hash'])
                ) {
                    if ((int) $usuario['ativo'] === 1 && !empty($usuario['senha_hash'])) {
                        registrarFalhaLogin($pdo, $usuarioId, $ipHash);

                        if (loginEstaBloqueado($pdo, $usuarioId, $ipHash)) {
                            $erro = 'Não foi possível realizar o acesso agora. Tente novamente mais tarde.';
                        } else {
                            $erro = 'E-mail ou senha inválidos.';
                        }
                    } else {
                        $erro = 'E-mail ou senha inválidos.';
                    }
                } else {
                    $stmtAdministrador = $pdo->prepare(
                        'SELECT
                            a.id AS administrador_id,
                            a.empresa_id,
                            a.nome_completo,
                            a.ativo AS administrador_ativo,
                            e.nome_fantasia AS empresa_nome,
                            e.ativo AS empresa_ativa
                         FROM administradores a
                         INNER JOIN empresas e
                           ON e.id = a.empresa_id
                         WHERE a.usuario_id = :usuario_id
                         LIMIT 2'
                    );
                    $stmtAdministrador->execute([':usuario_id' => $usuarioId]);
                    $administradores = $stmtAdministrador->fetchAll(PDO::FETCH_ASSOC);

                    $stmtProfissional = $pdo->prepare(
                        'SELECT
                            pr.id AS profissional_id,
                            pr.pessoa_id,
                            pr.empresa_id,
                            pr.ativo AS profissional_ativo,
                            p.nome_completo,
                            p.ativo AS pessoa_ativa,
                            e.nome_fantasia AS empresa_nome,
                            e.ativo AS empresa_ativa
                         FROM profissionais pr
                         INNER JOIN pessoas p
                           ON p.id = pr.pessoa_id
                          AND p.empresa_id = pr.empresa_id
                         INNER JOIN empresas e
                           ON e.id = pr.empresa_id
                         WHERE pr.usuario_id = :usuario_id
                         LIMIT 2'
                    );
                    $stmtProfissional->execute([':usuario_id' => $usuarioId]);
                    $profissionais = $stmtProfissional->fetchAll(PDO::FETCH_ASSOC);

                    $contextos = count($administradores) + count($profissionais);

                    if ($contextos !== 1) {
                        $erro = 'Não foi possível determinar o contexto desta conta.';
                    } elseif ($administradores) {
                        $contexto = $administradores[0];

                        if (
                            (int) $contexto['administrador_ativo'] !== 1
                            || (int) $contexto['empresa_ativa'] !== 1
                        ) {
                            $erro = 'Esta conta está desativada.';
                        } else {
                            limparFalhasLogin($pdo, $usuarioId, $ipHash);
                            session_regenerate_id(true);

                            $_SESSION['user_id'] = $usuarioId;
                            $_SESSION['user_email'] = (string) $usuario['email'];
                            $_SESSION['user_name'] = (string) $contexto['nome_completo'];
                            $_SESSION['empresa_id'] = (int) $contexto['empresa_id'];
                            $_SESSION['empresa_nome'] = (string) $contexto['empresa_nome'];
                            $_SESSION['administrador_id'] = (int) $contexto['administrador_id'];
                            $_SESSION['contexto'] = 'administrador';

                            unset($_SESSION['profissional_id'], $_SESSION['pessoa_id']);

                            $update = $pdo->prepare(
                                'UPDATE usuarios
                                 SET ultimo_acesso_em = NOW()
                                 WHERE id = :id'
                            );
                            $update->execute([':id' => $usuarioId]);

                            header('Location: dashboard.php');
                            exit;
                        }
                    } else {
                        $contexto = $profissionais[0];

                        if (
                            (int) $contexto['profissional_ativo'] !== 1
                            || (int) $contexto['pessoa_ativa'] !== 1
                            || (int) $contexto['empresa_ativa'] !== 1
                        ) {
                            $erro = 'Esta conta está desativada.';
                        } else {
                            limparFalhasLogin($pdo, $usuarioId, $ipHash);
                            session_regenerate_id(true);

                            $_SESSION['user_id'] = $usuarioId;
                            $_SESSION['user_email'] = (string) $usuario['email'];
                            $_SESSION['user_name'] = (string) $contexto['nome_completo'];
                            $_SESSION['empresa_id'] = (int) $contexto['empresa_id'];
                            $_SESSION['empresa_nome'] = (string) $contexto['empresa_nome'];
                            $_SESSION['profissional_id'] = (int) $contexto['profissional_id'];
                            $_SESSION['pessoa_id'] = (int) $contexto['pessoa_id'];
                            $_SESSION['contexto'] = 'profissional';

                            unset($_SESSION['administrador_id']);

                            $update = $pdo->prepare(
                                'UPDATE usuarios
                                 SET ultimo_acesso_em = NOW()
                                 WHERE id = :id'
                            );
                            $update->execute([':id' => $usuarioId]);

                            header('Location: dashboard-profissional.php');
                            exit;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('Erro no login: ' . $e->getMessage());
            $erro = 'Não foi possível realizar o login agora.';
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
        content="width=device-width, initial-scale=1, shrink-to-fit=no"
    >

    <title>Entrar | Agenda</title>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/login.css"
    >

</head>

<body>

<div class="login-page">

    <div class="container">

        <div class="row justify-content-center">

            <div class="col-12 col-sm-10 col-md-7 col-lg-5">

                <div class="login-card">

                    <div class="text-center mb-4">

                        <img
                            src="assets/img/logo-placeholder.svg"
                            alt="Agenda"
                            class="login-logo"
                        >

                        <h1 class="h3 font-weight-bold mt-3 mb-2">
                            Acesse sua conta
                        </h1>

                        <p class="text-muted mb-0">
                            Entre com seu e-mail corporativo e sua senha.
                        </p>

                    </div>


                    <?php if ($erro): ?>

                        <div
                            class="alert alert-danger"
                            role="alert"
                        >
                            <?= htmlspecialchars(
                                $erro,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </div>

                    <?php endif; ?>


                    <form
                        id="loginForm"
                        method="post"
                        action="login.php"
                        novalidate
                    >

                        <div class="form-group">

                            <label for="email">
                                E-mail
                            </label>

                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                name="email"
                                autocomplete="email"
                                required
                                autofocus
                            >

                            <div class="invalid-feedback">
                                Informe um e-mail válido.
                            </div>

                        </div>


                        <div class="form-group">

                            <label for="senha">
                                Senha
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                id="senha"
                                name="senha"
                                autocomplete="current-password"
                                required
                            >

                            <div class="invalid-feedback">
                                Informe sua senha.
                            </div>

                            <div class="text-right mt-2">
                                <a href="esqueci-senha.php">
                                    Esqueci minha senha
                                </a>
                            </div>

                        </div>


                        <button
                            type="submit"
                            class="btn btn-primary btn-lg btn-block mt-4"
                        >
                            Entrar
                        </button>

                    </form>


                    <div class="login-footer text-center">

                        <span>
                            Ainda não cadastrou sua empresa?
                        </span>

                        <a href="cadastro-empresa.php">
                            Criar conta
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<script src="assets/js/login.js"></script>

</body>

</html>