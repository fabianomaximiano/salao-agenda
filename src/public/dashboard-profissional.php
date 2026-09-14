<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../services/ImagemProfissionalService.php';

exigirProfissional();

$empresaId = (int) $_SESSION['empresa_id'];
$profissionalId = (int) $_SESSION['profissional_id'];
$pdo = getDB();

$timezoneEmpresa = 'America/Sao_Paulo';

$stmtPerfil = $pdo->prepare(
    'SELECT
        pr.cargo,
        pr.foto_url,
        pe.nome_completo
     FROM profissionais pr
     INNER JOIN pessoas pe
       ON pe.id = pr.pessoa_id
      AND pe.empresa_id = pr.empresa_id
     WHERE pr.id = :profissional_id
       AND pr.empresa_id = :empresa_id
       AND pr.ativo = 1
     LIMIT 1'
);
$stmtPerfil->execute([
    ':profissional_id' => $profissionalId,
    ':empresa_id' => $empresaId,
]);
$perfil = $stmtPerfil->fetch(PDO::FETCH_ASSOC);

if (!$perfil) {
    http_response_code(403);
    exit('Acesso negado.');
}

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

$agora = new DateTimeImmutable('now', $timezone);
$inicioHoje = new DateTimeImmutable('today', $timezone);
$fimHoje = $inicioHoje->modify('+1 day');

$stmtResumo = $pdo->prepare(
    "SELECT
        COUNT(*) AS atendimentos_hoje,
        SUM(CASE WHEN ags.status = 'concluido' THEN 1 ELSE 0 END) AS concluidos_hoje,
        SUM(CASE WHEN ags.status = 'cancelado' THEN 1 ELSE 0 END) AS cancelados_hoje,
        SUM(CASE WHEN ags.status = 'nao_compareceu' THEN 1 ELSE 0 END) AS faltas_hoje
     FROM agendamento_servicos ags
     INNER JOIN agendamentos a
       ON a.id = ags.agendamento_id
      AND a.empresa_id = :empresa_id
     WHERE ags.profissional_id = :profissional_id
       AND ags.inicio >= :inicio_hoje
       AND ags.inicio < :fim_hoje"
);
$stmtResumo->execute([
    ':empresa_id' => $empresaId,
    ':profissional_id' => $profissionalId,
    ':inicio_hoje' => $inicioHoje->format('Y-m-d H:i:s'),
    ':fim_hoje' => $fimHoje->format('Y-m-d H:i:s'),
]);
$resumo = $stmtResumo->fetch(PDO::FETCH_ASSOC) ?: [];

$totalHoje = (int) ($resumo['atendimentos_hoje'] ?? 0);
$concluidosHoje = (int) ($resumo['concluidos_hoje'] ?? 0);
$canceladosHoje = (int) ($resumo['cancelados_hoje'] ?? 0);
$faltasHoje = (int) ($resumo['faltas_hoje'] ?? 0);
$pendentesHoje = max(0, $totalHoje - $concluidosHoje - $canceladosHoje - $faltasHoje);

$stmtProximo = $pdo->prepare(
    "SELECT
        ags.inicio,
        ags.fim,
        ags.status,
        ags.duracao_minutos,
        s.nome AS servico,
        p.nome_completo AS cliente
     FROM agendamento_servicos ags
     INNER JOIN agendamentos a
       ON a.id = ags.agendamento_id
      AND a.empresa_id = :empresa_id
     INNER JOIN servicos s
       ON s.id = ags.servico_id
      AND s.empresa_id = a.empresa_id
     INNER JOIN clientes c
       ON c.id = a.cliente_id
      AND c.empresa_id = a.empresa_id
     INNER JOIN pessoas p
       ON p.id = c.pessoa_id
      AND p.empresa_id = a.empresa_id
     WHERE ags.profissional_id = :profissional_id
       AND ags.inicio >= :agora
       AND ags.status NOT IN ('cancelado', 'concluido', 'nao_compareceu')
     ORDER BY ags.inicio ASC
     LIMIT 1"
);
$stmtProximo->execute([
    ':empresa_id' => $empresaId,
    ':profissional_id' => $profissionalId,
    ':agora' => $agora->format('Y-m-d H:i:s'),
]);
$proximo = $stmtProximo->fetch(PDO::FETCH_ASSOC) ?: null;

$stmtLista = $pdo->prepare(
    "SELECT
        ags.id,
        ags.inicio,
        ags.fim,
        ags.status,
        ags.duracao_minutos,
        s.nome AS servico,
        p.nome_completo AS cliente
     FROM agendamento_servicos ags
     INNER JOIN agendamentos a
       ON a.id = ags.agendamento_id
      AND a.empresa_id = :empresa_id
     INNER JOIN servicos s
       ON s.id = ags.servico_id
      AND s.empresa_id = a.empresa_id
     INNER JOIN clientes c
       ON c.id = a.cliente_id
      AND c.empresa_id = a.empresa_id
     INNER JOIN pessoas p
       ON p.id = c.pessoa_id
      AND p.empresa_id = a.empresa_id
     WHERE ags.profissional_id = :profissional_id
       AND ags.inicio >= :agora
       AND ags.status NOT IN ('cancelado', 'concluido', 'nao_compareceu')
     ORDER BY ags.inicio ASC
     LIMIT 5"
);
$stmtLista->execute([
    ':empresa_id' => $empresaId,
    ':profissional_id' => $profissionalId,
    ':agora' => $agora->format('Y-m-d H:i:s'),
]);
$proximos = $stmtLista->fetchAll(PDO::FETCH_ASSOC);

$stmtHorarios = $pdo->prepare(
    'SELECT dia_semana, hora_inicio, hora_fim
     FROM profissional_horarios
     WHERE profissional_id = :profissional_id
       AND ativo = 1
     ORDER BY dia_semana, hora_inicio'
);
$stmtHorarios->execute([':profissional_id' => $profissionalId]);
$horariosSemana = $stmtHorarios->fetchAll(PDO::FETCH_ASSOC);

function rotuloStatusProfissional(string $status): string
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

function classeStatusProfissional(string $status): string
{
    return match ($status) {
        'confirmado' => 'success',
        'em_atendimento' => 'primary',
        'concluido' => 'secondary',
        'cancelado', 'nao_compareceu' => 'danger',
        default => 'warning',
    };
}

$nome = trim((string) $perfil['nome_completo']);
$primeiroNome = $nome !== '' ? ((preg_split('/\s+/u', $nome) ?: ['Profissional'])[0]) : 'Profissional';
$cargo = trim((string) ($perfil['cargo'] ?? ''));
$fotoAtual = is_string($perfil['foto_url'] ?? null) ? $perfil['foto_url'] : '';
$fotoUrls = ImagemProfissionalService::urls($fotoAtual);
$iniciais = ImagemProfissionalService::iniciais($nome);

$diasSemana = [
    1 => 'Seg',
    2 => 'Ter',
    3 => 'Qua',
    4 => 'Qui',
    5 => 'Sex',
    6 => 'Sáb',
    7 => 'Dom',
];

$horariosAgrupados = [];

foreach ($horariosSemana as $horario) {
    $dia = (int) $horario['dia_semana'];
    $horariosAgrupados[$dia][] =
        substr((string) $horario['hora_inicio'], 0, 5)
        . '–'
        . substr((string) $horario['hora_fim'], 0, 5);
}

$dataExtenso = $agora->format('d/m/Y');

$pageTitle = 'Dashboard profissional';
$pageCss = 'dashboard-profissional.css?v=20260914-1';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/partials/navbar.php';
?>

<main class="app-content dashboard-profissional">
    <section class="prof-dashboard-hero mb-4">
        <div class="prof-dashboard-profile">
            <div class="prof-dashboard-photo">
                <?php if (!empty($fotoUrls['m'])): ?>
                    <img
                        src="<?= htmlspecialchars((string) $fotoUrls['m'], ENT_QUOTES, 'UTF-8') ?>"
                        srcset="<?= htmlspecialchars((string) $fotoUrls['p'], ENT_QUOTES, 'UTF-8') ?> 160w, <?= htmlspecialchars((string) $fotoUrls['m'], ENT_QUOTES, 'UTF-8') ?> 320w, <?= htmlspecialchars((string) $fotoUrls['g'], ENT_QUOTES, 'UTF-8') ?> 500w"
                        sizes="112px"
                        alt="Foto de <?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>"
                        decoding="async"
                    >
                <?php else: ?>
                    <span><?= htmlspecialchars($iniciais, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>

            <div class="prof-dashboard-intro">
                <span class="prof-dashboard-eyebrow">Área profissional</span>
                <h1>Olá, <?= htmlspecialchars($primeiroNome, ENT_QUOTES, 'UTF-8') ?>!</h1>

                <?php if ($cargo !== ''): ?>
                    <p class="prof-dashboard-role">
                        <?= htmlspecialchars($cargo, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endif; ?>

                <p class="prof-dashboard-copy">
                    Acompanhe seu dia e seus próximos atendimentos.
                </p>

                <div class="prof-dashboard-meta">
                    <span><?= htmlspecialchars($dataExtenso, ENT_QUOTES, 'UTF-8') ?></span>
                    <span aria-hidden="true">•</span>
                    <span><?= htmlspecialchars((string) ($_SESSION['empresa_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
        </div>

        <div class="prof-dashboard-hero-action">
            <a href="agenda.php" class="btn btn-primary">
                Abrir minha agenda
            </a>
        </div>
    </section>

    <section class="row prof-dashboard-stats">
        <div class="col-12 col-md-4 mb-4">
            <article class="prof-stat-card prof-stat-card--today">
                <div>
                    <span class="prof-stat-label">Atendimentos hoje</span>
                    <strong class="prof-stat-value"><?= $totalHoje ?></strong>
                    <small><?= $pendentesHoje ?> ainda no fluxo do dia</small>
                </div>
                <div class="prof-stat-symbol" aria-hidden="true">01</div>
            </article>
        </div>

        <div class="col-12 col-md-4 mb-4">
            <article class="prof-stat-card prof-stat-card--next">
                <div>
                    <span class="prof-stat-label">Próximo atendimento</span>

                    <?php if ($proximo): ?>
                        <?php $inicioProximo = new DateTimeImmutable((string) $proximo['inicio'], $timezone); ?>
                        <strong class="prof-stat-value"><?= htmlspecialchars($inicioProximo->format('H:i'), ENT_QUOTES, 'UTF-8') ?></strong>
                        <small>
                            <?= htmlspecialchars((string) $proximo['cliente'], ENT_QUOTES, 'UTF-8') ?>
                            ·
                            <?= htmlspecialchars((string) $proximo['servico'], ENT_QUOTES, 'UTF-8') ?>
                        </small>
                    <?php else: ?>
                        <strong class="prof-stat-value">—</strong>
                        <small>Nenhum atendimento futuro.</small>
                    <?php endif; ?>
                </div>
                <div class="prof-stat-symbol" aria-hidden="true">02</div>
            </article>
        </div>

        <div class="col-12 col-md-4 mb-4">
            <article class="prof-stat-card prof-stat-card--done">
                <div>
                    <span class="prof-stat-label">Concluídos hoje</span>
                    <strong class="prof-stat-value"><?= $concluidosHoje ?></strong>
                    <small>Atendimentos finalizados</small>
                </div>
                <div class="prof-stat-symbol" aria-hidden="true">03</div>
            </article>
        </div>
    </section>

    <section class="app-card prof-dashboard-appointments mb-4">
        <div class="app-card-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-center">
            <div>
                <span class="prof-dashboard-eyebrow">Agenda</span>
                <h2>Próximos atendimentos</h2>
            </div>

            <a href="agenda.php" class="btn btn-sm btn-outline-primary mt-2 mt-sm-0">
                Ver minha agenda
            </a>
        </div>

        <div class="app-card-body p-0">
            <?php if (!$proximos): ?>
                <div class="prof-dashboard-empty">
                    <div class="prof-dashboard-empty-mark" aria-hidden="true">✓</div>
                    <h3>Sua agenda está tranquila por enquanto</h3>
                    <p>Quando houver novos atendimentos, eles aparecerão aqui automaticamente.</p>
                    <a href="agenda.php" class="btn btn-outline-primary btn-sm">Consultar calendário</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table prof-dashboard-table mb-0">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Horário</th>
                                <th>Cliente</th>
                                <th>Serviço</th>
                                <th>Duração</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($proximos as $item): ?>
                                <?php
                                $inicio = new DateTimeImmutable((string) $item['inicio'], $timezone);
                                $fim = new DateTimeImmutable((string) $item['fim'], $timezone);
                                $status = (string) $item['status'];
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($inicio->format('d/m'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($inicio->format('H:i'), ENT_QUOTES, 'UTF-8') ?></strong>
                                        <span class="text-muted">– <?= htmlspecialchars($fim->format('H:i'), ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td><?= htmlspecialchars((string) $item['cliente'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) $item['servico'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= (int) $item['duracao_minutos'] ?> min</td>
                                    <td>
                                        <span class="badge badge-<?= classeStatusProfissional($status) ?>">
                                            <?= htmlspecialchars(rotuloStatusProfissional($status), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="row">
        <div class="col-12 col-xl-7 mb-4">
            <article class="app-card prof-dashboard-summary h-100">
                <div class="app-card-header">
                    <span class="prof-dashboard-eyebrow">Resumo</span>
                    <h2>Meu dia de hoje</h2>
                </div>

                <div class="app-card-body">
                    <div class="prof-summary-grid">
                        <div>
                            <strong><?= $totalHoje ?></strong>
                            <span>Total</span>
                        </div>
                        <div>
                            <strong><?= $concluidosHoje ?></strong>
                            <span>Concluídos</span>
                        </div>
                        <div>
                            <strong><?= $canceladosHoje ?></strong>
                            <span>Cancelados</span>
                        </div>
                        <div>
                            <strong><?= $faltasHoje ?></strong>
                            <span>Não compareceram</span>
                        </div>
                    </div>
                </div>
            </article>
        </div>

        <div class="col-12 col-xl-5 mb-4">
            <article class="app-card prof-dashboard-hours h-100">
                <div class="app-card-header d-flex justify-content-between align-items-start">
                    <div>
                        <span class="prof-dashboard-eyebrow">Disponibilidade</span>
                        <h2>Meus horários</h2>
                    </div>

                    <a href="horarios-profissionais.php" class="btn btn-sm btn-outline-primary">
                        Ver horários
                    </a>
                </div>

                <div class="app-card-body">
                    <?php if ($horariosAgrupados === []): ?>
                        <p class="text-muted mb-0">
                            Nenhum horário semanal ativo foi configurado para você.
                        </p>
                    <?php else: ?>
                        <div class="prof-hours-list">
                            <?php foreach ($diasSemana as $dia => $nomeDia): ?>
                                <?php if (empty($horariosAgrupados[$dia])) {
                                    continue;
                                } ?>
                                <div class="prof-hours-row">
                                    <strong><?= htmlspecialchars($nomeDia, ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span><?= htmlspecialchars(implode(' / ', $horariosAgrupados[$dia]), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        </div>
    </section>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
