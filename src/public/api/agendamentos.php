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

$agendamentoId = filter_input(INPUT_POST, 'agendamento_id', FILTER_VALIDATE_INT);
$agendamentoServicoId = filter_input(INPUT_POST, 'agendamento_servico_id', FILTER_VALIDATE_INT);
$statusNovo = trim((string) ($_POST['status'] ?? ''));
$retornoData = trim((string) ($_POST['retorno_data'] ?? ''));
$retornoUrl = '../agendamentos.php';
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $retornoData)) {
    $dataRetorno = DateTimeImmutable::createFromFormat('!Y-m-d', $retornoData);
    if ($dataRetorno !== false && $dataRetorno->format('Y-m-d') === $retornoData) {
        $retornoUrl = '../agenda-dia.php?data=' . rawurlencode($retornoData);
    }
}

$transicoesItemOperacao = [
    'agendado' => ['confirmado', 'cancelado', 'nao_compareceu'],
    'confirmado' => ['em_atendimento', 'cancelado', 'nao_compareceu'],
    'em_atendimento' => ['concluido'],
    'concluido' => [],
    'cancelado' => [],
    'nao_compareceu' => [],
];

$transicoesItemProfissional = [
    'confirmado' => ['em_atendimento'],
    'em_atendimento' => ['concluido'],
];

$statusPermitidos = ['confirmado', 'em_atendimento', 'concluido', 'cancelado', 'nao_compareceu'];

if (!$agendamentoId || !$agendamentoServicoId || !in_array($statusNovo, $statusPermitidos, true)) {
    $_SESSION['flash_agendamentos'] = [
        'tipo' => 'danger',
        'mensagem' => 'Não foi possível atualizar o atendimento.',
    ];
    header('Location: ' . $retornoUrl);
    exit;
}

function consolidarStatusAgendamento(array $statusItens): string
{
    if (!$statusItens) {
        return 'pendente';
    }

    if (in_array('em_atendimento', $statusItens, true)) {
        return 'em_atendimento';
    }

    $ativos = array_values(array_filter(
        $statusItens,
        static fn (string $status): bool => !in_array($status, ['cancelado', 'nao_compareceu'], true)
    ));

    if ($ativos && count(array_filter($ativos, static fn (string $s): bool => $s === 'concluido')) === count($ativos)) {
        return 'concluido';
    }

    if (in_array('concluido', $statusItens, true)) {
        return 'em_atendimento';
    }

    if (in_array('confirmado', $statusItens, true)) {
        return 'confirmado';
    }

    if (in_array('agendado', $statusItens, true)) {
        return 'pendente';
    }

    if (count(array_filter($statusItens, static fn (string $s): bool => $s === 'nao_compareceu')) === count($statusItens)) {
        return 'nao_compareceu';
    }

    if (count(array_filter($statusItens, static fn (string $s): bool => $s === 'cancelado')) === count($statusItens)) {
        return 'cancelado';
    }

    return in_array('nao_compareceu', $statusItens, true) ? 'nao_compareceu' : 'cancelado';
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
        'SELECT a.id, a.status
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

    $statusGeralAnterior = (string) $agendamento['status'];

    $stmtItem = $pdo->prepare(
        'SELECT ags.id, ags.profissional_id, ags.status
         FROM agendamento_servicos ags
         INNER JOIN profissionais pr
           ON pr.id = ags.profissional_id
          AND pr.empresa_id = :empresa_id
         WHERE ags.id = :item_id
           AND ags.agendamento_id = :agendamento_id
         LIMIT 1
         FOR UPDATE'
    );
    $stmtItem->execute([
        ':empresa_id' => $empresaId,
        ':item_id' => $agendamentoServicoId,
        ':agendamento_id' => $agendamentoId,
    ]);
    $item = $stmtItem->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new RuntimeException('Serviço do agendamento não encontrado.');
    }

    $profissionalItemId = (int) $item['profissional_id'];
    $statusItemAnterior = (string) $item['status'];

    // Compatibilidade com registros anteriores ao fluxo por serviço:
    // o cabeçalho avançava, mas o item permanecia como "agendado".
    if ($statusItemAnterior === 'agendado' && $statusGeralAnterior !== 'pendente') {
        $mapaLegado = [
            'confirmado' => 'confirmado',
            'em_atendimento' => 'em_atendimento',
            'concluido' => 'concluido',
            'cancelado' => 'cancelado',
            'nao_compareceu' => 'nao_compareceu',
        ];
        $statusLegado = $mapaLegado[$statusGeralAnterior] ?? null;

        if ($statusLegado !== null) {
            $camposLegado = ['status = :status'];
            if ($statusLegado === 'em_atendimento') {
                $camposLegado[] = 'iniciado_em = COALESCE(iniciado_em, NOW())';
            } elseif ($statusLegado === 'concluido') {
                $camposLegado[] = 'iniciado_em = COALESCE(iniciado_em, inicio)';
                $camposLegado[] = 'concluido_em = COALESCE(concluido_em, fim)';
            }

            $stmtCompat = $pdo->prepare(
                'UPDATE agendamento_servicos SET ' . implode(', ', $camposLegado) . ' WHERE id = :item_id'
            );
            $stmtCompat->execute([
                ':status' => $statusLegado,
                ':item_id' => $agendamentoServicoId,
            ]);
            $statusItemAnterior = $statusLegado;
        }
    }

    if ($ehProfissional && $profissionalItemId !== $profissionalSessaoId) {
        throw new RuntimeException('Você só pode administrar serviços da sua própria agenda.');
    }

    if ($statusItemAnterior === $statusNovo) {
        throw new RuntimeException('Este serviço já está com esse status.');
    }

    $transicoesPermitidas = $ehProfissional
        ? ($transicoesItemProfissional[$statusItemAnterior] ?? [])
        : ($transicoesItemOperacao[$statusItemAnterior] ?? []);

    if (!in_array($statusNovo, $transicoesPermitidas, true)) {
        throw new RuntimeException('Esta mudança de status não é permitida para este serviço.');
    }

    if ($statusNovo === 'em_atendimento') {
        $stmtEmAtendimento = $pdo->prepare(
            "SELECT ags2.id
             FROM agendamento_servicos ags2
             INNER JOIN agendamentos a2 ON a2.id = ags2.agendamento_id
             WHERE a2.empresa_id = :empresa_id
               AND ags2.profissional_id = :profissional_id
               AND ags2.status = 'em_atendimento'
               AND ags2.id <> :item_id
             LIMIT 1
             FOR UPDATE"
        );
        $stmtEmAtendimento->execute([
            ':empresa_id' => $empresaId,
            ':profissional_id' => $profissionalItemId,
            ':item_id' => $agendamentoServicoId,
        ]);

        if ($stmtEmAtendimento->fetchColumn()) {
            throw new RuntimeException('Finalize o atendimento atual primeiro.');
        }
    }

    $sqlUpdateItem = 'UPDATE agendamento_servicos SET status = :status';
    if ($statusNovo === 'em_atendimento') {
        $sqlUpdateItem .= ', iniciado_em = COALESCE(iniciado_em, NOW()), concluido_em = NULL';
    } elseif ($statusNovo === 'concluido') {
        $sqlUpdateItem .= ', concluido_em = NOW()';
    }
    $sqlUpdateItem .= ' WHERE id = :item_id AND agendamento_id = :agendamento_id';

    $stmtUpdateItem = $pdo->prepare($sqlUpdateItem);
    $stmtUpdateItem->execute([
        ':status' => $statusNovo,
        ':item_id' => $agendamentoServicoId,
        ':agendamento_id' => $agendamentoId,
    ]);

    if ($stmtUpdateItem->rowCount() !== 1) {
        throw new RuntimeException('O serviço do agendamento não foi atualizado.');
    }

    $stmtStatusItens = $pdo->prepare(
        'SELECT status FROM agendamento_servicos WHERE agendamento_id = :agendamento_id ORDER BY ordem FOR UPDATE'
    );
    $stmtStatusItens->execute([':agendamento_id' => $agendamentoId]);
    $statusItens = array_map('strval', $stmtStatusItens->fetchAll(PDO::FETCH_COLUMN));
    $statusGeralNovo = consolidarStatusAgendamento($statusItens);

    if ($statusGeralNovo !== $statusGeralAnterior) {
        $stmtUpdateGeral = $pdo->prepare(
            'UPDATE agendamentos SET status = :status WHERE id = :agendamento_id AND empresa_id = :empresa_id'
        );
        $stmtUpdateGeral->execute([
            ':status' => $statusGeralNovo,
            ':agendamento_id' => $agendamentoId,
            ':empresa_id' => $empresaId,
        ]);

        $stmtHistorico = $pdo->prepare(
            'INSERT INTO agendamento_historico (
                agendamento_id, status_anterior, status_novo, usuario_id, observacao
             ) VALUES (
                :agendamento_id, :status_anterior, :status_novo, :usuario_id, :observacao
             )'
        );
        $stmtHistorico->execute([
            ':agendamento_id' => $agendamentoId,
            ':status_anterior' => $statusGeralAnterior,
            ':status_novo' => $statusGeralNovo,
            ':usuario_id' => $usuarioId > 0 ? $usuarioId : null,
            ':observacao' => 'Status geral sincronizado após atualização do serviço #' . $agendamentoServicoId
                . ' (' . $contextoAtual . '): ' . $statusItemAnterior . ' → ' . $statusNovo . '.',
        ]);
    }

    $pdo->commit();

    $_SESSION['csrf_agendamentos'] = bin2hex(random_bytes(32));
    $_SESSION['flash_agendamentos'] = [
        'tipo' => 'success',
        'mensagem' => 'Status do serviço atualizado com sucesso.',
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

header('Location: ' . $retornoUrl);
exit;
