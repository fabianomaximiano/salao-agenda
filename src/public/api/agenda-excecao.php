<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

exigirAdministrador();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método não permitido.');
}

$empresaId = (int) ($_SESSION['empresa_id'] ?? 0);
$usuarioId = (int) ($_SESSION['user_id'] ?? 0);
$csrf = (string) ($_POST['csrf_token'] ?? '');

if ($empresaId <= 0 || $usuarioId <= 0
    || empty($_SESSION['csrf_agenda_excecao'])
    || !hash_equals((string) $_SESSION['csrf_agenda_excecao'], $csrf)) {
    http_response_code(403);
    exit('Requisição inválida.');
}

$data = trim((string) ($_POST['data_excecao'] ?? ''));
$acao = trim((string) ($_POST['acao'] ?? ''));
$descricao = trim((string) ($_POST['descricao'] ?? ''));
$mes = preg_match('/^\d{4}-\d{2}$/', (string) ($_POST['mes_retorno'] ?? ''))
    ? (string) $_POST['mes_retorno'] : substr($data, 0, 7);

$dataObj = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
if (!$dataObj || $dataObj->format('Y-m-d') !== $data
    || !in_array($acao, ['normal', 'fechado', 'horario_especial'], true)
    || mb_strlen($descricao) > 255) {
    $_SESSION['flash_error'] = 'Dados do dia especial são inválidos.';
    header('Location: ../agenda.php?mes=' . rawurlencode($mes));
    exit;
}

$timezoneEmpresa = 'America/Sao_Paulo';
$stmtTimezone = getDB()->prepare('SELECT timezone FROM empresas WHERE id = :empresa_id LIMIT 1');
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
$dataNaEmpresa = new DateTimeImmutable($data . ' 00:00:00', $timezone);

if ($dataNaEmpresa < $hoje) {
    $_SESSION['flash_error'] = 'Datas passadas não podem ter o funcionamento alterado.';
    header('Location: ../agenda.php?mes=' . rawurlencode($mes));
    exit;
}

$periodos = [];
if ($acao === 'horario_especial') {
    $inicios = is_array($_POST['hora_inicio'] ?? null) ? $_POST['hora_inicio'] : [];
    $fins = is_array($_POST['hora_fim'] ?? null) ? $_POST['hora_fim'] : [];

    for ($i = 0; $i < 2; $i++) {
        $inicio = trim((string) ($inicios[$i] ?? ''));
        $fim = trim((string) ($fins[$i] ?? ''));
        if ($inicio === '' && $fim === '') continue;

        if ($inicio === '' || $fim === ''
            || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $inicio)
            || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $fim)
            || $fim <= $inicio) {
            $_SESSION['flash_error'] = 'Informe períodos especiais válidos.';
            header('Location: ../agenda.php?mes=' . rawurlencode($mes));
            exit;
        }
        $periodos[] = [$inicio, $fim];
    }

    if (!$periodos) {
        $_SESSION['flash_error'] = 'Informe pelo menos um período especial.';
        header('Location: ../agenda.php?mes=' . rawurlencode($mes));
        exit;
    }

    usort($periodos, static fn(array $a, array $b): int => strcmp($a[0], $b[0]));
    for ($i = 1; $i < count($periodos); $i++) {
        if ($periodos[$i][0] < $periodos[$i - 1][1]) {
            $_SESSION['flash_error'] = 'Os períodos especiais não podem se sobrepor.';
            header('Location: ../agenda.php?mes=' . rawurlencode($mes));
            exit;
        }
    }
}

$retorno = ['mes' => $mes];
foreach (['profissional_retorno' => 'profissional', 'servico_retorno' => 'servico'] as $post => $get) {
    $id = filter_var($_POST[$post] ?? null, FILTER_VALIDATE_INT);
    if (is_int($id) && $id > 0) $retorno[$get] = $id;
}
$url = '../agenda.php?' . http_build_query($retorno);
$pdo = getDB();

try {
    $pdo->beginTransaction();

    // Serializa a alteração do funcionamento com os fluxos de agendamento,
    // que usam a linha do profissional como mutex.
    $stmt = $pdo->prepare(
        'SELECT id
         FROM profissionais
         WHERE empresa_id = :empresa_id
           AND ativo = 1
         ORDER BY id
         FOR UPDATE'
    );
    $stmt->execute([':empresa_id' => $empresaId]);
    $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare(
        'SELECT id FROM empresa_excecoes
         WHERE empresa_id = :empresa_id AND data_excecao = :data
         LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([':empresa_id' => $empresaId, ':data' => $data]);
    $id = (int) ($stmt->fetchColumn() ?: 0);

    if ($acao === 'normal') {
        if ($id > 0) {
            $del = $pdo->prepare('DELETE FROM empresa_excecoes WHERE id = :id AND empresa_id = :empresa_id');
            $del->execute([':id' => $id, ':empresa_id' => $empresaId]);
        }
        $pdo->commit();
        $_SESSION['flash_success'] = 'Funcionamento normal restaurado para esta data.';
        header('Location: ' . $url);
        exit;
    }

    $tipo = $acao === 'fechado' ? 'fechado' : 'horario_especial';

    $diaInicio = $data . ' 00:00:00';
    $diaFim = (new DateTimeImmutable($data . ' 00:00:00', $timezone))
        ->modify('+1 day')
        ->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        "SELECT ags.inicio, ags.fim
         FROM agendamento_servicos ags
         INNER JOIN agendamentos a ON a.id = ags.agendamento_id
         WHERE a.empresa_id = :empresa_id
           AND ags.inicio < :dia_fim
           AND ags.fim > :dia_inicio
           AND a.status NOT IN ('cancelado', 'nao_compareceu')
           AND ags.status NOT IN ('cancelado', 'nao_compareceu')
         FOR UPDATE"
    );
    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':dia_fim' => $diaFim,
        ':dia_inicio' => $diaInicio,
    ]);
    $agendamentosAtivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($tipo === 'fechado' && $agendamentosAtivos) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = 'Existem agendamentos ativos nesta data. Remarque ou cancele os atendimentos antes de marcar o salão como fechado.';
        header('Location: ' . $url);
        exit;
    }

    if ($tipo === 'horario_especial') {
        foreach ($agendamentosAtivos as $agendamento) {
            $inicioAgendamento = new DateTimeImmutable((string) $agendamento['inicio'], $timezone);
            $fimAgendamento = new DateTimeImmutable((string) $agendamento['fim'], $timezone);
            $dentroDePeriodoEspecial = false;

            foreach ($periodos as [$inicioPeriodo, $fimPeriodo]) {
                $inicioEspecial = new DateTimeImmutable($data . ' ' . $inicioPeriodo . ':00', $timezone);
                $fimEspecial = new DateTimeImmutable($data . ' ' . $fimPeriodo . ':00', $timezone);

                if ($inicioAgendamento >= $inicioEspecial && $fimAgendamento <= $fimEspecial) {
                    $dentroDePeriodoEspecial = true;
                    break;
                }
            }

            if (!$dentroDePeriodoEspecial) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = 'Existem agendamentos ativos fora do horário especial informado. Remarque ou cancele os atendimentos conflitantes antes de alterar o funcionamento.';
                header('Location: ' . $url);
                exit;
            }
        }
    }

    if ($id > 0) {
        $up = $pdo->prepare(
            'UPDATE empresa_excecoes
             SET tipo=:tipo, descricao=:descricao, ativo=1, criado_por_usuario_id=:usuario
             WHERE id=:id AND empresa_id=:empresa_id'
        );
        $up->execute([
            ':tipo'=>$tipo, ':descricao'=>$descricao !== '' ? $descricao : null,
            ':usuario'=>$usuarioId, ':id'=>$id, ':empresa_id'=>$empresaId
        ]);
        $delp = $pdo->prepare('DELETE FROM empresa_excecao_periodos WHERE empresa_excecao_id=:id');
        $delp->execute([':id'=>$id]);
    } else {
        $ins = $pdo->prepare(
            'INSERT INTO empresa_excecoes
             (empresa_id,data_excecao,tipo,descricao,ativo,criado_por_usuario_id)
             VALUES (:empresa_id,:data,:tipo,:descricao,1,:usuario)'
        );
        $ins->execute([
            ':empresa_id'=>$empresaId, ':data'=>$data, ':tipo'=>$tipo,
            ':descricao'=>$descricao !== '' ? $descricao : null, ':usuario'=>$usuarioId
        ]);
        $id = (int) $pdo->lastInsertId();
    }

    if ($tipo === 'horario_especial') {
        $insp = $pdo->prepare(
            'INSERT INTO empresa_excecao_periodos
             (empresa_excecao_id,hora_inicio,hora_fim) VALUES (:id,:inicio,:fim)'
        );
        foreach ($periodos as [$inicio,$fim]) {
            $insp->execute([':id'=>$id, ':inicio'=>$inicio.':00', ':fim'=>$fim.':00']);
        }
    }

    $pdo->commit();
    $_SESSION['flash_success'] = $tipo === 'fechado'
        ? 'Data marcada como fechada.'
        : 'Horário especial salvo com sucesso.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Erro ao salvar exceção da empresa: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Não foi possível atualizar o funcionamento desta data.';
}

header('Location: ' . $url);
exit;
