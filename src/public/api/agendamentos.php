<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

exigirLogin();

$contextoAtual = (string) ($_SESSION['contexto'] ?? '');
$ehAdministrador = $contextoAtual === 'administrador';
$ehColaborador = $contextoAtual === 'colaborador';
$ehProfissional = $contextoAtual === 'profissional';

if ($ehColaborador) {
    exigirAcesso('agenda');
} elseif (!$ehAdministrador && !$ehProfissional) {
    http_response_code(403);
    exit('Acesso não autorizado.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método não permitido.');
}

$csrfRecebido = (string) ($_POST['csrf_token'] ?? '');
$csrfSessao = (string) ($_SESSION['csrf_agendamentos'] ?? '');

if (
    $csrfRecebido === ''
    || $csrfSessao === ''
    || !hash_equals($csrfSessao, $csrfRecebido)
) {
    http_response_code(403);
    exit('Token CSRF inválido.');
}

$acao = trim((string) ($_POST['acao'] ?? ''));

if ($acao !== 'alterar_status') {
    http_response_code(400);
    exit('Ação inválida.');
}

$agendamentoId = filter_input(
    INPUT_POST,
    'agendamento_id',
    FILTER_VALIDATE_INT
);

$statusNovo = trim((string) ($_POST['status'] ?? ''));

$transicoesOperacao = [
    'pendente' => ['confirmado', 'cancelado', 'nao_compareceu'],
    'confirmado' => ['em_atendimento', 'cancelado', 'nao_compareceu'],
    'em_atendimento' => ['concluido'],
    'concluido' => [],
    'cancelado' => [],
    'nao_compareceu' => [],
];

$transicoesProfissional = [
    'confirmado' => ['em_atendimento'],
    'em_atendimento' => ['concluido'],
];

$statusPermitidos = array_keys($transicoesOperacao);

if (!$agendamentoId || !in_array($statusNovo, $statusPermitidos, true)) {
    $_SESSION['flash_agendamentos'] = [
        'tipo' => 'danger',
        'mensagem' => 'Não foi possível atualizar o agendamento.',
    ];

    header('Location: ../agendamentos.php');
    exit;
}

$empresaId = (int) $_SESSION['empresa_id'];
$usuarioId = (int) $_SESSION['user_id'];
$profissionalSessaoId = $ehProfissional ? (int) ($_SESSION['profissional_id'] ?? 0) : 0;
$pdo = getDB();

if ($ehProfissional && $profissionalSessaoId <= 0) {
    http_response_code(403);
    exit('Profissional não identificado.');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT
            a.id,
            a.status
         FROM agendamentos a
         WHERE a.id = :agendamento_id
           AND a.empresa_id = :empresa_id
         LIMIT 1
         FOR UPDATE'
    );

    $stmt->execute([
        ':agendamento_id' => $agendamentoId,
        ':empresa_id' => $empresaId,
    ]);

    $agendamento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$agendamento) {
        throw new RuntimeException('Agendamento não encontrado.');
    }

    $statusAnterior = (string) $agendamento['status'];

    $stmtProfissionais = $pdo->prepare(
        'SELECT DISTINCT profissional_id
         FROM agendamento_servicos
         WHERE agendamento_id = :agendamento_id
           AND profissional_id IS NOT NULL
         FOR UPDATE'
    );
    $stmtProfissionais->execute([':agendamento_id' => $agendamentoId]);
    $profissionaisAgendamento = array_map(
        'intval',
        $stmtProfissionais->fetchAll(PDO::FETCH_COLUMN)
    );

    if ($ehProfissional && !in_array($profissionalSessaoId, $profissionaisAgendamento, true)) {
        throw new RuntimeException('Você só pode administrar atendimentos da sua própria agenda.');
    }

    if ($statusAnterior === $statusNovo) {
        throw new RuntimeException('O agendamento já está com esse status.');
    }

    $transicoesPermitidas = $ehProfissional
        ? ($transicoesProfissional[$statusAnterior] ?? [])
        : ($transicoesOperacao[$statusAnterior] ?? []);

    if (!in_array($statusNovo, $transicoesPermitidas, true)) {
        throw new RuntimeException('Esta mudança de status não é permitida.');
    }

    if ($statusNovo === 'em_atendimento') {
        foreach ($profissionaisAgendamento as $profissionalId) {
            $stmtEmAtendimento = $pdo->prepare(
                'SELECT a2.id
                 FROM agendamentos a2
                 INNER JOIN agendamento_servicos ags2
                   ON ags2.agendamento_id = a2.id
                 WHERE a2.empresa_id = :empresa_id
                   AND a2.status = "em_atendimento"
                   AND a2.id <> :agendamento_id
                   AND ags2.profissional_id = :profissional_id
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmtEmAtendimento->execute([
                ':empresa_id' => $empresaId,
                ':agendamento_id' => $agendamentoId,
                ':profissional_id' => $profissionalId,
            ]);

            if ($stmtEmAtendimento->fetchColumn()) {
                throw new RuntimeException('Finalize o atendimento atual primeiro.');
            }
        }
    }

    $stmtUpdate = $pdo->prepare(
        'UPDATE agendamentos
         SET status = :status
         WHERE id = :agendamento_id
           AND empresa_id = :empresa_id'
    );

    $stmtUpdate->execute([
        ':status' => $statusNovo,
        ':agendamento_id' => $agendamentoId,
        ':empresa_id' => $empresaId,
    ]);

    if ($stmtUpdate->rowCount() !== 1) {
        throw new RuntimeException('O agendamento não foi atualizado.');
    }

    $stmtHistorico = $pdo->prepare(
        'INSERT INTO agendamento_historico (
            agendamento_id,
            status_anterior,
            status_novo,
            usuario_id,
            observacao
         ) VALUES (
            :agendamento_id,
            :status_anterior,
            :status_novo,
            :usuario_id,
            :observacao
         )'
    );

    $stmtHistorico->execute([
        ':agendamento_id' => $agendamentoId,
        ':status_anterior' => $statusAnterior,
        ':status_novo' => $statusNovo,
        ':usuario_id' => $usuarioId > 0 ? $usuarioId : null,
        ':observacao' => 'Status alterado pelo painel (' . $contextoAtual . '): ' . $statusAnterior . ' → ' . $statusNovo . '.',
    ]);

    $pdo->commit();

    $_SESSION['csrf_agendamentos'] = bin2hex(random_bytes(32));

    $_SESSION['flash_agendamentos'] = [
        'tipo' => 'success',
        'mensagem' => 'Status do agendamento atualizado com sucesso.',
    ];
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['flash_agendamentos'] = [
        'tipo' => 'danger',
        'mensagem' => $e->getMessage(),
    ];
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro ao atualizar agendamento id='
        . $agendamentoId
        . ': '
        . $e->getMessage()
    );

    $_SESSION['flash_agendamentos'] = [
        'tipo' => 'danger',
        'mensagem' => 'Não foi possível atualizar o agendamento agora.',
    ];
}

header('Location: ../agendamentos.php');
exit;
