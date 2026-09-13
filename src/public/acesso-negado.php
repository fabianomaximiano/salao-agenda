<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

exigirLogin();

$pageTitle = 'Acesso não autorizado';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/partials/navbar.php';
?>

<main class="app-content">
    <div class="row justify-content-center">
        <div class="col-12 col-md-9 col-lg-7 col-xl-6">
            <div class="app-card">
                <div class="app-card-body text-center py-5">
                    <div class="mb-3">
                        <span class="badge badge-warning px-3 py-2">Acesso restrito</span>
                    </div>

                    <h1 class="h3 mb-3">Acesso não autorizado</h1>

                    <p class="text-muted mb-4">
                        Você não possui permissão para acessar esta área.
                        Se precisar deste recurso, solicite acesso ao administrador da empresa.
                    </p>

                    <a href="dashboard.php" class="btn btn-primary">
                        Voltar ao Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
