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

$flashSuccess = (string) ($_SESSION['flash_success'] ?? '');
$flashError = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($ehAdministrador && empty($_SESSION['csrf_horarios_profissionais'])) {
    $_SESSION['csrf_horarios_profissionais'] = bin2hex(random_bytes(32));
}

$csrfToken = $ehAdministrador
    ? (string) $_SESSION['csrf_horarios_profissionais']
    : '';

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

$profissionais = $stmtProfissionais->fetchAll(PDO::FETCH_ASSOC);

if ($ehProfissional) {
    if ($profissionais === []) {
        http_response_code(403);
        exit('Acesso negado.');
    }

    $profissionalSelecionado = $profissionalSessaoId;
} else {
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

$pageTitle = $ehProfissional ? 'Meus horários' : 'Horários dos profissionais';
$pageCss = 'horarios-profissionais.css?v=20260914-1';
$pageJs = $ehAdministrador ? 'horarios-profissionais.js?v=20260914-1' : null;

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
            <h1><?= $ehProfissional ? 'Meus horários' : 'Horários dos profissionais' ?></h1>
            <p>
                <?= $ehProfissional
                    ? 'Consulte os períodos semanais definidos para sua agenda.'
                    : 'Defina os períodos semanais em que cada profissional pode receber agendamentos.' ?>
            </p>
        </div>

        <div class="mt-3 mt-md-0">
            <a href="agenda.php" class="btn btn-outline-secondary">
                Voltar para agenda
            </a>
        </div>
    </div>

    <?php if ($profissionais === []): ?>
        <div class="app-card">
            <div class="app-card-body">
                <div class="app-empty-state">
                    <h2>Nenhum profissional ativo</h2>
                    <p>Não há horários profissionais disponíveis.</p>

                    <?php if ($ehAdministrador): ?>
                        <a href="cadastro-profissional.php" class="btn btn-primary">
                            Cadastrar profissional
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-12 col-xl-8 mb-4">
                <div class="app-card">
                    <div class="app-card-body">
                        <?php if ($ehAdministrador): ?>
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
                        <?php endif; ?>

                        <?php if ($ehAdministrador): ?>
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
                        <?php endif; ?>

                            <div class="hp-toolbar mb-4">
                                <div>
                                    <strong>Agenda semanal</strong>
                                    <p class="text-muted mb-0">
                                        <?= $ehProfissional
                                            ? 'Estes horários são definidos pela administração da empresa.'
                                            : 'O horário do profissional deve ficar dentro do horário de funcionamento da empresa.' ?>
                                    </p>
                                </div>

                                <?php if ($ehAdministrador): ?>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        id="copiarHorarioEmpresa"
                                    >
                                        Copiar horário da empresa
                                    </button>
                                <?php endif; ?>
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
                                                <?php if ($ehAdministrador): ?>
                                                    <div class="custom-control custom-switch">
                                                        <input
                                                            type="checkbox"
                                                            class="custom-control-input hp-day-toggle"
                                                            id="aberto_<?= $dia ?>"
                                                            name="aberto[<?= $dia ?>]"
                                                            value="1"
                                                            <?= $profissionalAtivo ? 'checked' : '' ?>
                                                            <?= !$empresaAberta ? 'disabled' : '' ?>
                                                        >
                                                        <label class="custom-control-label" for="aberto_<?= $dia ?>">
                                                            <strong><?= htmlspecialchars($nomeDia, ENT_QUOTES, 'UTF-8') ?></strong>
                                                        </label>
                                                    </div>
                                                <?php else: ?>
                                                    <h2><?= htmlspecialchars($nomeDia, ENT_QUOTES, 'UTF-8') ?></h2>
                                                <?php endif; ?>

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

                                            <div>
                                                <span class="badge badge-<?= $profissionalAtivo ? 'success' : 'secondary' ?> hp-day-status">
                                                    <?= $profissionalAtivo ? 'Disponível' : 'Indisponível' ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="hp-periods">
                                            <?php for ($indice = 0; $indice < 2; $indice++): ?>
                                                <?php
                                                $inicio = periodoProfissional($horariosProfissional, $dia, $indice, 'inicio');
                                                $fim = periodoProfissional($horariosProfissional, $dia, $indice, 'fim');

                                                if ($ehProfissional && $inicio === '' && $fim === '') {
                                                    continue;
                                                }
                                                ?>
                                                <div class="hp-period">
                                                    <span class="hp-period-label">
                                                        <?= $indice === 0 ? '1º período' : '2º período' ?>
                                                    </span>

                                                    <div class="hp-time-group">
                                                        <div class="form-group mb-0">
                                                            <label>Início</label>
                                                            <input
                                                                type="time"
                                                                class="form-control hp-time-input"
                                                                <?php if ($ehAdministrador): ?>
                                                                    id="inicio_<?= $dia ?>_<?= $indice ?>"
                                                                    name="inicio[<?= $dia ?>][<?= $indice ?>]"
                                                                <?php endif; ?>
                                                                value="<?= htmlspecialchars($inicio, ENT_QUOTES, 'UTF-8') ?>"
                                                                <?= (!$ehAdministrador || !$empresaAberta || !$profissionalAtivo) ? 'disabled' : '' ?>
                                                            >
                                                        </div>

                                                        <div class="form-group mb-0">
                                                            <label>Fim</label>
                                                            <input
                                                                type="time"
                                                                class="form-control hp-time-input"
                                                                <?php if ($ehAdministrador): ?>
                                                                    id="fim_<?= $dia ?>_<?= $indice ?>"
                                                                    name="fim[<?= $dia ?>][<?= $indice ?>]"
                                                                <?php endif; ?>
                                                                value="<?= htmlspecialchars($fim, ENT_QUOTES, 'UTF-8') ?>"
                                                                <?= (!$ehAdministrador || !$empresaAberta || !$profissionalAtivo) ? 'disabled' : '' ?>
                                                            >
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endfor; ?>

                                            <?php if ($ehProfissional && !$profissionalAtivo): ?>
                                                <p class="text-muted mb-0">
                                                    Nenhum período de trabalho definido para este dia.
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </section>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($ehAdministrador): ?>
                                <div class="d-flex justify-content-end mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        Salvar horários
                                    </button>
                                </div>
                            <?php endif; ?>

                        <?php if ($ehAdministrador): ?>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4 mb-4">
                <div class="app-card hp-help-card">
                    <div class="app-card-header">
                        <h2><?= $ehProfissional ? 'Sobre seus horários' : 'Como funciona' ?></h2>
                    </div>

                    <div class="app-card-body">
                        <?php if ($ehProfissional): ?>
                            <p>
                                Seu horário semanal é administrado pela empresa e define quando você pode receber agendamentos.
                            </p>
                            <p class="mb-0">
                                Alterações de disponibilidade devem ser tratadas com a administração enquanto a gestão de bloqueios e folgas ainda está em evolução.
                            </p>
                        <?php else: ?>
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
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php if ($ehAdministrador): ?>
<script>
window.SALAO_HORARIOS_EMPRESA = <?= json_encode(
    $horariosEmpresa,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
) ?>;
</script>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
