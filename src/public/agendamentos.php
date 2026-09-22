<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigirLogin();

$contextoAtual = (string) ($_SESSION['contexto'] ?? '');
$ehAdministrador = $contextoAtual === 'administrador';
$ehColaborador = $contextoAtual === 'colaborador';
$ehProfissional = $contextoAtual === 'profissional';

if ($ehColaborador) {
    exigirAcesso('agenda');
} elseif (!$ehAdministrador && !$ehProfissional) {
    header('Location: acesso-negado.php');
    exit;
}

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();
$profissionalSessaoId = $ehProfissional ? (int) ($_SESSION['profissional_id'] ?? 0) : 0;

if ($ehProfissional && $profissionalSessaoId <= 0) {
    http_response_code(403);
    exit('Profissional não identificado.');
}

if (
    empty($_SESSION['csrf_agendamentos'])
    || !is_string($_SESSION['csrf_agendamentos'])
) {
    $_SESSION['csrf_agendamentos'] = bin2hex(random_bytes(32));
}

$csrfToken = (string) $_SESSION['csrf_agendamentos'];

$flash = $_SESSION['flash_agendamentos'] ?? null;
unset($_SESSION['flash_agendamentos']);

$mensagem = is_array($flash) && is_string($flash['mensagem'] ?? null)
    ? $flash['mensagem']
    : '';

$tipoMensagem = is_array($flash) && ($flash['tipo'] ?? '') === 'success'
    ? 'success'
    : 'danger';

$statusPermitidos = [
    'pendente' => 'Pendente',
    'confirmado' => 'Confirmado',
    'em_atendimento' => 'Em atendimento',
    'concluido' => 'Concluído',
    'cancelado' => 'Cancelado',
    'nao_compareceu' => 'Não compareceu',
];

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

$statusItensPermitidos = [
    'agendado' => 'Agendado',
    'confirmado' => 'Confirmado',
    'em_atendimento' => 'Em atendimento',
    'concluido' => 'Concluído',
    'cancelado' => 'Cancelado',
    'nao_compareceu' => 'Não compareceu',
];

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

$statusFiltro = trim((string) ($_GET['status'] ?? ''));
$profissionalFiltro = filter_input(INPUT_GET, 'profissional_id', FILTER_VALIDATE_INT);
$dataInicio = trim((string) ($_GET['data_inicio'] ?? ''));
$dataFim = trim((string) ($_GET['data_fim'] ?? ''));

if (!array_key_exists($statusFiltro, $statusPermitidos)) {
    $statusFiltro = '';
}

if ($profissionalFiltro === false || $profissionalFiltro === null) {
    $profissionalFiltro = 0;
}

function agendamentoDataValida(string $data): bool
{
    if ($data === '') {
        return true;
    }

    $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $data);

    return $objeto !== false && $objeto->format('Y-m-d') === $data;
}

if (!agendamentoDataValida($dataInicio)) {
    $dataInicio = '';
}

if (!agendamentoDataValida($dataFim)) {
    $dataFim = '';
}

$stmtProfissionais = $pdo->prepare(
    'SELECT
        pr.id,
        p.nome_completo
     FROM profissionais pr
     INNER JOIN pessoas p
       ON p.id = pr.pessoa_id
      AND p.empresa_id = pr.empresa_id
     WHERE pr.empresa_id = :empresa_id
       AND pr.ativo = 1
       AND p.ativo = 1
     ORDER BY p.nome_completo ASC'
);
$stmtProfissionais->execute([':empresa_id' => $empresaId]);
$profissionais = $stmtProfissionais->fetchAll(PDO::FETCH_ASSOC);

if ($ehProfissional) {
    $profissionais = array_values(array_filter(
        $profissionais,
        static fn (array $profissional): bool =>
            (int) $profissional['id'] === $profissionalSessaoId
    ));
    $profissionalFiltro = $profissionalSessaoId;
}

$where = [
    'a.empresa_id = :empresa_id',
];

$params = [
    ':empresa_id' => $empresaId,
];

if ($ehProfissional) {
    $where[] = 'EXISTS (
        SELECT 1
        FROM agendamento_servicos asp
        WHERE asp.agendamento_id = a.id
          AND asp.profissional_id = :profissional_sessao_id
    )';
    $params[':profissional_sessao_id'] = $profissionalSessaoId;
}

if ($statusFiltro !== '') {
    $where[] = 'a.status = :status';
    $params[':status'] = $statusFiltro;
}

if ($profissionalFiltro > 0) {
    $where[] = 'EXISTS (
        SELECT 1
        FROM agendamento_servicos asf
        INNER JOIN profissionais prf
          ON prf.id = asf.profissional_id
        WHERE asf.agendamento_id = a.id
          AND asf.profissional_id = :profissional_id
          AND prf.empresa_id = a.empresa_id
    )';
    $params[':profissional_id'] = $profissionalFiltro;
}

if ($dataInicio !== '') {
    $where[] = 'a.inicio >= :data_inicio';
    $params[':data_inicio'] = $dataInicio . ' 00:00:00';
}

if ($dataFim !== '') {
    $where[] = 'a.inicio < :data_fim';
    $fimExclusivo = (new DateTimeImmutable($dataFim))->modify('+1 day');
    $params[':data_fim'] = $fimExclusivo->format('Y-m-d') . ' 00:00:00';
}

$sql = '
    SELECT
        a.id,
        a.inicio,
        a.fim,
        a.status,
        a.origem,
        a.valor_total,
        a.observacoes_cliente,
        a.observacoes_internas,
        pc.nome_completo AS cliente_nome,
        GROUP_CONCAT(
            DISTINCT CONCAT(
                s.nome,
                " — ",
                pp.nome_completo
            )
            ORDER BY ags.ordem ASC
            SEPARATOR "||"
        ) AS itens
    FROM agendamentos a
    INNER JOIN clientes c
      ON c.id = a.cliente_id
     AND c.empresa_id = a.empresa_id
    INNER JOIN pessoas pc
      ON pc.id = c.pessoa_id
     AND pc.empresa_id = c.empresa_id
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
    WHERE ' . implode(' AND ', $where) . '
    GROUP BY
        a.id,
        a.inicio,
        a.fim,
        a.status,
        a.origem,
        a.valor_total,
        a.observacoes_cliente,
        a.observacoes_internas,
        pc.nome_completo
    ORDER BY a.inicio DESC, a.id DESC
';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$itensPorAgendamento = [];
if ($agendamentos) {
    $idsAgendamentos = array_map(static fn (array $a): int => (int) $a['id'], $agendamentos);
    $placeholders = implode(',', array_fill(0, count($idsAgendamentos), '?'));
    $sqlItens = 'SELECT
                    ags.id,
                    ags.agendamento_id,
                    ags.profissional_id,
                    ags.status,
                    ags.inicio,
                    ags.fim,
                    ags.iniciado_em,
                    ags.concluido_em,
                    ags.ordem,
                    s.nome AS servico_nome,
                    pp.nome_completo AS profissional_nome
                 FROM agendamento_servicos ags
                 INNER JOIN agendamentos a
                   ON a.id = ags.agendamento_id
                  AND a.empresa_id = ?
                 INNER JOIN servicos s
                   ON s.id = ags.servico_id
                  AND s.empresa_id = a.empresa_id
                 INNER JOIN profissionais pr
                   ON pr.id = ags.profissional_id
                  AND pr.empresa_id = a.empresa_id
                 INNER JOIN pessoas pp
                   ON pp.id = pr.pessoa_id
                  AND pp.empresa_id = a.empresa_id
                 WHERE ags.agendamento_id IN (' . $placeholders . ')
                 ORDER BY ags.agendamento_id, ags.ordem';
    $stmtItens = $pdo->prepare($sqlItens);
    $stmtItens->execute(array_merge([$empresaId], $idsAgendamentos));
    foreach ($stmtItens->fetchAll(PDO::FETCH_ASSOC) as $itemAgendamento) {
        $itensPorAgendamento[(int) $itemAgendamento['agendamento_id']][] = $itemAgendamento;
    }
}

$badges = [
    'pendente' => 'warning',
    'confirmado' => 'primary',
    'em_atendimento' => 'info',
    'concluido' => 'success',
    'cancelado' => 'secondary',
    'nao_compareceu' => 'danger',
];

$origens = [
    'site' => 'Site',
    'painel' => 'Painel',
    'telefone' => 'Telefone',
    'whatsapp' => 'WhatsApp',
    'recepcao' => 'Recepção',
    'api' => 'API',
];

$pageTitle = 'Agendamentos';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/partials/navbar.php';
?>

<main class="app-content">
    <div class="app-page-header d-md-flex justify-content-between align-items-center">
        <div>
            <h1>Agendamentos</h1>
            <p>Acompanhe e gerencie os agendamentos da empresa.</p>
        </div>

        <div class="mt-3 mt-md-0">
            <a href="agenda.php" class="btn btn-outline-primary">
                Ver calendário
            </a>
        </div>
    </div>

    <?php if ($mensagem !== ''): ?>
        <div class="alert alert-<?= $tipoMensagem ?>" role="alert">
            <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="app-card mb-4">
        <div class="app-card-body">
            <form method="get" class="row align-items-end">
                <div class="form-group col-12 col-md-3">
                    <label for="status">Status</label>
                    <select class="custom-select" id="status" name="status">
                        <option value="">Todos os status</option>
                        <?php foreach ($statusPermitidos as $valor => $rotulo): ?>
                            <option
                                value="<?= htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $statusFiltro === $valor ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group col-12 col-md-3">
                    <label for="profissional_id">Profissional</label>
                    <select class="custom-select" id="profissional_id" name="profissional_id">
                        <option value="">Todos os profissionais</option>
                        <?php foreach ($profissionais as $profissional): ?>
                            <option
                                value="<?= (int) $profissional['id'] ?>"
                                <?= $profissionalFiltro === (int) $profissional['id'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars(
                                    (string) $profissional['nome_completo'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group col-12 col-md-2">
                    <label for="data_inicio">De</label>
                    <input
                        type="date"
                        class="form-control"
                        id="data_inicio"
                        name="data_inicio"
                        value="<?= htmlspecialchars($dataInicio, ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <div class="form-group col-12 col-md-2">
                    <label for="data_fim">Até</label>
                    <input
                        type="date"
                        class="form-control"
                        id="data_fim"
                        name="data_fim"
                        value="<?= htmlspecialchars($dataFim, ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <div class="form-group col-12 col-md-2 d-flex">
                    <button type="submit" class="btn btn-primary mr-2">
                        Aplicar
                    </button>
                    <a href="agendamentos.php" class="btn btn-outline-secondary">
                        Limpar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="app-card">
        <div class="app-card-body p-0">
            <?php if (!$agendamentos): ?>
                <div class="app-empty-state">
                    <h3>Nenhum agendamento encontrado</h3>
                    <p>
                        Os agendamentos cadastrados aparecerão aqui.
                    </p>
                    <a href="agenda.php" class="btn btn-primary">
                        Abrir agenda
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Data / hora</th>
                                <th>Cliente</th>
                                <th>Serviços / profissionais</th>
                                <th>Origem</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th class="text-right">Atualizar</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($agendamentos as $agendamento): ?>
                            <?php
                            $inicio = new DateTimeImmutable((string) $agendamento['inicio']);
                            $fim = new DateTimeImmutable((string) $agendamento['fim']);
                            $status = (string) $agendamento['status'];
                            $itens = $itensPorAgendamento[(int) $agendamento['id']] ?? [];
                            ?>
                            <tr>
                                <td>
                                    <strong><?= $inicio->format('d/m/Y') ?></strong>
                                    <div class="text-muted">
                                        <?= $inicio->format('H:i') ?>–<?= $fim->format('H:i') ?>
                                    </div>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        (string) $agendamento['cliente_nome'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?php if ($itens): ?>
                                        <?php foreach ($itens as $item): ?>
                                            <?php
                                            $statusItem = (string) $item['status'];
                                            if ($statusItem === 'agendado' && $status !== 'pendente') {
                                                $statusItem = match ($status) {
                                                    'confirmado' => 'confirmado',
                                                    'em_atendimento' => 'em_atendimento',
                                                    'concluido' => 'concluido',
                                                    'cancelado' => 'cancelado',
                                                    'nao_compareceu' => 'nao_compareceu',
                                                    default => $statusItem,
                                                };
                                            }
                                            ?>
                                            <div class="mb-2">
                                                <strong><?= htmlspecialchars((string) $item['servico_nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                — <?= htmlspecialchars((string) $item['profissional_nome'], ENT_QUOTES, 'UTF-8') ?>
                                                <div class="small text-muted">
                                                    <?= (new DateTimeImmutable((string) $item['inicio']))->format('H:i') ?>–<?= (new DateTimeImmutable((string) $item['fim']))->format('H:i') ?>
                                                    · <?= htmlspecialchars($statusItensPermitidos[$statusItem] ?? $statusItem, ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Sem serviços vinculados</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $origens[(string) $agendamento['origem']]
                                            ?? ucfirst((string) $agendamento['origem']),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    R$ <?= number_format(
                                        (float) $agendamento['valor_total'],
                                        2,
                                        ',',
                                        '.'
                                    ) ?>
                                </td>

                                <td>
                                    <span class="badge badge-<?= htmlspecialchars(
                                        $badges[$status] ?? 'secondary',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>">
                                        <?= htmlspecialchars(
                                            $statusPermitidos[$status] ?? $status,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>
                                </td>

                                <td class="text-right">
                                    <?php if (!$itens): ?>
                                        <span class="text-muted small">Sem ação</span>
                                    <?php else: ?>
                                        <?php foreach ($itens as $item): ?>
                                            <?php
                                            $statusItem = (string) $item['status'];
                                            if ($statusItem === 'agendado' && $status !== 'pendente') {
                                                $statusItem = match ($status) {
                                                    'confirmado' => 'confirmado',
                                                    'em_atendimento' => 'em_atendimento',
                                                    'concluido' => 'concluido',
                                                    'cancelado' => 'cancelado',
                                                    'nao_compareceu' => 'nao_compareceu',
                                                    default => $statusItem,
                                                };
                                            }
                                            $itemDoProfissional = !$ehProfissional
                                                || (int) $item['profissional_id'] === $profissionalSessaoId;
                                            $proximosStatusItem = $ehProfissional
                                                ? ($transicoesItemProfissional[$statusItem] ?? [])
                                                : ($transicoesItemOperacao[$statusItem] ?? []);
                                            ?>
                                            <?php if ($itemDoProfissional && $proximosStatusItem): ?>
                                                <div class="mb-2">
                                                    <?php if ($ehProfissional): ?>
                                                        <?php $statusAcao = $proximosStatusItem[0]; ?>
                                                        <form action="api/agendamentos.php" method="post" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                            <input type="hidden" name="acao" value="alterar_status">
                                                            <input type="hidden" name="agendamento_id" value="<?= (int) $agendamento['id'] ?>">
                                                            <input type="hidden" name="agendamento_servico_id" value="<?= (int) $item['id'] ?>">
                                                            <input type="hidden" name="status" value="<?= htmlspecialchars($statusAcao, ENT_QUOTES, 'UTF-8') ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                                                <?= $statusAcao === 'em_atendimento' ? '▶ Iniciar' : '✓ Finalizar' ?>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form action="api/agendamentos.php" method="post" class="d-inline-flex align-items-center">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                            <input type="hidden" name="acao" value="alterar_status">
                                                            <input type="hidden" name="agendamento_id" value="<?= (int) $agendamento['id'] ?>">
                                                            <input type="hidden" name="agendamento_servico_id" value="<?= (int) $item['id'] ?>">
                                                            <select class="custom-select custom-select-sm mr-2" name="status" required>
                                                                <option value="">Selecione</option>
                                                                <?php foreach ($proximosStatusItem as $valor): ?>
                                                                    <option value="<?= htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') ?>">
                                                                        <?= htmlspecialchars($statusItensPermitidos[$valor] ?? $valor, ENT_QUOTES, 'UTF-8') ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <button type="submit" class="btn btn-sm btn-outline-primary">Atualizar</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            <?php elseif ($itemDoProfissional): ?>
                                                <div class="small text-muted mb-2">
                                                    <?= htmlspecialchars((string) $item['servico_nome'], ENT_QUOTES, 'UTF-8') ?>: final
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
