<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

exigirAcesso('agenda');

$contextoAtual = (string) ($_SESSION['contexto'] ?? '');
if (!in_array($contextoAtual, ['administrador', 'colaborador'], true)) {
    http_response_code(403);
    exit('Acesso não autorizado.');
}

header('Content-Type: application/json; charset=utf-8');

$pdo = getDB();
$empresaId = (int) $_SESSION['empresa_id'];
$usuarioId = (int) $_SESSION['user_id'];

function responderRecepcao(array $dados, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function dataValidaRecepcao(string $data): bool
{
    $obj = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
    return $obj !== false && $obj->format('Y-m-d') === $data;
}

function sobrepoeRecepcao(DateTimeImmutable $inicioA, DateTimeImmutable $fimA, DateTimeImmutable $inicioB, DateTimeImmutable $fimB): bool
{
    return $inicioA < $fimB && $fimA > $inicioB;
}

function dadosServicoRecepcao(PDO $pdo, int $empresaId, int $servicoId, int $profissionalId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT
            s.nome,
            s.intervalo_minutos,
            COALESCE(ps.duracao_minutos, s.duracao_minutos) AS duracao,
            COALESCE(ps.preco, s.preco) AS preco
         FROM servicos s
         INNER JOIN profissional_servicos ps
           ON ps.servico_id = s.id
          AND ps.profissional_id = :profissional_id
          AND ps.ativo = 1
         INNER JOIN profissionais pr
           ON pr.id = ps.profissional_id
          AND pr.empresa_id = s.empresa_id
          AND pr.ativo = 1
         INNER JOIN pessoas p
           ON p.id = pr.pessoa_id
          AND p.empresa_id = pr.empresa_id
          AND p.ativo = 1
         WHERE s.id = :servico_id
           AND s.empresa_id = :empresa_id
           AND s.ativo = 1
         LIMIT 1'
    );
    $stmt->execute([
        ':profissional_id' => $profissionalId,
        ':servico_id' => $servicoId,
        ':empresa_id' => $empresaId,
    ]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);
    return $dados ?: null;
}

function horariosRecepcao(PDO $pdo, int $empresaId, int $servicoId, int $profissionalId, string $data): array
{
    $servico = dadosServicoRecepcao($pdo, $empresaId, $servicoId, $profissionalId);
    if (!$servico || new DateTimeImmutable($data) < new DateTimeImmutable('today')) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT id, tipo
         FROM empresa_excecoes
         WHERE empresa_id = :empresa_id
           AND data_excecao = :data
           AND ativo = 1
         LIMIT 1'
    );
    $stmt->execute([':empresa_id' => $empresaId, ':data' => $data]);
    $excecao = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($excecao && $excecao['tipo'] === 'fechado') {
        return [];
    }

    $diaSemana = (int) (new DateTimeImmutable($data))->format('N');

    if ($excecao) {
        $stmt = $pdo->prepare(
            'SELECT hora_inicio, hora_fim
             FROM empresa_excecao_periodos
             WHERE empresa_excecao_id = :id
             ORDER BY hora_inicio'
        );
        $stmt->execute([':id' => (int) $excecao['id']]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT hora_inicio, hora_fim
             FROM empresa_horarios
             WHERE empresa_id = :empresa_id
               AND dia_semana = :dia_semana
               AND ativo = 1
             ORDER BY hora_inicio'
        );
        $stmt->execute([':empresa_id' => $empresaId, ':dia_semana' => $diaSemana]);
    }
    $periodosEmpresa = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$periodosEmpresa) return [];

    $stmt = $pdo->prepare(
        'SELECT hora_inicio, hora_fim
         FROM profissional_horarios
         WHERE profissional_id = :profissional_id
           AND dia_semana = :dia_semana
           AND ativo = 1
         ORDER BY hora_inicio'
    );
    $stmt->execute([':profissional_id' => $profissionalId, ':dia_semana' => $diaSemana]);
    $periodosProfissional = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$periodosProfissional) return [];

    $diaInicio = $data . ' 00:00:00';
    $diaFim = (new DateTimeImmutable($diaInicio))->modify('+1 day')->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        'SELECT inicio, fim
         FROM profissional_bloqueios
         WHERE profissional_id = :profissional_id
           AND inicio < :dia_fim
           AND fim > :dia_inicio'
    );
    $stmt->execute([
        ':profissional_id' => $profissionalId,
        ':dia_fim' => $diaFim,
        ':dia_inicio' => $diaInicio,
    ]);
    $ocupados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT ags.inicio, ags.fim
         FROM agendamento_servicos ags
         INNER JOIN agendamentos a ON a.id = ags.agendamento_id
         WHERE a.empresa_id = :empresa_id
           AND ags.profissional_id = :profissional_id
           AND ags.inicio < :dia_fim
           AND ags.fim > :dia_inicio
           AND a.status NOT IN ('cancelado', 'nao_compareceu')
           AND ags.status NOT IN ('cancelado', 'nao_compareceu')"
    );
    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':profissional_id' => $profissionalId,
        ':dia_fim' => $diaFim,
        ':dia_inicio' => $diaInicio,
    ]);
    $ocupados = array_merge($ocupados, $stmt->fetchAll(PDO::FETCH_ASSOC));

    $resultado = [];
    $duracao = (int) $servico['duracao'];
    $intervalo = (int) $servico['intervalo_minutos'];
    $agora = new DateTimeImmutable();

    foreach ($periodosEmpresa as $periodoEmpresa) {
        foreach ($periodosProfissional as $periodoProfissional) {
            $inicio = max(
                new DateTimeImmutable($data . ' ' . $periodoEmpresa['hora_inicio']),
                new DateTimeImmutable($data . ' ' . $periodoProfissional['hora_inicio'])
            );
            $fim = min(
                new DateTimeImmutable($data . ' ' . $periodoEmpresa['hora_fim']),
                new DateTimeImmutable($data . ' ' . $periodoProfissional['hora_fim'])
            );

            for ($cursor = $inicio; ; $cursor = $cursor->modify('+15 minutes')) {
                $fimServico = $cursor->modify('+' . $duracao . ' minutes');
                $fimReserva = $fimServico->modify('+' . $intervalo . ' minutes');
                if ($fimReserva > $fim) break;
                if ($cursor <= $agora) continue;

                $livre = true;
                foreach ($ocupados as $ocupado) {
                    if (sobrepoeRecepcao(
                        $cursor,
                        $fimReserva,
                        new DateTimeImmutable((string) $ocupado['inicio']),
                        new DateTimeImmutable((string) $ocupado['fim'])
                    )) {
                        $livre = false;
                        break;
                    }
                }

                if ($livre) {
                    $resultado[] = [
                        'hora' => $cursor->format('H:i'),
                        'inicio' => $cursor->format('Y-m-d H:i:s'),
                        'fim' => $fimServico->format('Y-m-d H:i:s'),
                    ];
                }
            }
        }
    }

    return $resultado;
}

$acao = trim((string) ($_GET['acao'] ?? $_POST['acao'] ?? ''));

try {
    if ($acao === 'servicos') {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT s.id, s.nome
             FROM servicos s
             INNER JOIN profissional_servicos ps
               ON ps.servico_id = s.id
              AND ps.ativo = 1
             INNER JOIN profissionais pr
               ON pr.id = ps.profissional_id
              AND pr.empresa_id = s.empresa_id
              AND pr.ativo = 1
             INNER JOIN pessoas p
               ON p.id = pr.pessoa_id
              AND p.empresa_id = pr.empresa_id
              AND p.ativo = 1
             WHERE s.empresa_id = :empresa_id
               AND s.ativo = 1
             ORDER BY s.nome'
        );
        $stmt->execute([':empresa_id' => $empresaId]);
        responderRecepcao(['ok' => true, 'servicos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($acao === 'profissionais') {
        $servicoId = filter_input(INPUT_GET, 'servico_id', FILTER_VALIDATE_INT);
        if (!$servicoId) responderRecepcao(['ok' => false, 'mensagem' => 'Serviço inválido.'], 422);

        $stmt = $pdo->prepare(
            'SELECT
                pr.id,
                p.nome_completo,
                COALESCE(ps.preco, s.preco) AS preco,
                COALESCE(ps.duracao_minutos, s.duracao_minutos) AS duracao_minutos
             FROM profissional_servicos ps
             INNER JOIN profissionais pr
               ON pr.id = ps.profissional_id
              AND pr.ativo = 1
             INNER JOIN pessoas p
               ON p.id = pr.pessoa_id
              AND p.empresa_id = pr.empresa_id
              AND p.ativo = 1
             INNER JOIN servicos s
               ON s.id = ps.servico_id
              AND s.empresa_id = pr.empresa_id
              AND s.ativo = 1
             WHERE pr.empresa_id = :empresa_id
               AND ps.servico_id = :servico_id
               AND ps.ativo = 1
             ORDER BY p.nome_completo'
        );
        $stmt->execute([':empresa_id' => $empresaId, ':servico_id' => $servicoId]);
        responderRecepcao(['ok' => true, 'profissionais' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($acao === 'horarios') {
        $servicoId = filter_input(INPUT_GET, 'servico_id', FILTER_VALIDATE_INT);
        $profissionalId = filter_input(INPUT_GET, 'profissional_id', FILTER_VALIDATE_INT);
        $data = trim((string) ($_GET['data'] ?? ''));

        if (!$servicoId || !$profissionalId || !dataValidaRecepcao($data)) {
            responderRecepcao(['ok' => false, 'mensagem' => 'Dados inválidos.'], 422);
        }

        responderRecepcao([
            'ok' => true,
            'horarios' => horariosRecepcao($pdo, $empresaId, $servicoId, $profissionalId, $data),
        ]);
    }

    if ($acao === 'confirmar') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responderRecepcao(['ok' => false, 'mensagem' => 'Método não permitido.'], 405);
        }

        $csrf = (string) ($_POST['csrf_token'] ?? '');
        $csrfSessao = (string) ($_SESSION['csrf_agendamento_recepcao'] ?? '');
        if ($csrf === '' || $csrfSessao === '' || !hash_equals($csrfSessao, $csrf)) {
            responderRecepcao(['ok' => false, 'mensagem' => 'Token CSRF inválido.'], 403);
        }

        $clienteId = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
        $servicoId = filter_input(INPUT_POST, 'servico_id', FILTER_VALIDATE_INT);
        $profissionalId = filter_input(INPUT_POST, 'profissional_id', FILTER_VALIDATE_INT);
        $data = trim((string) ($_POST['data'] ?? ''));
        $hora = trim((string) ($_POST['hora'] ?? ''));
        $observacoes = trim((string) ($_POST['observacoes_internas'] ?? ''));

        if (
            !$clienteId || !$servicoId || !$profissionalId
            || !dataValidaRecepcao($data)
            || !preg_match('/^\d{2}:\d{2}$/', $hora)
        ) {
            responderRecepcao(['ok' => false, 'mensagem' => 'Preencha todos os dados do agendamento.'], 422);
        }

        if (mb_strlen($observacoes) > 2000) {
            responderRecepcao(['ok' => false, 'mensagem' => 'A observação é muito longa.'], 422);
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT c.id
             FROM clientes c
             INNER JOIN pessoas p
               ON p.id = c.pessoa_id
              AND p.empresa_id = c.empresa_id
             WHERE c.id = :cliente_id
               AND c.empresa_id = :empresa_id
               AND c.ativo = 1
               AND p.ativo = 1
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([':cliente_id' => $clienteId, ':empresa_id' => $empresaId]);
        if (!$stmt->fetchColumn()) throw new RuntimeException('Cliente indisponível para agendamento.');

        $stmt = $pdo->prepare(
            'SELECT id
             FROM profissionais
             WHERE id = :profissional_id
               AND empresa_id = :empresa_id
               AND ativo = 1
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([':profissional_id' => $profissionalId, ':empresa_id' => $empresaId]);
        if (!$stmt->fetchColumn()) throw new RuntimeException('Profissional indisponível.');

        $servico = dadosServicoRecepcao($pdo, $empresaId, $servicoId, $profissionalId);
        if (!$servico) throw new RuntimeException('Serviço indisponível para este profissional.');

        $horarioEscolhido = null;
        foreach (horariosRecepcao($pdo, $empresaId, $servicoId, $profissionalId, $data) as $slot) {
            if ($slot['hora'] === $hora) {
                $horarioEscolhido = $slot;
                break;
            }
        }
        if (!$horarioEscolhido) throw new RuntimeException('Este horário não está mais disponível.');

        $stmt = $pdo->prepare(
            "INSERT INTO agendamentos (
                empresa_id, cliente_id, inicio, fim, status, origem,
                observacoes_internas, valor_total, criado_por_usuario_id
             ) VALUES (
                :empresa_id, :cliente_id, :inicio, :fim, 'confirmado', 'recepcao',
                :observacoes, :valor_total, :usuario_id
             )"
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':cliente_id' => $clienteId,
            ':inicio' => $horarioEscolhido['inicio'],
            ':fim' => $horarioEscolhido['fim'],
            ':observacoes' => $observacoes !== '' ? $observacoes : null,
            ':valor_total' => $servico['preco'],
            ':usuario_id' => $usuarioId > 0 ? $usuarioId : null,
        ]);
        $agendamentoId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            "INSERT INTO agendamento_servicos (
                agendamento_id, servico_id, profissional_id, status,
                inicio, fim, duracao_minutos, valor, ordem
             ) VALUES (
                :agendamento_id, :servico_id, :profissional_id, 'confirmado',
                :inicio, :fim, :duracao, :valor, 1
             )"
        );
        $stmt->execute([
            ':agendamento_id' => $agendamentoId,
            ':servico_id' => $servicoId,
            ':profissional_id' => $profissionalId,
            ':inicio' => $horarioEscolhido['inicio'],
            ':fim' => $horarioEscolhido['fim'],
            ':duracao' => (int) $servico['duracao'],
            ':valor' => $servico['preco'],
        ]);

        $stmt = $pdo->prepare(
            "INSERT INTO agendamento_historico (
                agendamento_id, status_anterior, status_novo, usuario_id, observacao
             ) VALUES (
                :agendamento_id, NULL, 'confirmado', :usuario_id,
                'Agendamento criado pela recepção.'
             )"
        );
        $stmt->execute([
            ':agendamento_id' => $agendamentoId,
            ':usuario_id' => $usuarioId > 0 ? $usuarioId : null,
        ]);

        $pdo->commit();
        $_SESSION['csrf_agendamento_recepcao'] = bin2hex(random_bytes(32));

        responderRecepcao([
            'ok' => true,
            'mensagem' => 'Agendamento confirmado com sucesso.',
            'agendamento_id' => $agendamentoId,
            'csrf_token' => $_SESSION['csrf_agendamento_recepcao'],
        ]);
    }

    responderRecepcao(['ok' => false, 'mensagem' => 'Ação inválida.'], 400);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    responderRecepcao(['ok' => false, 'mensagem' => $e->getMessage()], 409);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Erro no agendamento da recepção: ' . $e->getMessage());
    responderRecepcao(['ok' => false, 'mensagem' => 'Não foi possível concluir o agendamento agora.'], 500);
}
