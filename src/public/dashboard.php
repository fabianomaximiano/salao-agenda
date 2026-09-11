<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigirAdministrador();

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();

$stmt = $pdo->prepare(
    "SELECT
        EXISTS(
            SELECT 1
            FROM empresa_identidade_visual iv
            WHERE iv.empresa_id = :empresa_identidade
              AND (
                  iv.logo_arquivo IS NOT NULL
                  OR iv.cor_primaria IS NOT NULL
                  OR iv.cor_secundaria IS NOT NULL
              )
        ) AS identidade_visual,
        EXISTS(
            SELECT 1
            FROM empresa_horarios eh
            WHERE eh.empresa_id = :empresa_horarios
              AND eh.ativo = 1
        ) AS horarios,
        EXISTS(
            SELECT 1
            FROM servicos s
            WHERE s.empresa_id = :empresa_servicos
              AND s.ativo = 1
        ) AS servicos,
        EXISTS(
            SELECT 1
            FROM profissionais p
            WHERE p.empresa_id = :empresa_profissionais
              AND p.ativo = 1
        ) AS profissionais,
        EXISTS(
            SELECT 1
            FROM profissionais p
            INNER JOIN profissional_horarios ph
                ON ph.profissional_id = p.id
               AND ph.ativo = 1
            WHERE p.empresa_id = :empresa_agenda
              AND p.ativo = 1
        ) AS agenda"
);
$stmt->execute([
    ':empresa_identidade' => $empresaId,
    ':empresa_horarios' => $empresaId,
    ':empresa_servicos' => $empresaId,
    ':empresa_profissionais' => $empresaId,
    ':empresa_agenda' => $empresaId,
]);
$estado = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$etapas = [
    ['titulo' => 'Conta criada', 'concluida' => true, 'url' => null],
    ['titulo' => 'E-mail confirmado', 'concluida' => true, 'url' => null],
    ['titulo' => 'Dados básicos da empresa', 'concluida' => true, 'url' => null],
    [
        'titulo' => 'Configure a identidade visual',
        'concluida' => (bool) ($estado['identidade_visual'] ?? false),
        'url' => 'identidade-visual.php',
    ],
    [
        'titulo' => 'Defina o horário de funcionamento',
        'concluida' => (bool) ($estado['horarios'] ?? false),
        'url' => null,
    ],
    [
        'titulo' => 'Cadastre seus serviços',
        'concluida' => (bool) ($estado['servicos'] ?? false),
        'url' => 'cadastro-servico.php',
    ],
    [
        'titulo' => 'Cadastre seus profissionais',
        'concluida' => (bool) ($estado['profissionais'] ?? false),
        'url' => 'cadastro-profissional.php',
    ],
    [
        'titulo' => 'Configure a agenda',
        'concluida' => (bool) ($estado['agenda'] ?? false),
        'url' => 'agenda.php',
    ],
];

$totalEtapas = count($etapas);
$concluidas = count(array_filter($etapas, static fn (array $etapa): bool => $etapa['concluida']));
$percentual = $totalEtapas > 0 ? (int) round(($concluidas / $totalEtapas) * 100) : 0;

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
            <p>Visão geral da operação da sua empresa.</p>
        </div>

        <div class="mt-3 mt-md-0">
            <a href="agenda.php" class="btn btn-primary">Ver agenda</a>
        </div>
    </div>

    <?php if ($percentual < 100): ?>
        <section class="app-card dashboard-onboarding mb-4" aria-labelledby="onboardingTitulo">
            <div class="app-card-body">
                <div class="d-md-flex justify-content-between align-items-start mb-3">
                    <div>
                        <span class="dashboard-onboarding-eyebrow">Configuração inicial</span>
                        <h2 id="onboardingTitulo" class="dashboard-onboarding-title">
                            Configure sua empresa
                        </h2>
                        <p class="text-muted mb-0">
                            Complete as etapas para deixar sua operação pronta para receber agendamentos.
                        </p>
                    </div>

                    <div class="dashboard-onboarding-progress-text mt-3 mt-md-0">
                        <strong><?= $percentual ?>%</strong>
                        <span><?= $concluidas ?> de <?= $totalEtapas ?> etapas</span>
                    </div>
                </div>

                <div class="progress dashboard-progress mb-4" aria-label="Progresso da configuração">
                    <div
                        class="progress-bar"
                        role="progressbar"
                        style="width: <?= $percentual ?>%"
                        aria-valuenow="<?= $percentual ?>"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    ></div>
                </div>

                <div class="dashboard-onboarding-list">
                    <?php foreach ($etapas as $etapa): ?>
                        <?php
                        $classe = $etapa['concluida'] ? 'is-complete' : 'is-pending';
                        $conteudo = $etapa['concluida'] ? '✓' : '○';
                        ?>

                        <?php if (is_string($etapa['url']) && $etapa['url'] !== ''): ?>
                            <a
                                href="<?= htmlspecialchars($etapa['url'], ENT_QUOTES, 'UTF-8') ?>"
                                class="dashboard-onboarding-item <?= $classe ?>"
                            >
                                <span class="dashboard-onboarding-status" aria-hidden="true">
                                    <?= $conteudo ?>
                                </span>
                                <span><?= htmlspecialchars($etapa['titulo'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="dashboard-onboarding-arrow" aria-hidden="true">›</span>
                            </a>
                        <?php else: ?>
                            <div class="dashboard-onboarding-item <?= $classe ?> is-static">
                                <span class="dashboard-onboarding-status" aria-hidden="true">
                                    <?= $conteudo ?>
                                </span>
                                <span><?= htmlspecialchars($etapa['titulo'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div class="dashboard-onboarding-optional mt-4">
                    <strong>Opcional</strong>
                    <span>○ Conecte o Google Calendar</span>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <div class="row">
        <div class="col-12 col-sm-6 col-xl-3 mb-4">
            <div class="app-stat-card">
                <p class="app-stat-label">Agendamentos hoje</p>
                <p class="app-stat-value">0</p>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3 mb-4">
            <div class="app-stat-card">
                <p class="app-stat-label">Clientes</p>
                <p class="app-stat-value">0</p>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3 mb-4">
            <div class="app-stat-card">
                <p class="app-stat-label">Profissionais</p>
                <p class="app-stat-value">0</p>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3 mb-4">
            <div class="app-stat-card">
                <p class="app-stat-label">Serviços</p>
                <p class="app-stat-value">0</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-xl-8 mb-4">
            <div class="app-card">
                <div class="app-card-header d-flex justify-content-between align-items-center">
                    <h2>Próximos agendamentos</h2>
                    <a href="agendamentos.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
                </div>

                <div class="app-card-body p-0">
                    <div class="app-empty-state">
                        <h3>Nenhum agendamento encontrado</h3>
                        <p>Os próximos atendimentos aparecerão aqui.</p>
                        <a href="agenda.php" class="btn btn-primary">Abrir agenda</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4 mb-4">
            <div class="app-card">
                <div class="app-card-header">
                    <h2>Ações rápidas</h2>
                </div>

                <div class="app-card-body">
                    <div class="dashboard-actions">
                        <a href="cadastro-profissional.php" class="btn btn-outline-primary btn-block text-left">
                            Cadastrar profissional
                        </a>
                        <a href="cadastro-servico.php" class="btn btn-outline-primary btn-block text-left">
                            Cadastrar serviço
                        </a>
                        <a href="clientes.php" class="btn btn-outline-secondary btn-block text-left">
                            Ver clientes
                        </a>
                        <a href="agenda.php" class="btn btn-outline-secondary btn-block text-left">
                            Abrir agenda
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
