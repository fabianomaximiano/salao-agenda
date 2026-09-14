<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

exigirAcesso('agenda');

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

$statusPermitidos = [
    'pendente',
    'confirmado',
    'em_atendimento',
    'concluido',
    'cancelado',
    'nao_compareceu',
];

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
$pdo = getDB();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT
            id,
            status
         FROM agendamentos
         WHERE id = :agendamento_id
           AND empresa_id = :empresa_id
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

    if ($statusAnterior === $statusNovo) {
        $pdo->commit();

        $_SESSION['flash_agendamentos'] = [
            'tipo' => 'success',
            'mensagem' => 'O agendamento já estava com esse status.',
        ];

        header('Location: ../agendamentos.php');
        exit;
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
        ':observacao' => 'Status alterado pelo painel.',
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
