<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigirLogin();

$contextoAtual = (string) ($_SESSION['contexto'] ?? '');
$ehAdministrador = $contextoAtual === 'administrador';
$ehProfissional = $contextoAtual === 'profissional';

if (!$ehAdministrador && !$ehProfissional) {
    http_response_code(403);
    exit('Acesso negado.');
}

$empresaId = (int) $_SESSION['empresa_id'];
$profissionalSessaoId = $ehProfissional ? (int) ($_SESSION['profissional_id'] ?? 0) : 0;

if ($ehProfissional && $profissionalSessaoId <= 0) {
    http_response_code(403);
    exit('Acesso negado.');
}

$pdo = getDB();
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

$hoje = new DateTimeImmutable('today', $timezone);
$mesParam = trim((string) ($_GET['mes'] ?? ''));

if (!preg_match('/^\d{4}-\d{2}$/', $mesParam)) {
    $mesParam = $hoje->format('Y-m');
}

try {
    $inicioMes = new DateTimeImmutable($mesParam . '-01 00:00:00', $timezone);
} catch (Throwable $e) {
    $inicioMes = new DateTimeImmutable($hoje->format('Y-m-01') . ' 00:00:00', $timezone);
}

$mesParam = $inicioMes->format('Y-m');
$mesAnterior = $inicioMes->modify('-1 month')->format('Y-m');
$proximoMes = $inicioMes->modify('+1 month')->format('Y-m');

if ($ehProfissional) {
    $profissionalSelecionado = $profissionalSessaoId;
} else {
    $profissionalSelecionado = filter_input(INPUT_GET, 'profissional', FILTER_VALIDATE_INT);
    $profissionalSelecionado = is_int($profissionalSelecionado) && $profissionalSelecionado > 0
        ? $profissionalSelecionado
        : 0;
}

$servicoSelecionado = filter_input(INPUT_GET, 'servico', FILTER_VALIDATE_INT);
$servicoSelecionado = is_int($servicoSelecionado) && $servicoSelecionado > 0
    ? $servicoSelecionado
    : 0;

$stmtHorarios = $pdo->prepare(
    'SELECT dia_semana, hora_inicio, hora_fim
     FROM empresa_horarios
     WHERE empresa_id = :empresa_id
       AND ativo = 1
     ORDER BY dia_semana, hora_inicio'
);
$stmtHorarios->execute([':empresa_id' => $empresaId]);

$horariosEmpresa = [];

foreach ($stmtHorarios->fetchAll(PDO::FETCH_ASSOC) as $horario) {
    $dia = (int) $horario['dia_semana'];

    $horariosEmpresa[$dia][] = [
        'inicio' => substr((string) $horario['hora_inicio'], 0, 5),
        'fim' => substr((string) $horario['hora_fim'], 0, 5),
    ];
}

if ($ehProfissional) {
    $stmtProfissionais = $pdo->prepare(
        'SELECT p.id, pe.nome_completo
         FROM profissionais p
         INNER JOIN pessoas pe
            ON pe.id = p.pessoa_id
           AND pe.empresa_id = p.empresa_id
         WHERE p.empresa_id = :empresa_id
           AND p.id = :profissional_id
           AND p.ativo = 1
         LIMIT 1'
    );
    $stmtProfissionais->execute([
        ':empresa_id' => $empresaId,
        ':profissional_id' => $profissionalSessaoId,
    ]);
} else {
    $stmtProfissionais = $pdo->prepare(
        'SELECT p.id, pe.nome_completo
         FROM profissionais p
         INNER JOIN pessoas pe
            ON pe.id = p.pessoa_id
           AND pe.empresa_id = p.empresa_id
         WHERE p.empresa_id = :empresa_id
           AND p.ativo = 1
         ORDER BY pe.nome_completo'
    );
    $stmtProfissionais->execute([':empresa_id' => $empresaId]);
}


$fimMes = $inicioMes->modify('last day of this month');
$stmtExcecoes = $pdo->prepare(
    'SELECT ee.id, ee.data_excecao, ee.tipo, ee.descricao,
            eep.hora_inicio, eep.hora_fim
     FROM empresa_excecoes ee
     LEFT JOIN empresa_excecao_periodos eep
       ON eep.empresa_excecao_id = ee.id
     WHERE ee.empresa_id = :empresa_id
       AND ee.ativo = 1
       AND ee.data_excecao BETWEEN :inicio_mes AND :fim_mes
     ORDER BY ee.data_excecao, eep.hora_inicio'
);
$stmtExcecoes->execute([
    ':empresa_id' => $empresaId,
    ':inicio_mes' => $inicioMes->format('Y-m-d'),
    ':fim_mes' => $fimMes->format('Y-m-d'),
]);
$excecoesEmpresa = [];
foreach ($stmtExcecoes->fetchAll(PDO::FETCH_ASSOC) as $excecao) {
    $chave = (string) $excecao['data_excecao'];
    if (!isset($excecoesEmpresa[$chave])) {
        $excecoesEmpresa[$chave] = [
            'tipo' => (string) $excecao['tipo'],
            'descricao' => (string) ($excecao['descricao'] ?? ''),
            'periodos' => [],
        ];
    }
    if ($excecao['hora_inicio'] !== null && $excecao['hora_fim'] !== null) {
        $excecoesEmpresa[$chave]['periodos'][] = [
            'inicio' => substr((string) $excecao['hora_inicio'], 0, 5),
            'fim' => substr((string) $excecao['hora_fim'], 0, 5),
        ];
    }
}

$profissionais = $stmtProfissionais->fetchAll(PDO::FETCH_ASSOC);

if ($ehProfissional && $profissionais === []) {
    http_response_code(403);
    exit('Acesso negado.');
}

$idsProfissionais = array_map(
    static fn (array $item): int => (int) $item['id'],
    $profissionais
);

if ($profissionalSelecionado > 0 && !in_array($profissionalSelecionado, $idsProfissionais, true)) {
    $profissionalSelecionado = $ehProfissional ? $profissionalSessaoId : 0;
}

if ($ehProfissional) {
    $stmtServicos = $pdo->prepare(
        'SELECT s.id, s.nome
         FROM profissional_servicos ps
         INNER JOIN servicos s
            ON s.id = ps.servico_id
           AND s.empresa_id = :empresa_id
           AND s.ativo = 1
         WHERE ps.profissional_id = :profissional_id
           AND ps.ativo = 1
         ORDER BY s.nome'
    );
    $stmtServicos->execute([
        ':empresa_id' => $empresaId,
        ':profissional_id' => $profissionalSessaoId,
    ]);
} else {
    $stmtServicos = $pdo->prepare(
        'SELECT id, nome
         FROM servicos
         WHERE empresa_id = :empresa_id
           AND ativo = 1
         ORDER BY nome'
    );
    $stmtServicos->execute([':empresa_id' => $empresaId]);
}

$servicos = $stmtServicos->fetchAll(PDO::FETCH_ASSOC);

$idsServicos = array_map(
    static fn (array $item): int => (int) $item['id'],
    $servicos
);

if ($servicoSelecionado > 0 && !in_array($servicoSelecionado, $idsServicos, true)) {
    $servicoSelecionado = 0;
}

$profissionaisPorServico = [];
$servicosPorProfissional = [];

if ($profissionais !== [] && $servicos !== []) {
    $stmtVinculos = $pdo->prepare(
        'SELECT ps.profissional_id, ps.servico_id
         FROM profissional_servicos ps
         INNER JOIN profissionais p
            ON p.id = ps.profissional_id
           AND p.empresa_id = :empresa_profissionais
           AND p.ativo = 1
         INNER JOIN servicos s
            ON s.id = ps.servico_id
           AND s.empresa_id = :empresa_servicos
           AND s.ativo = 1
         WHERE ps.ativo = 1'
    );
    $stmtVinculos->execute([
        ':empresa_profissionais' => $empresaId,
        ':empresa_servicos' => $empresaId,
    ]);

    foreach ($stmtVinculos->fetchAll(PDO::FETCH_ASSOC) as $vinculo) {
        $profissionalId = (int) $vinculo['profissional_id'];
        $servicoId = (int) $vinculo['servico_id'];

        $profissionaisPorServico[$servicoId][$profissionalId] = true;
        $servicosPorProfissional[$profissionalId][$servicoId] = true;
    }
}

$profissionaisDisponiveis = array_values(array_filter(
    $profissionais,
    static function (array $profissional) use ($servicoSelecionado, $profissionaisPorServico): bool {
        if ($servicoSelecionado === 0) {
            return true;
        }

        return isset($profissionaisPorServico[$servicoSelecionado][(int) $profissional['id']]);
    }
));

$servicosDisponiveis = array_values(array_filter(
    $servicos,
    static function (array $servico) use ($profissionalSelecionado, $servicosPorProfissional): bool {
        if ($profissionalSelecionado === 0) {
            return true;
        }

        return isset($servicosPorProfissional[$profissionalSelecionado][(int) $servico['id']]);
    }
));

if (
    $profissionalSelecionado > 0
    && $servicoSelecionado > 0
    && !isset($servicosPorProfissional[$profissionalSelecionado][$servicoSelecionado])
) {
    $servicoSelecionado = 0;
    $servicosDisponiveis = $servicos;
}

$ausenciasAprovadas = [];

if ($profissionalSelecionado > 0) {
    $stmtAusencias = $pdo->prepare(
        "SELECT s.id, s.inicio, s.fim, s.tipo, s.motivo
         FROM profissional_solicitacoes_ausencia s
         INNER JOIN profissionais p
            ON p.id = s.profissional_id
           AND p.empresa_id = s.empresa_id
         WHERE s.empresa_id = :empresa_id
           AND s.profissional_id = :profissional_id
           AND s.status = 'aprovada'
           AND s.inicio < :fim_mes
           AND s.fim >= :inicio_mes
         ORDER BY s.inicio"
    );
    $stmtAusencias->execute([
        ':empresa_id' => $empresaId,
        ':profissional_id' => $profissionalSelecionado,
        ':inicio_mes' => $inicioMes->format('Y-m-d 00:00:00'),
        ':fim_mes' => $fimMes->modify('+1 day')->format('Y-m-d 00:00:00'),
    ]);

    foreach ($stmtAusencias->fetchAll(PDO::FETCH_ASSOC) as $ausencia) {
        $inicioAusencia = new DateTimeImmutable((string) $ausencia['inicio'], $timezone);
        $fimAusencia = new DateTimeImmutable((string) $ausencia['fim'], $timezone);

        $cursor = $inicioAusencia->setTime(0, 0);
        $ultimoDia = $fimAusencia->setTime(0, 0);

        while ($cursor <= $ultimoDia) {
            $dataAusencia = $cursor->format('Y-m-d');

            if ($cursor >= $inicioMes && $cursor <= $fimMes) {
                $ausenciasAprovadas[$dataAusencia][] = [
                    'id' => (int) $ausencia['id'],
                    'inicio' => $inicioAusencia->format('H:i'),
                    'fim' => $fimAusencia->format('H:i'),
                    'motivo' => (string) ($ausencia['motivo'] ?? ''),
                ];
            }

            $cursor = $cursor->modify('+1 day');
        }
    }
}

$horariosProfissional = [];

if ($profissionalSelecionado > 0) {
    $stmtHorarioProfissional = $pdo->prepare(
        'SELECT ph.dia_semana, ph.hora_inicio, ph.hora_fim
         FROM profissional_horarios ph
         INNER JOIN profissionais p
            ON p.id = ph.profissional_id
         WHERE ph.profissional_id = :profissional_id
           AND p.empresa_id = :empresa_id
           AND p.ativo = 1
           AND ph.ativo = 1
         ORDER BY ph.dia_semana, ph.hora_inicio'
    );
    $stmtHorarioProfissional->execute([
        ':profissional_id' => $profissionalSelecionado,
        ':empresa_id' => $empresaId,
    ]);

    foreach ($stmtHorarioProfissional->fetchAll(PDO::FETCH_ASSOC) as $horario) {
        $dia = (int) $horario['dia_semana'];

        $horariosProfissional[$dia][] = [
            'inicio' => substr((string) $horario['hora_inicio'], 0, 5),
            'fim' => substr((string) $horario['hora_fim'], 0, 5),
        ];
    }
}

$nomesDias = [
    1 => 'Seg',
    2 => 'Ter',
    3 => 'Qua',
    4 => 'Qui',
    5 => 'Sex',
    6 => 'Sáb',
    7 => 'Dom',
];

$meses = [
    1 => 'Janeiro',
    2 => 'Fevereiro',
    3 => 'Março',
    4 => 'Abril',
    5 => 'Maio',
    6 => 'Junho',
    7 => 'Julho',
    8 => 'Agosto',
    9 => 'Setembro',
    10 => 'Outubro',
    11 => 'Novembro',
    12 => 'Dezembro',
];

$primeiroDiaSemana = (int) $inicioMes->format('N');
$diasNoMes = (int) $inicioMes->format('t');
$totalCelulas = (int) ceil(($primeiroDiaSemana - 1 + $diasNoMes) / 7) * 7;

function agendaQuery(array $alteracoes): string
{
    global $ehProfissional, $profissionalSessaoId;

    $params = [
        'mes' => (string) ($_GET['mes'] ?? ''),
        'servico' => (string) ($_GET['servico'] ?? ''),
    ];

    if (!$ehProfissional) {
        $params['profissional'] = (string) ($_GET['profissional'] ?? '');
    }

    foreach ($alteracoes as $chave => $valor) {
        if ($chave === 'profissional' && $ehProfissional) {
            continue;
        }

        if ($valor === null || $valor === '' || $valor === 0 || $valor === '0') {
            unset($params[$chave]);
            continue;
        }

        $params[$chave] = (string) $valor;
    }

    $params = array_filter(
        $params,
        static fn (string $valor): bool => $valor !== ''
    );

    return http_build_query($params);
}

if ($ehAdministrador && empty($_SESSION['csrf_agenda_excecao'])) {
    $_SESSION['csrf_agenda_excecao'] = bin2hex(random_bytes(32));
}
$csrfAgendaExcecao = $ehAdministrador ? (string) $_SESSION['csrf_agenda_excecao'] : '';

$pageTitle = $ehProfissional ? 'Minha agenda' : 'Agenda';
$pageCss = 'agenda.css?v=20260915-3';
$pageJs = 'agenda.js?v=20260915-2';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/partials/navbar.php';
?>

<main class="app-content">
    <div class="app-page-header d-lg-flex justify-content-between align-items-start">
        <div>
            <h1><?= $ehProfissional ? 'Minha agenda' : 'Agenda' ?></h1>
            <p>
                <?= $ehProfissional
                    ? 'Visualize seu calendário mensal e filtre pelos serviços que você realiza.'
                    : 'Visão mensal da operação da empresa.' ?>
            </p>
        </div>

        <?php if ($ehAdministrador): ?>
            <div class="agenda-header-actions mt-3 mt-lg-0">
                <a href="horario-funcionamento.php" class="btn btn-outline-primary">
                    Horário de funcionamento
                </a>
                <a href="horarios-profissionais.php" class="btn btn-outline-primary">
                    Horários dos profissionais
                </a>
                <span class="btn btn-outline-secondary disabled" aria-disabled="true">
                    Dias especiais: clique em uma data
                </span>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars((string) $_SESSION['flash_success'], ENT_QUOTES, 'UTF-8') ?>
            <?php unset($_SESSION['flash_success']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars((string) $_SESSION['flash_error'], ENT_QUOTES, 'UTF-8') ?>
            <?php unset($_SESSION['flash_error']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <section class="app-card mb-4">
        <div class="app-card-body">
            <form method="get" class="agenda-filters" id="agendaFilters">
                <input type="hidden" name="mes" value="<?= htmlspecialchars($mesParam, ENT_QUOTES, 'UTF-8') ?>">

                <?php if ($ehAdministrador): ?>
                    <div class="form-group mb-0">
                        <label for="agendaProfissional">Profissional</label>
                        <select class="form-control" id="agendaProfissional" name="profissional">
                            <option value="">Todos os profissionais</option>
                            <?php foreach ($profissionaisDisponiveis as $profissional): ?>
                                <?php $id = (int) $profissional['id']; ?>
                                <option
                                    value="<?= $id ?>"
                                    <?= $id === $profissionalSelecionado ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars((string) $profissional['nome_completo'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-group mb-0">
                    <label for="agendaServico">Serviço</label>
                    <select class="form-control" id="agendaServico" name="servico">
                        <option value="">Todos os serviços</option>
                        <?php foreach ($servicosDisponiveis as $servico): ?>
                            <?php $id = (int) $servico['id']; ?>
                            <option
                                value="<?= $id ?>"
                                <?= $id === $servicoSelecionado ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars((string) $servico['nome'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="agenda-filter-actions">
                    <button type="submit" class="btn btn-primary">Aplicar</button>
                    <a
                        href="agenda.php?<?= htmlspecialchars(http_build_query(['mes' => $mesParam]), ENT_QUOTES, 'UTF-8') ?>"
                        class="btn btn-outline-secondary"
                    >
                        Limpar filtros
                    </a>
                </div>
            </form>
        </div>
    </section>

    <section class="app-card">
        <div class="app-card-header agenda-calendar-header">
            <div class="agenda-month-navigation">
                <a
                    href="agenda.php?<?= htmlspecialchars(agendaQuery(['mes' => $mesAnterior]), ENT_QUOTES, 'UTF-8') ?>"
                    class="btn btn-sm btn-outline-secondary"
                    aria-label="Mês anterior"
                >
                    ‹
                </a>

                <div>
                    <span class="agenda-section-eyebrow">Calendário mensal</span>
                    <h2>
                        <?= htmlspecialchars(
                            ($meses[(int) $inicioMes->format('n')] ?? '') . ' ' . $inicioMes->format('Y'),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </h2>
                </div>

                <a
                    href="agenda.php?<?= htmlspecialchars(agendaQuery(['mes' => $proximoMes]), ENT_QUOTES, 'UTF-8') ?>"
                    class="btn btn-sm btn-outline-secondary"
                    aria-label="Próximo mês"
                >
                    ›
                </a>
            </div>

            <a
                href="agenda.php?<?= htmlspecialchars(agendaQuery(['mes' => $hoje->format('Y-m')]), ENT_QUOTES, 'UTF-8') ?>"
                class="btn btn-sm btn-outline-primary"
            >
                Hoje
            </a>
        </div>

        <div class="app-card-body p-0">
            <div class="agenda-calendar-scroll">
                <div class="agenda-calendar">
                    <?php foreach ($nomesDias as $nomeDia): ?>
                        <div class="agenda-weekday"><?= htmlspecialchars($nomeDia, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endforeach; ?>

                    <?php for ($celula = 1; $celula <= $totalCelulas; $celula++): ?>
                        <?php
                        $numeroDia = $celula - ($primeiroDiaSemana - 1);

                        if ($numeroDia < 1 || $numeroDia > $diasNoMes):
                        ?>
                            <div class="agenda-day agenda-day--empty" aria-hidden="true"></div>
                            <?php continue; ?>
                        <?php endif; ?>

                        <?php
                        $data = $inicioMes->setDate(
                            (int) $inicioMes->format('Y'),
                            (int) $inicioMes->format('m'),
                            $numeroDia
                        );

                        $diaSemana = (int) $data->format('N');
                        $dataIso = $data->format('Y-m-d');
                        $excecaoDia = $excecoesEmpresa[$dataIso] ?? null;
                        $empresaAberta = !empty($horariosEmpresa[$diaSemana]);

                        if ($excecaoDia !== null) {
                            if ($excecaoDia['tipo'] === 'fechado') {
                                $empresaAberta = false;
                            } elseif ($excecaoDia['tipo'] === 'horario_especial') {
                                $empresaAberta = !empty($excecaoDia['periodos']);
                            }
                        }

                        $profissionalTrabalha = true;

                        if ($profissionalSelecionado > 0) {
                            $profissionalTrabalha = !empty($horariosProfissional[$diaSemana]);
                        }

                        $ausenciasDia = $profissionalSelecionado > 0
                            ? ($ausenciasAprovadas[$dataIso] ?? [])
                            : [];
                        $temAusenciaAprovada = $ausenciasDia !== [];

                        $diaDisponivel = $empresaAberta && $profissionalTrabalha && !$temAusenciaAprovada;
                        $ehHoje = $data->format('Y-m-d') === $hoje->format('Y-m-d');

                        $classes = ['agenda-day'];

                        if ($excecaoDia !== null) {
                            $classes[] = 'agenda-day--special';

                            if ($excecaoDia['tipo'] === 'fechado') {
                                $classes[] = 'agenda-day--special-closed';
                            } elseif ($excecaoDia['tipo'] === 'horario_especial') {
                                $classes[] = 'agenda-day--special-hours';
                            }
                        }

                        if (!$empresaAberta) {
                            $classes[] = 'agenda-day--closed';
                        } elseif (!$profissionalTrabalha) {
                            $classes[] = 'agenda-day--unavailable';
                        } else {
                            $classes[] = 'agenda-day--open';
                        }

                        $ehPassado = $data < $hoje;

                        if ($ehPassado) {
                            $classes[] = 'agenda-day--past';
                        }

                        if ($temAusenciaAprovada) {
                            $classes[] = 'agenda-day--absence-approved';
                        }

                        if ($ehHoje) {
                            $classes[] = 'agenda-day--today';
                        }
                        ?>

                        <?php if ($ehAdministrador && !$ehPassado && !$temAusenciaAprovada): ?>
                            <button
                                type="button"
                                class="<?= htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8') ?> agenda-day-button"
                                data-special-date="<?= htmlspecialchars($dataIso, ENT_QUOTES, 'UTF-8') ?>"
                                data-special-label="<?= htmlspecialchars($data->format('d/m/Y'), ENT_QUOTES, 'UTF-8') ?>"
                                data-special-type="<?= htmlspecialchars((string) ($excecaoDia['tipo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-special-description="<?= htmlspecialchars((string) ($excecaoDia['descricao'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-special-periods="<?= htmlspecialchars(json_encode($excecaoDia['periodos'] ?? []), ENT_QUOTES, 'UTF-8') ?>"
                                aria-label="<?= htmlspecialchars('Alterar funcionamento de ' . $data->format('d/m/Y'), ENT_QUOTES, 'UTF-8') ?>"
                            >
                        <?php elseif ($diaDisponivel): ?>
                            <a
                                href="#"
                                class="<?= htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8') ?>"
                                data-agenda-date="<?= htmlspecialchars($dataIso, ENT_QUOTES, 'UTF-8') ?>"
                                aria-label="<?= htmlspecialchars('Abrir agenda de ' . $data->format('d/m/Y'), ENT_QUOTES, 'UTF-8') ?>"
                            >
                        <?php else: ?>
                            <div class="<?= htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>

                            <div class="agenda-day-top">
                                <span class="agenda-day-number"><?= $numeroDia ?></span>
                                <?php if ($ehHoje): ?>
                                    <span class="agenda-today-badge">Hoje</span>
                                <?php endif; ?>
                            </div>

                            <div class="agenda-day-content">
                                <?php if ($excecaoDia !== null): ?>
                                    <?php if ($excecaoDia['tipo'] === 'fechado'): ?>
                                        <span class="agenda-special-badge agenda-special-badge--closed">Fechado</span>
                                    <?php else: ?>
                                        <span class="agenda-special-badge agenda-special-badge--hours">Horário especial</span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($temAusenciaAprovada && !$ehPassado): ?>
                                    <a
                                        href="ausencias-profissionais.php?detalhe=<?= (int) $ausenciasDia[0]['id'] ?>"
                                        class="agenda-absence-badge"
                                        title="Ver detalhes da ausência aprovada"
                                    >
                                        Ausência aprovada
                                    </a>
                                <?php elseif ($ehPassado): ?>
                                    <span class="agenda-day-status">Data passada</span>
                                <?php elseif (!$empresaAberta && $excecaoDia === null): ?>
                                    <span class="agenda-day-status">Fechado</span>
                                <?php elseif (!$empresaAberta && $excecaoDia !== null): ?>
                                    <?php if ($excecaoDia['descricao'] !== ''): ?>
                                        <span class="agenda-day-placeholder agenda-day-placeholder--closed">
                                            <?= htmlspecialchars($excecaoDia['descricao'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php endif; ?>
                                <?php elseif (!$profissionalTrabalha): ?>
                                    <span class="agenda-day-status">Sem horário</span>
                                <?php else: ?>
                                    <span class="agenda-day-status agenda-day-status--open">Disponível</span>
                                    <?php if (
                                    !$ehPassado
                                    && $excecaoDia !== null
                                    && $excecaoDia['tipo'] !== 'fechado'
                                    && $excecaoDia['descricao'] !== ''
                                ): ?>
                                        <span class="agenda-day-placeholder">
                                            <?= htmlspecialchars($excecaoDia['descricao'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php elseif (!$ehPassado && $excecaoDia !== null && $excecaoDia['tipo'] === 'horario_especial'): ?>
                                        <span class="agenda-day-placeholder">
                                            <?= htmlspecialchars(
                                                implode(' / ', array_map(
                                                    static fn (array $p): string => $p['inicio'] . '–' . $p['fim'],
                                                    $excecaoDia['periodos']
                                                )),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>
                                    <?php elseif (!$ehPassado): ?>
                                        <span class="agenda-day-placeholder">
                                            Os atendimentos reais serão ligados nesta etapa da agenda.
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                        <?php if ($ehAdministrador && !$ehPassado && !$temAusenciaAprovada): ?>
                            </button>
                        <?php elseif ($diaDisponivel): ?>
                            </a>
                        <?php else: ?>
                            </div>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </section>

    <?php if ($ehAdministrador): ?>
        <div class="agenda-special-modal" id="agendaSpecialModal" hidden>
            <div class="agenda-special-backdrop" data-special-close></div>
            <div class="agenda-special-dialog" role="dialog" aria-modal="true" aria-labelledby="agendaSpecialTitle">
                <div class="agenda-special-header">
                    <div>
                        <span class="agenda-section-eyebrow">Funcionamento da empresa</span>
                        <h2 id="agendaSpecialTitle">Alterar funcionamento deste dia</h2>
                        <p class="mb-0 text-muted" id="agendaSpecialDateLabel"></p>
                    </div>
                    <button type="button" class="agenda-special-close" data-special-close aria-label="Fechar">×</button>
                </div>

                <form method="post" action="api/agenda-excecao.php" id="agendaSpecialForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfAgendaExcecao, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="data_excecao" id="agendaSpecialDate">
                    <input type="hidden" name="mes_retorno" value="<?= htmlspecialchars($mesParam, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="profissional_retorno" value="<?= $profissionalSelecionado ?>">
                    <input type="hidden" name="servico_retorno" value="<?= $servicoSelecionado ?>">

                    <div class="agenda-special-options">
                        <label class="agenda-special-option">
                            <input type="radio" name="acao" value="normal" id="specialNormal">
                            <span><strong>Funcionamento normal</strong><small>Usar o horário semanal desta data.</small></span>
                        </label>
                        <label class="agenda-special-option">
                            <input type="radio" name="acao" value="fechado" id="specialClosed">
                            <span><strong>Fechado neste dia</strong><small>Feriado, recesso ou fechamento excepcional.</small></span>
                        </label>
                        <label class="agenda-special-option">
                            <input type="radio" name="acao" value="horario_especial" id="specialHours">
                            <span><strong>Horário especial</strong><small>Alterar somente o funcionamento desta data.</small></span>
                        </label>
                    </div>

                    <div class="agenda-special-periods" id="agendaSpecialPeriods" hidden>
                        <div class="agenda-special-period-row">
                            <div class="form-group mb-0">
                                <label>Início</label>
                                <input type="time" class="form-control" name="hora_inicio[]">
                            </div>
                            <div class="form-group mb-0">
                                <label>Fim</label>
                                <input type="time" class="form-control" name="hora_fim[]">
                            </div>
                        </div>
                        <div class="agenda-special-period-row">
                            <div class="form-group mb-0">
                                <label>2º período</label>
                                <input type="time" class="form-control" name="hora_inicio[]">
                            </div>
                            <div class="form-group mb-0">
                                <label>Fim</label>
                                <input type="time" class="form-control" name="hora_fim[]">
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-3 mb-0">
                        <label for="agendaSpecialDescription">Motivo / descrição</label>
                        <input type="text" class="form-control" id="agendaSpecialDescription" name="descricao"
                               maxlength="255" placeholder="Ex.: Feriado municipal">
                    </div>

                    <div class="agenda-special-actions">
                        <button type="button" class="btn btn-outline-secondary" data-special-close>Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar funcionamento</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
