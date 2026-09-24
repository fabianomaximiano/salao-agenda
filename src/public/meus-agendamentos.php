<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigirCliente();

$empresaId = (int) ($_SESSION['empresa_id'] ?? 0);
$clienteId = (int) ($_SESSION['cliente_id'] ?? 0);
$nome = trim((string) ($_SESSION['user_name'] ?? 'Cliente'));
$empresa = trim((string) ($_SESSION['empresa_nome'] ?? ''));

$pdo = getDB();
if (empty($_SESSION['csrf_autoagendamento'])) {
    $_SESSION['csrf_autoagendamento'] = bin2hex(random_bytes(32));
}

$timezoneEmpresa = 'America/Sao_Paulo';
$stmtTimezone = $pdo->prepare(
    'SELECT timezone FROM empresas WHERE id = :empresa_id LIMIT 1'
);
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

function hMeusAgendamentos(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

$sql = '
    SELECT
        a.id,
        a.inicio,
        a.fim,
        a.status,
        a.valor_total,
        GROUP_CONCAT(
            CONCAT(
                s.nome,
                "||",
                pp.nome_completo,
                "||",
                ags.status
            )
            ORDER BY ags.ordem ASC
            SEPARATOR "##"
        ) AS itens
    FROM agendamentos a
    INNER JOIN clientes c
      ON c.id = a.cliente_id
     AND c.empresa_id = a.empresa_id
    LEFT JOIN agendamento_servicos ags
      ON ags.agendamento_id = a.id
    LEFT JOIN servicos s
      ON s.id = ags.servico_id
     AND s.empresa_id = a.empresa_id
    LEFT JOIN profissionais pr
      ON pr.id = ags.profissional_id
     AND pr.empresa_id = a.empresa_id
    LEFT JOIN pessoas pp
      ON pp.id = pr.pessoa_id
     AND pp.empresa_id = pr.empresa_id
    WHERE a.empresa_id = :empresa_id
      AND a.cliente_id = :cliente_id
    GROUP BY
        a.id,
        a.inicio,
        a.fim,
        a.status,
        a.valor_total
    ORDER BY a.inicio DESC, a.id DESC
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':empresa_id' => $empresaId,
    ':cliente_id' => $clienteId,
]);

$todos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$agora = new DateTimeImmutable('now', $timezone);
$proximos = [];
$historico = [];

foreach ($todos as $agendamento) {
    $fim = new DateTimeImmutable((string) $agendamento['fim'], $timezone);
    $status = (string) $agendamento['status'];

    if (
        $fim >= $agora
        && !in_array($status, ['concluido', 'cancelado', 'nao_compareceu'], true)
    ) {
        $proximos[] = $agendamento;
    } else {
        $historico[] = $agendamento;
    }
}

usort(
    $proximos,
    static fn(array $a, array $b): int =>
        strcmp((string) $a['inicio'], (string) $b['inicio'])
);

$statusRotulos = [
    'pendente' => 'Pendente',
    'confirmado' => 'Confirmado',
    'em_atendimento' => 'Em atendimento',
    'concluido' => 'Concluído',
    'cancelado' => 'Cancelado',
    'nao_compareceu' => 'Não compareceu',
];

$statusClasses = [
    'pendente' => 'warning',
    'confirmado' => 'primary',
    'em_atendimento' => 'info',
    'concluido' => 'success',
    'cancelado' => 'secondary',
    'nao_compareceu' => 'danger',
];

$resumoHistorico = [
    'concluido' => 0,
    'cancelado' => 0,
    'nao_compareceu' => 0,
];

foreach ($historico as $agendamentoHistorico) {
    $statusHistorico = (string) ($agendamentoHistorico['status'] ?? '');
    if (array_key_exists($statusHistorico, $resumoHistorico)) {
        $resumoHistorico[$statusHistorico]++;
    }
}

function renderAgendamentoCliente(
    array $agendamento,
    array $statusRotulos,
    array $statusClasses,
    DateTimeZone $timezone
): void {
    $inicio = new DateTimeImmutable((string) $agendamento['inicio'], $timezone);
    $fim = new DateTimeImmutable((string) $agendamento['fim'], $timezone);
    $status = (string) $agendamento['status'];
    $agora = new DateTimeImmutable('now', $timezone);
    $podeAlterar = in_array($status,['pendente','confirmado'],true)
        && $agora < $inicio->modify('-2 hours');
    $itens = [];
    $itensBrutos = trim((string) ($agendamento['itens'] ?? ''));

    if ($itensBrutos !== '') {
        foreach (explode('##', $itensBrutos) as $itemBruto) {
            $partes = explode('||', $itemBruto);

            $itens[] = [
                'servico' => trim((string) ($partes[0] ?? '')),
                'profissional' => trim((string) ($partes[1] ?? '')),
                'status' => trim((string) ($partes[2] ?? '')),
            ];
        }
    }
    ?>
    <article class="cliente-agendamento-item">
        <div class="cliente-agendamento-data">
            <span><?= $inicio->format('d/m/Y') ?></span>
            <strong><?= $inicio->format('H:i') ?></strong>
            <small>até <?= $fim->format('H:i') ?></small>
        </div>

        <div class="cliente-agendamento-detalhes">
            <?php if ($itens): ?>
                <?php foreach ($itens as $item): ?>
                    <div class="cliente-agendamento-servico">
                        <strong>
                            <?= hMeusAgendamentos(
                                $item['servico'] !== '' ? $item['servico'] : 'Serviço'
                            ) ?>
                        </strong>

                        <span>
                            com
                            <?= hMeusAgendamentos(
                                $item['profissional'] !== ''
                                    ? $item['profissional']
                                    : 'profissional'
                            ) ?>
                        </span>
                        <?php if ($item['status'] !== '' && count($itens) > 1): ?>
                            <small class="cliente-agendamento-servico-status">
                                <?= hMeusAgendamentos($statusRotulos[$item['status']] ?? ucfirst(str_replace('_', ' ', $item['status']))) ?>
                            </small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="cliente-agendamento-servico">
                    <strong>Atendimento</strong>
                    <span>Detalhes indisponíveis.</span>
                </div>
            <?php endif; ?>

            <?php if ($podeAlterar): ?>
                <div class="mt-3 pt-3 border-top">
                    <a class="btn btn-outline-primary btn-sm mr-2" href="agendar.php?reagendar=<?= (int)$agendamento['id'] ?>">Reagendar</a>
                    <button type="button" class="btn btn-outline-danger btn-sm js-cancelar-agendamento" data-agendamento-id="<?= (int)$agendamento['id'] ?>">Cancelar</button>
                </div>
            <?php elseif (in_array($status,['pendente','confirmado'],true) && $inicio > $agora): ?>
                <div class="alert alert-light border mt-3 mb-0 py-2 px-3">Alterações encerradas — faltam menos de 2 horas para o atendimento.</div>
            <?php endif; ?>
            <div class="cliente-agendamento-meta">
                <span>
                    Valor:
                    <strong>
                        R$ <?= number_format(
                            (float) $agendamento['valor_total'],
                            2,
                            ',',
                            '.'
                        ) ?>
                    </strong>
                </span>

                <span class="badge badge-<?= hMeusAgendamentos(
                    $statusClasses[$status] ?? 'secondary'
                ) ?>">
                    <?= hMeusAgendamentos(
                        $statusRotulos[$status] ?? ucfirst($status)
                    ) ?>
                </span>
            </div>
        </div>
    </article>
    <?php
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width,initial-scale=1,shrink-to-fit=no"
    >
    <title>
        Meus agendamentos<?= $empresa !== ''
            ? ' | ' . hMeusAgendamentos($empresa)
            : '' ?>
    </title>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
    >
    <link
        rel="stylesheet"
        href="assets/css/cliente-area.css?v=20260915-1"
    >
    <link
        rel="stylesheet"
        href="assets/css/meus-agendamentos-cliente.css?v=20260923-1"
    >
</head>
<body>
<div class="cliente-area-page">
    <header class="cliente-area-topbar">
        <div class="container cliente-area-container">
            <div class="cliente-area-topbar-inner">
                <a
                    class="cliente-area-brand"
                    href="dashboard-cliente.php"
                >
                    <?= hMeusAgendamentos(
                        $empresa !== '' ? $empresa : 'Minha área'
                    ) ?>
                </a>

                <div class="cliente-area-user">
                    <div class="cliente-area-user-text">
                        <strong><?= hMeusAgendamentos($nome) ?></strong>
                        <span>Cliente</span>
                    </div>

                    <a
                        class="btn btn-outline-secondary btn-sm"
                        href="dashboard-cliente.php"
                    >
                        Minha área
                    </a>

                    <a
                        class="btn btn-link btn-sm cliente-area-logout"
                        href="logout.php"
                    >
                        Sair
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container cliente-area-container cliente-area-main">
        <div id="agendamentoAlerta" class="alert d-none"></div>
        <section class="cliente-agendamentos-header">
            <div>
                <span class="cliente-area-eyebrow">Minha agenda</span>
                <h1>Meus agendamentos</h1>
                <p>
                    Consulte seus próximos horários e o histórico de
                    atendimentos.
                </p>
            </div>

            <a class="btn btn-primary" href="agendar.php">
                Agendar novo horário
            </a>
        </section>

        <section class="cliente-agendamentos-section">
            <div class="cliente-area-section-heading">
                <div>
                    <h2>Próximos agendamentos</h2>
                    <p>
                        Horários que ainda estão programados para acontecer.
                    </p>
                </div>
            </div>

            <?php if (!$proximos): ?>
                <div class="cliente-agendamentos-empty">
                    <strong>Nenhum próximo agendamento.</strong>
                    <p>
                        Quando você reservar um horário, ele aparecerá aqui.
                    </p>
                    <a href="agendar.php" class="btn btn-primary btn-sm">
                        Agendar horário
                    </a>
                </div>
            <?php else: ?>
                <div class="cliente-agendamentos-lista">
                    <?php foreach ($proximos as $agendamento): ?>
                        <?php renderAgendamentoCliente(
                            $agendamento,
                            $statusRotulos,
                            $statusClasses,
                            $timezone
                        ); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="cliente-agendamentos-section">
            <div class="cliente-area-section-heading">
                <div>
                    <h2>Histórico</h2>
                    <p>
                        Atendimentos concluídos, cancelados, não comparecidos
                        ou horários que já passaram.
                    </p>
                </div>
            </div>

            <?php if ($historico): ?>
                <div class="cliente-historico-resumo" aria-label="Resumo do histórico">
                    <div><strong><?= $resumoHistorico['concluido'] ?></strong><span>Concluídos</span></div>
                    <div><strong><?= $resumoHistorico['cancelado'] ?></strong><span>Cancelados</span></div>
                    <div><strong><?= $resumoHistorico['nao_compareceu'] ?></strong><span>Não compareceram</span></div>
                </div>
            <?php endif; ?>

            <?php if (!$historico): ?>
                <div class="cliente-agendamentos-empty">
                    <strong>Seu histórico ainda está vazio.</strong>
                    <p>
                        Os atendimentos anteriores aparecerão nesta área.
                    </p>
                </div>
            <?php else: ?>
                <div class="cliente-agendamentos-lista">
                    <?php foreach ($historico as $agendamento): ?>
                        <?php renderAgendamentoCliente(
                            $agendamento,
                            $statusRotulos,
                            $statusClasses,
                            $timezone
                        ); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="cliente-area-footer">
        <div class="container cliente-area-container">
            <span>
                <?= hMeusAgendamentos(
                    $empresa !== '' ? $empresa : 'Área do cliente'
                ) ?>
            </span>
            <span>Área do cliente</span>
        </div>
    </footer>
</div>
<script>
window.AUTOAGENDAMENTO={csrfToken:<?=json_encode($_SESSION['csrf_autoagendamento'])?>,apiUrl:'api/autoagendamento.php'};
</script>
<script src="assets/js/cliente-agendamento.js?v=20260915-2"></script>
</body>
</html>
