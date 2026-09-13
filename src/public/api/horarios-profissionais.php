<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

exigirAdministrador();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
$csrfSession = (string) ($_SESSION['csrf_horarios_profissionais'] ?? '');

if (
    $csrfToken === ''
    || $csrfSession === ''
    || !hash_equals($csrfSession, $csrfToken)
) {
    http_response_code(403);
    exit('Requisição inválida.');
}

$profissionalId = filter_input(INPUT_POST, 'profissional_id', FILTER_VALIDATE_INT);

if (!is_int($profissionalId) || $profissionalId <= 0) {
    $_SESSION['flash_error'] = 'Profissional inválido.';
    header('Location: ../horarios-profissionais.php');
    exit;
}

$stmtProfissional = $pdo->prepare(
    'SELECT id
     FROM profissionais
     WHERE id = :profissional_id
       AND empresa_id = :empresa_id
       AND ativo = 1
     LIMIT 1'
);
$stmtProfissional->execute([
    ':profissional_id' => $profissionalId,
    ':empresa_id' => $empresaId,
]);

if (!$stmtProfissional->fetchColumn()) {
    http_response_code(403);
    exit('Profissional não pertence à empresa autenticada.');
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

$aberto = is_array($_POST['aberto'] ?? null) ? $_POST['aberto'] : [];
$inicio = is_array($_POST['inicio'] ?? null) ? $_POST['inicio'] : [];
$fim = is_array($_POST['fim'] ?? null) ? $_POST['fim'] : [];

function horarioValido(string $valor): bool
{
    return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $valor) === 1;
}

function minutos(string $hora): int
{
    [$h, $m] = array_map('intval', explode(':', $hora));

    return ($h * 60) + $m;
}

$novosHorarios = [];
$erros = [];

for ($dia = 1; $dia <= 7; $dia++) {
    if (!isset($aberto[$dia])) {
        continue;
    }

    if (empty($horariosEmpresa[$dia])) {
        $erros[] = 'O profissional não pode ser configurado em um dia em que a empresa está fechada.';
        continue;
    }

    $periodosDia = [];

    for ($indice = 0; $indice < 2; $indice++) {
        $horaInicio = trim((string) ($inicio[$dia][$indice] ?? ''));
        $horaFim = trim((string) ($fim[$dia][$indice] ?? ''));

        if ($horaInicio === '' && $horaFim === '') {
            continue;
        }

        if (!horarioValido($horaInicio) || !horarioValido($horaFim)) {
            $erros[] = 'Existe um horário inválido na agenda do profissional.';
            continue;
        }

        $inicioMinutos = minutos($horaInicio);
        $fimMinutos = minutos($horaFim);

        if ($fimMinutos <= $inicioMinutos) {
            $erros[] = 'O horário final deve ser maior que o horário inicial.';
            continue;
        }

        $contidoNoHorarioEmpresa = false;

        foreach ($horariosEmpresa[$dia] as $periodoEmpresa) {
            $empresaInicio = minutos($periodoEmpresa['inicio']);
            $empresaFim = minutos($periodoEmpresa['fim']);

            if ($inicioMinutos >= $empresaInicio && $fimMinutos <= $empresaFim) {
                $contidoNoHorarioEmpresa = true;
                break;
            }
        }

        if (!$contidoNoHorarioEmpresa) {
            $erros[] = 'O horário do profissional deve ficar totalmente dentro de um período de funcionamento da empresa.';
            continue;
        }

        $periodosDia[] = [
            'inicio' => $horaInicio,
            'fim' => $horaFim,
            'inicio_minutos' => $inicioMinutos,
            'fim_minutos' => $fimMinutos,
        ];
    }

    usort(
        $periodosDia,
        static fn (array $a, array $b): int => $a['inicio_minutos'] <=> $b['inicio_minutos']
    );

    for ($i = 1, $total = count($periodosDia); $i < $total; $i++) {
        if ($periodosDia[$i]['inicio_minutos'] < $periodosDia[$i - 1]['fim_minutos']) {
            $erros[] = 'Existem períodos sobrepostos na agenda do profissional.';
        }
    }

    foreach ($periodosDia as $periodo) {
        $novosHorarios[] = [
            'dia' => $dia,
            'inicio' => $periodo['inicio'],
            'fim' => $periodo['fim'],
        ];
    }
}

if ($novosHorarios === []) {
    $erros[] = 'Informe pelo menos um período de trabalho para o profissional.';
}

if ($erros !== []) {
    $_SESSION['flash_error'] = $erros[0];

    header(
        'Location: ../horarios-profissionais.php?profissional='
        . rawurlencode((string) $profissionalId)
    );
    exit;
}

try {
    $pdo->beginTransaction();

    $stmtDesativar = $pdo->prepare(
        'UPDATE profissional_horarios ph
         INNER JOIN profissionais p
            ON p.id = ph.profissional_id
         SET ph.ativo = 0
         WHERE ph.profissional_id = :profissional_id
           AND p.empresa_id = :empresa_id
           AND ph.ativo = 1'
    );
    $stmtDesativar->execute([
        ':profissional_id' => $profissionalId,
        ':empresa_id' => $empresaId,
    ]);

    $stmtInserir = $pdo->prepare(
        'INSERT INTO profissional_horarios (
            profissional_id,
            dia_semana,
            hora_inicio,
            hora_fim,
            ativo
         ) VALUES (
            :profissional_id,
            :dia_semana,
            :hora_inicio,
            :hora_fim,
            1
         )'
    );

    foreach ($novosHorarios as $horario) {
        $stmtInserir->execute([
            ':profissional_id' => $profissionalId,
            ':dia_semana' => $horario['dia'],
            ':hora_inicio' => $horario['inicio'],
            ':hora_fim' => $horario['fim'],
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['flash_error'] = 'Não foi possível salvar os horários do profissional.';

    header(
        'Location: ../horarios-profissionais.php?profissional='
        . rawurlencode((string) $profissionalId)
    );
    exit;
}

$_SESSION['csrf_horarios_profissionais'] = bin2hex(random_bytes(32));
$_SESSION['flash_success'] = 'Horários do profissional salvos com sucesso.';

header(
    'Location: ../horarios-profissionais.php?profissional='
    . rawurlencode((string) $profissionalId)
);
exit;
