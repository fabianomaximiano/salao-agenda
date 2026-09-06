<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {

        $erro = 'Informe seu e-mail e sua senha.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $erro = 'Informe um e-mail válido.';

    } else {

        try {

            $pdo = getDB();

            $sql = "
                SELECT
                    u.id AS usuario_id,
                    u.email,
                    u.senha_hash,
                    u.ativo AS usuario_ativo,

                    ue.id AS usuario_empresa_id,
                    ue.empresa_id,
                    ue.pessoa_id,
                    ue.ativo AS vinculo_ativo,

                    e.nome_fantasia AS empresa_nome,
                    e.ativo AS empresa_ativa,

                    p.nome_completo AS pessoa_nome,

                    pa.slug AS papel_slug,
                    pa.nome AS papel_nome

                FROM usuarios u

                INNER JOIN usuario_empresas ue
                    ON ue.usuario_id = u.id

                INNER JOIN empresas e
                    ON e.id = ue.empresa_id

                LEFT JOIN pessoas p
                    ON p.id = ue.pessoa_id

                INNER JOIN usuario_empresa_papeis uep
                    ON uep.usuario_empresa_id = ue.id

                INNER JOIN papeis pa
                    ON pa.id = uep.papel_id

                WHERE u.email = :email
                  AND pa.slug = 'administrador'

                LIMIT 1
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':email' => $email
            ]);

            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {

                $erro = 'E-mail ou senha inválidos.';

            } elseif (
                !(bool) $usuario['usuario_ativo']
                || !(bool) $usuario['vinculo_ativo']
                || !(bool) $usuario['empresa_ativa']
            ) {

                $erro = 'Esta conta está desativada.';

            } elseif (
                empty($usuario['senha_hash'])
                || !password_verify(
                    $senha,
                    $usuario['senha_hash']
                )
            ) {

                $erro = 'E-mail ou senha inválidos.';

            } else {

                session_regenerate_id(true);

                $_SESSION['user_id'] =
                    (int) $usuario['usuario_id'];

                $_SESSION['user_email'] =
                    $usuario['email'];

                $_SESSION['user_name'] =
                    $usuario['pessoa_nome']
                    ?: $usuario['email'];

                $_SESSION['empresa_id'] =
                    (int) $usuario['empresa_id'];

                $_SESSION['empresa_nome'] =
                    $usuario['empresa_nome'];

                $_SESSION['papel'] =
                    $usuario['papel_slug'];

                $_SESSION['papel_nome'] =
                    $usuario['papel_nome'];

                $update = $pdo->prepare(
                    "
                    UPDATE usuarios
                    SET ultimo_acesso_em = NOW()
                    WHERE id = :id
                    "
                );

                $update->execute([
                    ':id' => $usuario['usuario_id']
                ]);

                header('Location: dashboard.php');
                exit;
            }

        } catch (Throwable $e) {

            error_log(
                'Erro no login administrativo: '
                . $e->getMessage()
            );

            $erro =
                'Não foi possível realizar o login agora.';
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
                            Acesse sua empresa
                        </h1>

                        <p class="text-muted mb-0">
                            Entre com sua conta administrativa.
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