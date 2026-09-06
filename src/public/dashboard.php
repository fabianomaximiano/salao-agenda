<?php

declare(strict_types=1);

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Dashboard';
$pageCss = 'dashboard.css';
$pageJs = 'dashboard.js';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/partials/navbar.php';

?>

<main class="app-content">

    <div class="app-page-header d-md-flex justify-content-between align-items-center">

        <div>
            <h1>Dashboard</h1>

            <p>
                Visão geral da operação da sua empresa.
            </p>
        </div>

        <div class="mt-3 mt-md-0">

            <a
                href="agenda.php"
                class="btn btn-primary"
            >
                Ver agenda
            </a>

        </div>

    </div>


    <div class="row">

        <div class="col-12 col-sm-6 col-xl-3 mb-4">

            <div class="app-stat-card">

                <p class="app-stat-label">
                    Agendamentos hoje
                </p>

                <p class="app-stat-value">
                    0
                </p>

            </div>

        </div>


        <div class="col-12 col-sm-6 col-xl-3 mb-4">

            <div class="app-stat-card">

                <p class="app-stat-label">
                    Clientes
                </p>

                <p class="app-stat-value">
                    0
                </p>

            </div>

        </div>


        <div class="col-12 col-sm-6 col-xl-3 mb-4">

            <div class="app-stat-card">

                <p class="app-stat-label">
                    Profissionais
                </p>

                <p class="app-stat-value">
                    0
                </p>

            </div>

        </div>


        <div class="col-12 col-sm-6 col-xl-3 mb-4">

            <div class="app-stat-card">

                <p class="app-stat-label">
                    Serviços
                </p>

                <p class="app-stat-value">
                    0
                </p>

            </div>

        </div>

    </div>


    <div class="row">

        <div class="col-12 col-xl-8 mb-4">

            <div class="app-card">

                <div
                    class="app-card-header d-flex justify-content-between align-items-center"
                >

                    <h2>
                        Próximos agendamentos
                    </h2>

                    <a
                        href="agendamentos.php"
                        class="btn btn-sm btn-outline-primary"
                    >
                        Ver todos
                    </a>

                </div>


                <div class="app-card-body p-0">

                    <div class="app-empty-state">

                        <h3>
                            Nenhum agendamento encontrado
                        </h3>

                        <p>
                            Os próximos atendimentos aparecerão aqui.
                        </p>

                        <a
                            href="agenda.php"
                            class="btn btn-primary"
                        >
                            Abrir agenda
                        </a>

                    </div>

                </div>

            </div>

        </div>


        <div class="col-12 col-xl-4 mb-4">

            <div class="app-card">

                <div class="app-card-header">

                    <h2>
                        Ações rápidas
                    </h2>

                </div>


                <div class="app-card-body">

                    <div class="dashboard-actions">

                        <a
                            href="cadastro-profissional.php"
                            class="btn btn-outline-primary btn-block text-left"
                        >
                            Cadastrar profissional
                        </a>

                        <a
                            href="cadastro-servico.php"
                            class="btn btn-outline-primary btn-block text-left"
                        >
                            Cadastrar serviço
                        </a>

                        <a
                            href="clientes.php"
                            class="btn btn-outline-secondary btn-block text-left"
                        >
                            Ver clientes
                        </a>

                        <a
                            href="agenda.php"
                            class="btn btn-outline-secondary btn-block text-left"
                        >
                            Abrir agenda
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</main>

<?php
require __DIR__ . '/partials/footer.php';
?>