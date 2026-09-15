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
