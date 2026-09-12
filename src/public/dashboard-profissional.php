<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

exigirProfissional();

$nome = (string) ($_SESSION['user_name'] ?? 'Profissional');
$empresa = (string) ($_SESSION['empresa_nome'] ?? '');
$email = (string) ($_SESSION['user_email'] ?? '');

$flashSuccess = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Área do profissional | Salão Agenda</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/dashboard-profissional.css">
</head>
<body>
<main class="professional-page">
    <div class="container">
        <div class="professional-card">
            <?php if ($flashSuccess !== ''): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <span class="badge badge-primary mb-3">Acesso profissional</span>
            <h1>Olá, <?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>.</h1>

            <?php if ($empresa !== ''): ?>
                <p class="lead mb-2">
                    Você está conectado à empresa
                    <strong><?= htmlspecialchars($empresa, ENT_QUOTES, 'UTF-8') ?></strong>.
                </p>
            <?php endif; ?>

            <p class="text-muted mb-4">
                <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>
            </p>

            <div class="alert alert-info mb-4">
                Seu acesso profissional está funcionando. Os recursos de agenda serão adicionados nesta área nas próximas etapas.
            </div>

            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between border-top pt-3">
                <div class="mb-3 mb-sm-0">
                    <strong><?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?></strong>
                    <div class="text-muted small">Profissional</div>
                </div>

                <div class="d-flex">
                    <a href="alterar-senha.php" class="btn btn-outline-primary mr-2">Alterar senha</a>
                    <a href="logout.php" class="btn btn-outline-secondary">Sair</a>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
