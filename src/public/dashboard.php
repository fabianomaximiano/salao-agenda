<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigirLogin();

$contextoAtual = (string) ($_SESSION['contexto'] ?? '');

if (!in_array($contextoAtual, ['administrador', 'colaborador'], true)) {
    http_response_code(403);
    exit('Acesso negado.');
}

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();

$flashSuccess = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);

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

$timezoneEmpresa = 'America/Sao_Paulo';

$stmtTimezone = $pdo->prepare(
    'SELECT timezone
     FROM empresas
     WHERE id = :empresa_id
     LIMIT 1'
);
$stmtTimezone->execute([':empresa_id' => $empresaId]);

$timezoneBanco = $stmtTimezone->fetchColumn();

if (is_string($timezoneBanco) && $timezoneBanco !== '') {
    $timezoneEmpresa = $timezoneBanco;
}

try {
    $timezone = new DateTimeZone($timezoneEmpresa);
} catch (Throwable $e) {
    $timezone = new DateTimeZone('America/Sao_Paulo');
}

$inicioHoje = new DateTimeImmutable('today', $timezone);
$fimHoje = $inicioHoje->modify('+1 day');

$stmtEstatisticas = $pdo->prepare(
    "SELECT
        (
            SELECT COUNT(*)
            FROM agendamentos a
            WHERE a.empresa_id = :empresa_agendamentos
              AND a.inicio >= :inicio_hoje
              AND a.inicio < :fim_hoje
              AND a.status <> 'cancelado'
        ) AS agendamentos_hoje,
        (
            SELECT COUNT(*)
            FROM clientes c
            WHERE c.empresa_id = :empresa_clientes
              AND c.ativo = 1
        ) AS clientes,
        (
            SELECT COUNT(*)
            FROM profissionais p
            WHERE p.empresa_id = :empresa_profissionais_stats
              AND p.ativo = 1
        ) AS profissionais,
        (
            SELECT COUNT(*)
            FROM servicos s
            WHERE s.empresa_id = :empresa_servicos_stats
              AND s.ativo = 1
        ) AS servicos"
);

$stmtEstatisticas->execute([
    ':empresa_agendamentos' => $empresaId,
    ':inicio_hoje' => $inicioHoje->format('Y-m-d H:i:s'),
    ':fim_hoje' => $fimHoje->format('Y-m-d H:i:s'),
    ':empresa_clientes' => $empresaId,
    ':empresa_profissionais_stats' => $empresaId,
    ':empresa_servicos_stats' => $empresaId,
]);

$estatisticas = $stmtEstatisticas->fetch(PDO::FETCH_ASSOC) ?: [];

$totalAgendamentosHoje = (int) ($estatisticas['agendamentos_hoje'] ?? 0);
$totalClientes = (int) ($estatisticas['clientes'] ?? 0);
$totalProfissionais = (int) ($estatisticas['profissionais'] ?? 0);
$totalServicos = (int) ($estatisticas['servicos'] ?? 0);

$agora = new DateTimeImmutable('now', $timezone);

$stmtOperacaoHoje = $pdo->prepare(
    "SELECT
        SUM(CASE WHEN ags.status IN ('agendado', 'confirmado') THEN 1 ELSE 0 END) AS aguardando,
        SUM(CASE WHEN ags.status = 'em_atendimento' THEN 1 ELSE 0 END) AS em_atendimento,
        SUM(CASE WHEN ags.status = 'concluido' THEN 1 ELSE 0 END) AS concluidos,
        SUM(CASE WHEN ags.status = 'cancelado' THEN 1 ELSE 0 END) AS cancelados,
        SUM(CASE WHEN ags.status = 'nao_compareceu' THEN 1 ELSE 0 END) AS faltas
     FROM agendamento_servicos ags
     INNER JOIN agendamentos a
       ON a.id = ags.agendamento_id
      AND a.empresa_id = :empresa_id
     WHERE ags.inicio >= :inicio_hoje
       AND ags.inicio < :fim_hoje"
);
$stmtOperacaoHoje->execute([
    ':empresa_id' => $empresaId,
    ':inicio_hoje' => $inicioHoje->format('Y-m-d H:i:s'),
    ':fim_hoje' => $fimHoje->format('Y-m-d H:i:s'),
]);
$operacaoHoje = $stmtOperacaoHoje->fetch(PDO::FETCH_ASSOC) ?: [];

$aguardandoHoje = (int) ($operacaoHoje['aguardando'] ?? 0);
$emAtendimentoHoje = (int) ($operacaoHoje['em_atendimento'] ?? 0);
$concluidosHoje = (int) ($operacaoHoje['concluidos'] ?? 0);
$canceladosHoje = (int) ($operacaoHoje['cancelados'] ?? 0);
$faltasHoje = (int) ($operacaoHoje['faltas'] ?? 0);

$stmtProximos = $pdo->prepare(
    "SELECT
        ags.inicio,
        ags.fim,
        ags.status,
        s.nome AS servico,
        pc.nome_completo AS cliente,
        pp.nome_completo AS profissional
     FROM agendamento_servicos ags
     INNER JOIN agendamentos a
       ON a.id = ags.agendamento_id
      AND a.empresa_id = :empresa_id
     INNER JOIN servicos s
       ON s.id = ags.servico_id
      AND s.empresa_id = a.empresa_id
     INNER JOIN profissionais pr
       ON pr.id = ags.profissional_id
      AND pr.empresa_id = a.empresa_id
     INNER JOIN pessoas pp
       ON pp.id = pr.pessoa_id
      AND pp.empresa_id = a.empresa_id
     INNER JOIN clientes c
       ON c.id = a.cliente_id
      AND c.empresa_id = a.empresa_id
     INNER JOIN pessoas pc
       ON pc.id = c.pessoa_id
      AND pc.empresa_id = a.empresa_id
     WHERE ags.inicio >= :agora
       AND ags.status NOT IN ('cancelado', 'concluido', 'nao_compareceu')
     ORDER BY ags.inicio ASC
     LIMIT 6"
);
$stmtProximos->execute([
    ':empresa_id' => $empresaId,
    ':agora' => $agora->format('Y-m-d H:i:s'),
]);
$proximosAgendamentos = $stmtProximos->fetchAll(PDO::FETCH_ASSOC);

function rotuloStatusDashboard(string $status): string
{
    return match ($status) {
        'agendado' => 'Agendado',
        'confirmado' => 'Confirmado',
        'em_atendimento' => 'Em atendimento',
        'concluido' => 'Concluído',
        'cancelado' => 'Cancelado',
        'nao_compareceu' => 'Não compareceu',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
}

function classeStatusDashboard(string $status): string
{
    return match ($status) {
        'confirmado' => 'success',
        'em_atendimento' => 'primary',
        'concluido' => 'secondary',
        'cancelado', 'nao_compareceu' => 'danger',
        default => 'warning',
    };
}


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
        'url' => 'horario-funcionamento.php',
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
        'titulo' => 'Defina os horários dos profissionais',
        'concluida' => (bool) ($estado['agenda'] ?? false),
        'url' => 'horarios-profissionais.php',
    ],
];

$totalEtapas = count($etapas);
$concluidas = count(array_filter($etapas, static fn (array $etapa): bool => $etapa['concluida']));
$percentual = $totalEtapas > 0 ? (int) round(($concluidas / $totalEtapas) * 100) : 0;

$pageTitle = 'Dashboard';
$pageCss = 'dashboard.css?v=20260923-1';
$pageJs = 'dashboard.js';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/partials/navbar.php';
?>

<main class="app-content">
    <?php if ($flashSuccess !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="app-page-header d-md-flex justify-content-between align-items-center">
        <div>
            <h1>Dashboard</h1>
            <p>Visão geral da operação da sua empresa.</p>
        </div>

        <?php if ($contextoAtual === 'administrador' || colaboradorPode('agenda')): ?>
            <div class="mt-3 mt-md-0">
                <a href="agenda.php" class="btn btn-primary">Ver agenda</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($contextoAtual === 'administrador' && $percentual < 100): ?>
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

                        <div class="dashboard-onboarding-row">
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
                        </div>
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
        <?php if ($contextoAtual === 'administrador' || colaboradorPode('agenda')): ?>
            <div class="col-12 col-sm-6 col-xl-3 mb-4">
                <div class="app-stat-card">
                    <p class="app-stat-label">Agendamentos hoje</p>
                    <p class="app-stat-value"><?= $totalAgendamentosHoje ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($contextoAtual === 'administrador' || colaboradorPode('clientes')): ?>
            <div class="col-12 col-sm-6 col-xl-3 mb-4">
                <div class="app-stat-card">
                    <p class="app-stat-label">Clientes</p>
                    <p class="app-stat-value"><?= $totalClientes ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($contextoAtual === 'administrador' || colaboradorPode('profissionais')): ?>
            <div class="col-12 col-sm-6 col-xl-3 mb-4">
                <div class="app-stat-card">
                    <p class="app-stat-label">Profissionais</p>
                    <p class="app-stat-value"><?= $totalProfissionais ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($contextoAtual === 'administrador' || colaboradorPode('servicos')): ?>
            <div class="col-12 col-sm-6 col-xl-3 mb-4">
                <div class="app-stat-card">
                    <p class="app-stat-label">Serviços</p>
                    <p class="app-stat-value"><?= $totalServicos ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($contextoAtual === 'administrador' || colaboradorPode('agenda')): ?>
        <section class="app-card dashboard-operation mb-4">
            <div class="app-card-header">
                <h2>Operação de hoje</h2>
            </div>
            <div class="app-card-body">
                <div class="dashboard-operation-grid">
                    <div><strong><?= $aguardandoHoje ?></strong><span>Aguardando</span></div>
                    <div><strong><?= $emAtendimentoHoje ?></strong><span>Em atendimento</span></div>
                    <div><strong><?= $concluidosHoje ?></strong><span>Concluídos</span></div>
                    <div><strong><?= $canceladosHoje ?></strong><span>Cancelados</span></div>
                    <div><strong><?= $faltasHoje ?></strong><span>Não compareceram</span></div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <div class="row">
        <?php if ($contextoAtual === 'administrador' || colaboradorPode('agenda')): ?>
        <div class="col-12 col-xl-8 mb-4">
            <div class="app-card">
                <div class="app-card-header d-flex justify-content-between align-items-center">
                    <h2>Próximos agendamentos</h2>
                    <a href="agendamentos.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
                </div>

                <div class="app-card-body p-0">
                    <?php if (!$proximosAgendamentos): ?>
                        <div class="app-empty-state">
                            <h3>Nenhum agendamento futuro</h3>
                            <p>Os próximos atendimentos aparecerão aqui.</p>
                            <a href="agenda.php" class="btn btn-primary">Abrir agenda</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table dashboard-appointments-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Horário</th>
                                        <th>Cliente</th>
                                        <th>Serviço</th>
                                        <th>Profissional</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($proximosAgendamentos as $item): ?>
                                        <?php
                                        $inicioItem = new DateTimeImmutable((string) $item['inicio'], $timezone);
                                        $fimItem = new DateTimeImmutable((string) $item['fim'], $timezone);
                                        $statusItem = (string) $item['status'];
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($inicioItem->format('d/m'), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($inicioItem->format('H:i'), ENT_QUOTES, 'UTF-8') ?></strong>
                                                <span class="text-muted">– <?= htmlspecialchars($fimItem->format('H:i'), ENT_QUOTES, 'UTF-8') ?></span>
                                            </td>
                                            <td><?= htmlspecialchars((string) $item['cliente'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) $item['servico'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) $item['profissional'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <span class="badge badge-<?= classeStatusDashboard($statusItem) ?>">
                                                    <?= htmlspecialchars(rotuloStatusDashboard($statusItem), ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="col-12 col-xl-4 mb-4">
            <div class="app-card">
                <div class="app-card-header">
                    <h2>Ações rápidas</h2>
                </div>

                <div class="app-card-body">
                    <div class="dashboard-actions">
                        <?php if ($contextoAtual === 'administrador' || colaboradorPode('profissionais')): ?>
                            <a href="cadastro-profissional.php" class="btn btn-outline-primary btn-block text-left">
                                Cadastrar profissional
                            </a>
                        <?php endif; ?>

                        <?php if ($contextoAtual === 'administrador' || colaboradorPode('servicos')): ?>
                            <a href="cadastro-servico.php" class="btn btn-outline-primary btn-block text-left">
                                Cadastrar serviço
                            </a>
                        <?php endif; ?>

                        <?php if ($contextoAtual === 'administrador' || colaboradorPode('clientes')): ?>
                            <a href="clientes.php" class="btn btn-outline-secondary btn-block text-left">
                                Ver clientes
                            </a>
                        <?php endif; ?>

                        <?php if ($contextoAtual === 'administrador' || colaboradorPode('agenda')): ?>
                            <a href="agenda.php" class="btn btn-outline-secondary btn-block text-left">
                                Abrir agenda
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
