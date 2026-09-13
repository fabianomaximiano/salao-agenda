<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigirAdministrador();

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();

$flashSuccess = (string) ($_SESSION['flash_success'] ?? '');
$flashError = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if (empty($_SESSION['csrf_horarios_profissionais'])) {
    $_SESSION['csrf_horarios_profissionais'] = bin2hex(random_bytes(32));
}

$csrfToken = (string) $_SESSION['csrf_horarios_profissionais'];

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
$profissionais = $stmtProfissionais->fetchAll(PDO::FETCH_ASSOC);

$profissionalSelecionado = filter_input(INPUT_GET, 'profissional', FILTER_VALIDATE_INT);

if (!is_int($profissionalSelecionado) || $profissionalSelecionado <= 0) {
    $profissionalSelecionado = isset($profissionais[0]['id'])
        ? (int) $profissionais[0]['id']
        : 0;
}

$profissionalValido = false;

foreach ($profissionais as $profissional) {
    if ((int) $profissional['id'] === $profissionalSelecionado) {
        $profissionalValido = true;
        break;
    }
}

if (!$profissionalValido && $profissionais !== []) {
    $profissionalSelecionado = (int) $profissionais[0]['id'];
}

$stmtEmpresa = $pdo->prepare(
    'SELECT dia_semana, hora_inicio, hora_fim
     FROM empresa_horarios
     WHERE empresa_id = :empresa_id
       AND ativo = 1
     ORDER BY dia_semana, hora_inicio'
);
$stmtEmpresa->execute([':empresa_id' => $empresaId]);

$horariosEmpresa = [];

foreach ($stmtEmpresa->fetchAll(PDO::FETCH_ASSOC) as $horario) {
    $dia = (int) $horario['dia_semana'];

    $horariosEmpresa[$dia][] = [
        'inicio' => substr((string) $horario['hora_inicio'], 0, 5),
        'fim' => substr((string) $horario['hora_fim'], 0, 5),
    ];
}

$horariosProfissional = [];

if ($profissionalSelecionado > 0) {
    $stmtHorarios = $pdo->prepare(
        'SELECT ph.dia_semana, ph.hora_inicio, ph.hora_fim
         FROM profissional_horarios ph
         INNER JOIN profissionais p
            ON p.id = ph.profissional_id
         WHERE ph.profissional_id = :profissional_id
           AND p.empresa_id = :empresa_id
           AND ph.ativo = 1
         ORDER BY ph.dia_semana, ph.hora_inicio'
    );
    $stmtHorarios->execute([
        ':profissional_id' => $profissionalSelecionado,
        ':empresa_id' => $empresaId,
    ]);

    foreach ($stmtHorarios->fetchAll(PDO::FETCH_ASSOC) as $horario) {
        $dia = (int) $horario['dia_semana'];

        $horariosProfissional[$dia][] = [
            'inicio' => substr((string) $horario['hora_inicio'], 0, 5),
            'fim' => substr((string) $horario['hora_fim'], 0, 5),
        ];
    }
}

$dias = [
    1 => 'Segunda-feira',
    2 => 'Terça-feira',
    3 => 'Quarta-feira',
    4 => 'Quinta-feira',
    5 => 'Sexta-feira',
    6 => 'Sábado',
    7 => 'Domingo',
];

function periodoProfissional(array $horarios, int $dia, int $indice, string $campo): string
{
    return (string) ($horarios[$dia][$indice][$campo] ?? '');
}

$pageTitle = 'Horários dos profissionais';
$pageCss = 'horarios-profissionais.css?v=20260913-1';
$pageJs = 'horarios-profissionais.js?v=20260913-1';

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

    <?php if ($flashError !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="app-page-header d-md-flex justify-content-between align-items-start">
        <div>
            <h1>Horários dos profissionais</h1>
            <p>Defina os períodos semanais em que cada profissional pode receber agendamentos.</p>
        </div>

        <div class="mt-3 mt-md-0">
            <a href="agenda.php" class="btn btn-outline-secondary">Voltar para agenda</a>
        </div>
    </div>

    <?php if ($profissionais === []): ?>
        <div class="app-card">
            <div class="app-card-body">
                <div class="app-empty-state">
                    <h2>Nenhum profissional ativo</h2>
                    <p>Cadastre ou ative um profissional antes de configurar os horários.</p>
                    <a href="cadastro-profissional.php" class="btn btn-primary">Cadastrar profissional</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-12 col-xl-8 mb-4">
                <div class="app-card">
                    <div class="app-card-body">
                        <form method="get" class="mb-4" id="profissionalSelectorForm">
                            <div class="form-group mb-0">
                                <label for="profissionalSelector">Profissional</label>
                                <select
                                    class="form-control"
                                    id="profissionalSelector"
                                    name="profissional"
                                >
                                    <?php foreach ($profissionais as $profissional): ?>
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
                        </form>

                        <form method="post" action="api/horarios-profissionais.php" id="horariosProfissionaisForm">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
                            >
                            <input
                                type="hidden"
                                name="profissional_id"
                                value="<?= $profissionalSelecionado ?>"
                            >

                            <div class="hp-toolbar mb-4">
                                <div>
                                    <strong>Agenda semanal</strong>
                                    <p class="text-muted mb-0">
                                        O horário do profissional deve ficar dentro do horário de funcionamento da empresa.
                                    </p>
                                </div>

                                <button type="button" class="btn btn-sm btn-outline-primary" id="copiarHorarioEmpresa">
                                    Copiar horário da empresa
                                </button>
                            </div>

                            <div class="hp-days">
                                <?php foreach ($dias as $dia => $nomeDia): ?>
                                    <?php
                                    $empresaAberta = !empty($horariosEmpresa[$dia]);
                                    $profissionalAtivo = !empty($horariosProfissional[$dia]);
                                    ?>
                                    <section
                                        class="hp-day <?= $empresaAberta ? '' : 'is-company-closed' ?> <?= $profissionalAtivo ? 'is-enabled' : '' ?>"
                                        data-day="<?= $dia ?>"
                                    >
                                        <div class="hp-day-header">
                                            <div>
                                                <h2><?= htmlspecialchars($nomeDia, ENT_QUOTES, 'UTF-8') ?></h2>

                                                <?php if ($empresaAberta): ?>
                                                    <div class="hp-company-reference">
                                                        Empresa:
                                                        <?php
                                                        $referencias = array_map(
                                                            static fn (array $periodo): string => $periodo['inicio'] . '–' . $periodo['fim'],
                                                            $horariosEmpresa[$dia]
                                                        );
                                                        ?>
                                                        <?= htmlspecialchars(implode(' / ', $referencias), ENT_QUOTES, 'UTF-8') ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="hp-company-reference">
                                                        Empresa fechada neste dia
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="custom-control custom-switch">
                                                <input
                                                    type="checkbox"
                                                    class="custom-control-input hp-day-toggle"
                                                    id="dia_<?= $dia ?>"
                                                    name="aberto[<?= $dia ?>]"
                                                    value="1"
                                                    <?= $profissionalAtivo ? 'checked' : '' ?>
                                                    <?= !$empresaAberta ? 'disabled' : '' ?>
                                                >
                                                <label class="custom-control-label" for="dia_<?= $dia ?>">
                                                    <span class="hp-day-status">
                                                        <?= $profissionalAtivo ? 'Disponível' : 'Indisponível' ?>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="hp-periods">
                                            <?php for ($indice = 0; $indice < 2; $indice++): ?>
                                                <div class="hp-period">
                                                    <span class="hp-period-label">
                                                        <?= $indice === 0 ? '1º período' : '2º período' ?>
                                                    </span>

                                                    <div class="hp-time-group">
                                                        <div class="form-group mb-0">
                                                            <label for="inicio_<?= $dia ?>_<?= $indice ?>">Início</label>
                                                            <input
                                                                type="time"
                                                                class="form-control hp-time-input"
                                                                id="inicio_<?= $dia ?>_<?= $indice ?>"
                                                                name="inicio[<?= $dia ?>][<?= $indice ?>]"
                                                                value="<?= htmlspecialchars(periodoProfissional($horariosProfissional, $dia, $indice, 'inicio'), ENT_QUOTES, 'UTF-8') ?>"
                                                                <?= (!$empresaAberta || !$profissionalAtivo) ? 'disabled' : '' ?>
                                                            >
                                                        </div>

                                                        <div class="form-group mb-0">
                                                            <label for="fim_<?= $dia ?>_<?= $indice ?>">Fim</label>
                                                            <input
                                                                type="time"
                                                                class="form-control hp-time-input"
                                                                id="fim_<?= $dia ?>_<?= $indice ?>"
                                                                name="fim[<?= $dia ?>][<?= $indice ?>]"
                                                                value="<?= htmlspecialchars(periodoProfissional($horariosProfissional, $dia, $indice, 'fim'), ENT_QUOTES, 'UTF-8') ?>"
                                                                <?= (!$empresaAberta || !$profissionalAtivo) ? 'disabled' : '' ?>
                                                            >
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                    </section>
                                <?php endforeach; ?>
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" class="btn btn-primary">
                                    Salvar horários
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4 mb-4">
                <div class="app-card hp-help-card">
                    <div class="app-card-header">
                        <h2>Como funciona</h2>
                    </div>

                    <div class="app-card-body">
                        <p>
                            O horário da empresa define a janela geral de funcionamento.
                        </p>
                        <p>
                            Cada profissional pode trabalhar em períodos menores dentro dessa janela.
                        </p>
                        <p class="mb-0">
                            Depois, a disponibilidade final também considerará exceções, bloqueios,
                            duração dos serviços e agendamentos existentes.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<script>
window.SALAO_HORARIOS_EMPRESA = <?= json_encode(
    $horariosEmpresa,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
) ?>;
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
