<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigirAdministrador();

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();

if (empty($_SESSION['csrf_horario_funcionamento'])) {
    $_SESSION['csrf_horario_funcionamento'] = bin2hex(random_bytes(32));
}

$csrfToken = (string) $_SESSION['csrf_horario_funcionamento'];

$flash = $_SESSION['flash_horario_funcionamento'] ?? null;
unset($_SESSION['flash_horario_funcionamento']);

$mensagem = is_string($flash['mensagem'] ?? null) ? $flash['mensagem'] : null;
$tipoMensagem = ($flash['tipo'] ?? '') === 'success' ? 'success' : 'danger';
$erros = is_array($flash['erros'] ?? null) ? $flash['erros'] : [];
$old = is_array($flash['old'] ?? null) ? $flash['old'] : null;

$dias = [
    1 => 'Segunda-feira',
    2 => 'Terça-feira',
    3 => 'Quarta-feira',
    4 => 'Quinta-feira',
    5 => 'Sexta-feira',
    6 => 'Sábado',
    7 => 'Domingo',
];

$horarios = [];

foreach ($dias as $diaNumero => $diaNome) {
    $horarios[$diaNumero] = [];
}

$stmt = $pdo->prepare(
    'SELECT
        dia_semana,
        hora_inicio,
        hora_fim
     FROM empresa_horarios
     WHERE empresa_id = :empresa_id
       AND ativo = 1
     ORDER BY dia_semana ASC, hora_inicio ASC'
);

$stmt->execute([
    ':empresa_id' => $empresaId,
]);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $registro) {
    $dia = (int) $registro['dia_semana'];

    if (!isset($horarios[$dia])) {
        continue;
    }

    $horarios[$dia][] = [
        'inicio' => substr((string) $registro['hora_inicio'], 0, 5),
        'fim' => substr((string) $registro['hora_fim'], 0, 5),
    ];
}

if ($old !== null) {
    foreach ($dias as $diaNumero => $diaNome) {
        $diaKey = (string) $diaNumero;
        $aberto = !empty($old['aberto'][$diaKey]);

        if (!$aberto) {
            $horarios[$diaNumero] = [];
            continue;
        }

        $periodos = [];

        for ($periodo = 1; $periodo <= 2; $periodo++) {
            $inicio = trim((string) ($old['inicio'][$diaKey][$periodo] ?? ''));
            $fim = trim((string) ($old['fim'][$diaKey][$periodo] ?? ''));

            if ($inicio !== '' || $fim !== '') {
                $periodos[] = [
                    'inicio' => $inicio,
                    'fim' => $fim,
                ];
            }
        }

        $horarios[$diaNumero] = $periodos;
    }
}

function periodoValor(
    array $horarios,
    int $dia,
    int $indice,
    string $campo
): string {
    $valor = $horarios[$dia][$indice][$campo] ?? '';

    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

$pageTitle = 'Horário de funcionamento';
$pageCss = 'horario-funcionamento.css';
$pageJs = 'horario-funcionamento.js';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/partials/navbar.php';
?>

<main class="app-content">
    <div class="app-page-header d-md-flex justify-content-between align-items-center">
        <div>
            <h1>Horário de funcionamento</h1>
            <p>Defina em quais dias e horários sua empresa atende.</p>
        </div>

        <div class="mt-3 mt-md-0">
            <a href="dashboard.php" class="btn btn-outline-secondary">
                Voltar ao dashboard
            </a>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipoMensagem ?>" role="alert">
            <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form action="api/horario-funcionamento.php" method="post" novalidate>
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
        >

        <div class="row">
            <div class="col-12 col-xl-8 mb-4">
                <div class="app-card">
                    <div class="app-card-header">
                        <h2>Semana de atendimento</h2>
                    </div>

                    <div class="app-card-body">
                        <p class="text-muted mb-4">
                            Marque os dias em que a empresa funciona. Você pode informar até dois períodos
                            por dia, por exemplo 09:00–12:00 e 13:00–18:00.
                        </p>

                        <?php foreach ($dias as $diaNumero => $diaNome): ?>
                            <?php
                            $periodos = $horarios[$diaNumero] ?? [];
                            $aberto = count($periodos) > 0;
                            $erroDia = $erros['dia_' . $diaNumero] ?? null;
                            ?>

                            <section class="horario-dia<?= $aberto ? ' is-open' : '' ?>">
                                <div class="horario-dia-cabecalho">
                                    <div class="custom-control custom-switch">
                                        <input
                                            type="checkbox"
                                            class="custom-control-input"
                                            id="aberto_<?= $diaNumero ?>"
                                            name="aberto[<?= $diaNumero ?>]"
                                            value="1"
                                            <?= $aberto ? 'checked' : '' ?>
                                            data-dia-toggle="<?= $diaNumero ?>"
                                        >
                                        <label
                                            class="custom-control-label"
                                            for="aberto_<?= $diaNumero ?>"
                                        >
                                            <strong><?= htmlspecialchars($diaNome, ENT_QUOTES, 'UTF-8') ?></strong>
                                        </label>
                                    </div>

                                    <span class="horario-dia-status">
                                        <?= $aberto ? 'Aberto' : 'Fechado' ?>
                                    </span>
                                </div>

                                <div
                                    class="horario-dia-periodos"
                                    data-dia-periodos="<?= $diaNumero ?>"
                                    <?= !$aberto ? 'hidden' : '' ?>
                                >
                                    <?php for ($periodo = 1; $periodo <= 2; $periodo++): ?>
                                        <?php $indice = $periodo - 1; ?>

                                        <div class="horario-periodo<?= $periodo === 2 ? ' horario-periodo-secundario' : '' ?>">
                                            <div class="form-row align-items-end">
                                                <div class="form-group col-sm-5 mb-sm-0">
                                                    <label for="inicio_<?= $diaNumero ?>_<?= $periodo ?>">
                                                        <?= $periodo === 1 ? 'Abertura' : 'Retorno' ?>
                                                    </label>
                                                    <input
                                                        type="time"
                                                        class="form-control"
                                                        id="inicio_<?= $diaNumero ?>_<?= $periodo ?>"
                                                        name="inicio[<?= $diaNumero ?>][<?= $periodo ?>]"
                                                        value="<?= periodoValor($horarios, $diaNumero, $indice, 'inicio') ?>"
                                                        <?= $periodo === 1 && $aberto ? 'required' : '' ?>
                                                    >
                                                </div>

                                                <div class="form-group col-sm-5 mb-sm-0">
                                                    <label for="fim_<?= $diaNumero ?>_<?= $periodo ?>">
                                                        <?= $periodo === 1 ? 'Fechamento' : 'Fechamento final' ?>
                                                    </label>
                                                    <input
                                                        type="time"
                                                        class="form-control"
                                                        id="fim_<?= $diaNumero ?>_<?= $periodo ?>"
                                                        name="fim[<?= $diaNumero ?>][<?= $periodo ?>]"
                                                        value="<?= periodoValor($horarios, $diaNumero, $indice, 'fim') ?>"
                                                        <?= $periodo === 1 && $aberto ? 'required' : '' ?>
                                                    >
                                                </div>

                                                <div class="form-group col-sm-2 mb-0">
                                                    <?php if ($periodo === 2): ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-outline-secondary btn-block"
                                                            data-limpar-periodo="<?= $diaNumero ?>"
                                                        >
                                                            Limpar
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <?php if ($periodo === 1): ?>
                                                <small class="form-text text-muted">
                                                    Se houver intervalo, use o segundo período abaixo.
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endfor; ?>

                                    <?php if ($erroDia): ?>
                                        <div class="alert alert-danger mt-3 mb-0 py-2">
                                            <?= htmlspecialchars((string) $erroDia, ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>

                    <div class="app-card-body border-top d-flex flex-column flex-sm-row justify-content-between align-items-sm-center">
                        <a href="dashboard.php" class="btn btn-outline-secondary mb-2 mb-sm-0">
                            Cancelar
                        </a>

                        <button type="submit" class="btn btn-primary">
                            Salvar horário de funcionamento
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4 mb-4">
                <div class="app-card">
                    <div class="app-card-header">
                        <h2>Como esse horário é usado</h2>
                    </div>

                    <div class="app-card-body horario-ajuda">
                        <p>
                            <strong>Funcionamento da empresa</strong> define a janela geral em que o
                            estabelecimento pode operar.
                        </p>
                        <p>
                            O horário de cada <strong>profissional</strong> será configurado separadamente e
                            deverá respeitar esta janela.
                        </p>
                        <p class="mb-0">
                            A disponibilidade final também considera duração dos serviços, bloqueios e
                            agendamentos existentes.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </form>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
