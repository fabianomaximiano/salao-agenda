<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

exigirAdministrador();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método não permitido.');
}

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();

$csrfRecebido = (string) ($_POST['csrf_token'] ?? '');
$csrfSessao = (string) ($_SESSION['csrf_horario_funcionamento'] ?? '');

if (
    $csrfSessao === ''
    || $csrfRecebido === ''
    || !hash_equals($csrfSessao, $csrfRecebido)
) {
    http_response_code(403);
    exit('Token CSRF inválido.');
}

$aberto = is_array($_POST['aberto'] ?? null) ? $_POST['aberto'] : [];
$inicio = is_array($_POST['inicio'] ?? null) ? $_POST['inicio'] : [];
$fim = is_array($_POST['fim'] ?? null) ? $_POST['fim'] : [];

$old = [
    'aberto' => $aberto,
    'inicio' => $inicio,
    'fim' => $fim,
];

$erros = [];
$registros = [];

function horarioValido(string $valor): bool
{
    if (!preg_match('/^\d{2}:\d{2}$/', $valor)) {
        return false;
    }

    [$hora, $minuto] = array_map('intval', explode(':', $valor));

    return $hora >= 0
        && $hora <= 23
        && $minuto >= 0
        && $minuto <= 59;
}

function minutosDoDia(string $valor): int
{
    [$hora, $minuto] = array_map('intval', explode(':', $valor));

    return ($hora * 60) + $minuto;
}

for ($dia = 1; $dia <= 7; $dia++) {
    $diaKey = (string) $dia;

    if (empty($aberto[$diaKey])) {
        continue;
    }

    $periodosDia = [];

    for ($periodo = 1; $periodo <= 2; $periodo++) {
        $inicioValor = trim((string) ($inicio[$diaKey][$periodo] ?? ''));
        $fimValor = trim((string) ($fim[$diaKey][$periodo] ?? ''));

        if ($periodo === 2 && $inicioValor === '' && $fimValor === '') {
            continue;
        }

        if ($inicioValor === '' || $fimValor === '') {
            $erros['dia_' . $dia] = 'Preencha o início e o fim de cada período informado.';
            continue;
        }

        if (!horarioValido($inicioValor) || !horarioValido($fimValor)) {
            $erros['dia_' . $dia] = 'Informe horários válidos.';
            continue;
        }

        $inicioMinutos = minutosDoDia($inicioValor);
        $fimMinutos = minutosDoDia($fimValor);

        if ($fimMinutos <= $inicioMinutos) {
            $erros['dia_' . $dia] = 'O fechamento deve ser posterior à abertura.';
            continue;
        }

        $periodosDia[] = [
            'inicio' => $inicioValor,
            'fim' => $fimValor,
            'inicio_minutos' => $inicioMinutos,
            'fim_minutos' => $fimMinutos,
        ];
    }

    if (!$periodosDia) {
        $erros['dia_' . $dia] = 'Informe pelo menos um período para este dia.';
        continue;
    }

    usort(
        $periodosDia,
        static fn (array $a, array $b): int => $a['inicio_minutos'] <=> $b['inicio_minutos']
    );

    if (
        count($periodosDia) === 2
        && $periodosDia[1]['inicio_minutos'] < $periodosDia[0]['fim_minutos']
    ) {
        $erros['dia_' . $dia] = 'Os períodos deste dia não podem se sobrepor.';
        continue;
    }

    foreach ($periodosDia as $periodoDia) {
        $registros[] = [
            'dia_semana' => $dia,
            'hora_inicio' => $periodoDia['inicio'] . ':00',
            'hora_fim' => $periodoDia['fim'] . ':00',
        ];
    }
}

if ($erros) {
    $_SESSION['flash_horario_funcionamento'] = [
        'tipo' => 'danger',
        'mensagem' => 'Revise os horários destacados.',
        'erros' => $erros,
        'old' => $old,
    ];

    header('Location: ../horario-funcionamento.php');
    exit;
}

if (!$registros) {
    $_SESSION['flash_horario_funcionamento'] = [
        'tipo' => 'danger',
        'mensagem' => 'Defina pelo menos um dia de funcionamento.',
        'erros' => [],
        'old' => $old,
    ];

    header('Location: ../horario-funcionamento.php');
    exit;
}

$pdo->beginTransaction();

try {
    $stmtDesativar = $pdo->prepare(
        'UPDATE empresa_horarios
         SET ativo = 0
         WHERE empresa_id = :empresa_id
           AND ativo = 1'
    );

    $stmtDesativar->execute([
        ':empresa_id' => $empresaId,
    ]);

    $stmtInsert = $pdo->prepare(
        'INSERT INTO empresa_horarios (
            empresa_id,
            dia_semana,
            hora_inicio,
            hora_fim,
            ativo
        ) VALUES (
            :empresa_id,
            :dia_semana,
            :hora_inicio,
            :hora_fim,
            1
        )'
    );

    foreach ($registros as $registro) {
        $stmtInsert->execute([
            ':empresa_id' => $empresaId,
            ':dia_semana' => $registro['dia_semana'],
            ':hora_inicio' => $registro['hora_inicio'],
            ':hora_fim' => $registro['hora_fim'],
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $e;
}

$_SESSION['csrf_horario_funcionamento'] = bin2hex(random_bytes(32));

$_SESSION['flash_horario_funcionamento'] = [
    'tipo' => 'success',
    'mensagem' => 'Horário de funcionamento salvo com sucesso.',
    'erros' => [],
];

header('Location: ../horario-funcionamento.php');
exit;
