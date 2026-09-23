<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigirAcesso('agenda');

$contextoAtual = (string) ($_SESSION['contexto'] ?? '');
if (!in_array($contextoAtual, ['administrador', 'colaborador'], true)) {
    negarAcesso();
}

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();

if (empty($_SESSION['csrf_agendamento_recepcao'])) {
    $_SESSION['csrf_agendamento_recepcao'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['csrf_agendamento_recepcao'];

$stmt = $pdo->prepare(
    'SELECT c.id, p.nome_completo, p.cpf, p.email, tp.numero AS telefone
     FROM clientes c
     INNER JOIN pessoas p
       ON p.id = c.pessoa_id
      AND p.empresa_id = c.empresa_id
     LEFT JOIN telefones_pessoa tp
       ON tp.id = (
           SELECT t.id
           FROM telefones_pessoa t
           WHERE t.pessoa_id = p.id
           ORDER BY t.principal DESC, t.id ASC
           LIMIT 1
       )
     WHERE c.empresa_id = :empresa_id
       AND c.ativo = 1
       AND p.ativo = 1
     ORDER BY p.nome_completo ASC'
);
$stmt->execute([':empresa_id' => $empresaId]);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Novo agendamento';
$pageCss = 'agendamento-recepcao.css';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/partials/navbar.php';
?>

<main class="app-content">
    <div class="app-page-header d-md-flex justify-content-between align-items-center">
        <div>
            <h1>Novo agendamento</h1>
            <p>Agende um atendimento realizado pela recepção.</p>
        </div>

        <div class="mt-3 mt-md-0">
            <a class="btn btn-outline-secondary" href="agendamentos.php">Voltar aos agendamentos</a>
        </div>
    </div>

    <div
        id="agendamentoRecepcaoApp"
        class="recepcao-layout"
        data-api="api/agendamento-recepcao.php"
        data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"
    >
        <section class="app-card recepcao-form-card">
            <div id="recepcaoMensagem" class="alert d-none" role="alert"></div>

            <form id="formAgendamentoRecepcao" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                <div class="recepcao-etapa">
                    <div class="recepcao-etapa-numero">1</div>
                    <div>
                        <h2>Cliente</h2>
                        <p>Selecione quem será atendido.</p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="cliente_id">Cliente</label>
                    <select class="form-control" id="cliente_id" name="cliente_id" required>
                        <option value="">Selecione o cliente</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <?php
                            $detalhe = [];
                            if (!empty($cliente['telefone'])) $detalhe[] = (string) $cliente['telefone'];
                            if (!empty($cliente['cpf'])) $detalhe[] = (string) $cliente['cpf'];
                            $rotulo = (string) $cliente['nome_completo'];
                            if ($detalhe) $rotulo .= ' — ' . implode(' · ', $detalhe);
                            ?>
                            <option value="<?= (int) $cliente['id'] ?>">
                                <?= htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="recepcao-apoio">
                        <?php if ($contextoAtual === 'administrador'): ?>
                            Cliente novo? <a href="cadastro-cliente-admin.php">Cadastrar cliente</a> e depois retorne ao agendamento.
                        <?php else: ?>
                            Cliente novo? O cadastro deve ser realizado por um administrador.
                        <?php endif; ?>
                    </div>
                </div>

                <div class="recepcao-etapa">
                    <div class="recepcao-etapa-numero">2</div>
                    <div>
                        <h2>Serviço e profissional</h2>
                        <p>Escolha o serviço e quem fará o atendimento.</p>
                    </div>
                </div>

                <div class="recepcao-grid-2">
                    <div class="form-group">
                        <label for="servico_id">Serviço</label>
                        <select class="form-control" id="servico_id" name="servico_id" required disabled>
                            <option value="">Carregando serviços...</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="profissional_id">Profissional</label>
                        <select class="form-control" id="profissional_id" name="profissional_id" required disabled>
                            <option value="">Selecione primeiro o serviço</option>
                        </select>
                    </div>
                </div>

                <div class="recepcao-etapa">
                    <div class="recepcao-etapa-numero">3</div>
                    <div>
                        <h2>Data e horário</h2>
                        <p>Somente horários realmente disponíveis serão exibidos.</p>
                    </div>
                </div>

                <div class="recepcao-grid-2">
                    <div class="form-group">
                        <label for="data">Data</label>
                        <input
                            class="form-control"
                            type="date"
                            id="data"
                            name="data"
                            min="<?= (new DateTimeImmutable('today'))->format('Y-m-d') ?>"
                            required
                            disabled
                        >
                    </div>

                    <div class="form-group">
                        <label for="hora">Horário</label>
                        <select class="form-control" id="hora" name="hora" required disabled>
                            <option value="">Selecione serviço, profissional e data</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="observacoes_internas">Observação interna <span class="text-muted">(opcional)</span></label>
                    <textarea
                        class="form-control"
                        id="observacoes_internas"
                        name="observacoes_internas"
                        rows="3"
                        maxlength="2000"
                        placeholder="Ex.: cliente ligou para agendar, preferência informada, observação para a recepção..."
                    ></textarea>
                </div>

                <div class="recepcao-acoes">
                    <button type="submit" id="btnConfirmarAgendamento" class="btn btn-primary" disabled>
                        Confirmar agendamento
                    </button>
                </div>
            </form>
        </section>

        <aside class="app-card recepcao-resumo-card">
            <h2>Resumo</h2>
            <dl class="recepcao-resumo">
                <div><dt>Cliente</dt><dd id="resumoCliente">—</dd></div>
                <div><dt>Serviço</dt><dd id="resumoServico">—</dd></div>
                <div><dt>Profissional</dt><dd id="resumoProfissional">—</dd></div>
                <div><dt>Data</dt><dd id="resumoData">—</dd></div>
                <div><dt>Horário</dt><dd id="resumoHorario">—</dd></div>
                <div><dt>Valor</dt><dd id="resumoValor">—</dd></div>
            </dl>
        </aside>
    </div>
</main>

<script src="assets/js/agendamento-recepcao.js"></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
