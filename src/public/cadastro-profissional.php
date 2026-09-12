<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigirAdministrador();

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();

if (empty($_SESSION['csrf_cadastro_profissional'])) {
    $_SESSION['csrf_cadastro_profissional'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_cadastro_profissional'];

$flash = $_SESSION['flash_profissional'] ?? null;
unset($_SESSION['flash_profissional']);

$old = is_array($flash['old'] ?? null) ? $flash['old'] : [];
$erros = is_array($flash['erros'] ?? null) ? $flash['erros'] : [];
$mensagem = is_string($flash['mensagem'] ?? null) ? $flash['mensagem'] : null;
$tipoMensagem = ($flash['tipo'] ?? '') === 'success' ? 'success' : 'danger';

$editarSolicitado = array_key_exists('editar', $_GET);
$editarId = $editarSolicitado
    ? filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT)
    : null;

if ($editarSolicitado && (!$editarId || $editarId <= 0)) {
    $_SESSION['flash_lista_profissionais'] = [
        'tipo' => 'danger',
        'mensagem' => 'Profissional inválido.',
    ];
    header('Location: profissionais.php');
    exit;
}

$profissionalEdicao = null;
$servicosVinculados = [];

if ($editarId) {
    $stmt = $pdo->prepare(
        'SELECT
            pr.id,
            pr.pessoa_id,
            pr.cargo,
            pr.descricao,
            pr.ativo,
            p.nome_completo,
            p.cpf,
            p.data_nascimento,
            p.genero,
            p.email,
            tp.numero AS telefone,
            COALESCE(tp.whatsapp, 0) AS whatsapp
         FROM profissionais pr
         INNER JOIN pessoas p
           ON p.id = pr.pessoa_id
          AND p.empresa_id = pr.empresa_id
         LEFT JOIN telefones_pessoa tp
           ON tp.id = (
                SELECT tp2.id
                FROM telefones_pessoa tp2
                WHERE tp2.pessoa_id = p.id
                ORDER BY tp2.principal DESC, tp2.id ASC
                LIMIT 1
           )
         WHERE pr.id = :id
           AND pr.empresa_id = :empresa_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $editarId,
        ':empresa_id' => $empresaId,
    ]);
    $profissionalEdicao = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profissionalEdicao) {
        $_SESSION['flash_lista_profissionais'] = [
            'tipo' => 'danger',
            'mensagem' => 'Profissional não encontrado.',
        ];
        header('Location: profissionais.php');
        exit;
    }

    $stmtVinculos = $pdo->prepare(
        'SELECT servico_id, duracao_minutos, preco, ativo
         FROM profissional_servicos
         WHERE profissional_id = :profissional_id'
    );
    $stmtVinculos->execute([':profissional_id' => $editarId]);

    foreach ($stmtVinculos->fetchAll(PDO::FETCH_ASSOC) as $vinculo) {
        $servicosVinculados[(int) $vinculo['servico_id']] = $vinculo;
    }
}

$stmtServicos = $pdo->prepare(
    'SELECT id, nome, duracao_minutos, preco, ativo
     FROM servicos
     WHERE empresa_id = :empresa_id
     ORDER BY ativo DESC, nome ASC'
);
$stmtServicos->execute([':empresa_id' => $empresaId]);
$servicos = $stmtServicos->fetchAll(PDO::FETCH_ASSOC);

function valorProfissional(array $old, ?array $profissional, string $campo, string $padrao = ''): string
{
    if (array_key_exists($campo, $old)) {
        $valor = $old[$campo];
    } elseif ($profissional !== null && array_key_exists($campo, $profissional)) {
        $valor = $profissional[$campo] ?? '';
    } else {
        $valor = $padrao;
    }

    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$temOld = $old !== [];
$modoEdicao = $profissionalEdicao !== null;

if ($temOld) {
    $ativoMarcado = !empty($old['ativo']);
    $whatsappMarcado = !empty($old['whatsapp']);
    $servicosOld = is_array($old['servicos'] ?? null) ? $old['servicos'] : [];
} else {
    $ativoMarcado = !$modoEdicao || (int) $profissionalEdicao['ativo'] === 1;
    $whatsappMarcado = $modoEdicao && (int) $profissionalEdicao['whatsapp'] === 1;
    $servicosOld = [];
}

$pageTitle = $modoEdicao ? 'Editar profissional' : 'Cadastrar profissional';
$pageCss = 'cadastro-profissional.css';
$pageJs = 'cadastro-profissional.js';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/partials/navbar.php';
?>

<main class="app-content">
    <div class="app-page-header d-md-flex justify-content-between align-items-center">
        <div>
            <h1><?= $modoEdicao ? 'Editar profissional' : 'Cadastrar profissional' ?></h1>
            <p>
                <?= $modoEdicao
                    ? 'Atualize os dados, contato e serviços executados pelo profissional.'
                    : 'Cadastre os dados operacionais do profissional e vincule os serviços que ele executa.' ?>
            </p>
        </div>

        <div class="mt-3 mt-md-0">
            <a href="profissionais.php" class="btn btn-outline-secondary">Ver profissionais</a>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipoMensagem ?>" role="alert">
            <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form id="cadastroProfissionalForm" action="api/profissionais.php" method="post" novalidate>
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
        >
        <input type="hidden" name="acao" value="<?= $modoEdicao ? 'atualizar' : 'criar' ?>">

        <?php if ($modoEdicao): ?>
            <input type="hidden" name="profissional_id" value="<?= (int) $profissionalEdicao['id'] ?>">
        <?php endif; ?>

        <div class="row">
            <div class="col-12 col-xl-8">
                <div class="app-card mb-4">
                    <div class="app-card-header"><h2>Dados pessoais</h2></div>
                    <div class="app-card-body">
                        <div class="form-group">
                            <label for="nome_completo">
                                Nome completo <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control<?= isset($erros['nome_completo']) ? ' is-invalid' : '' ?>"
                                id="nome_completo"
                                name="nome_completo"
                                maxlength="160"
                                value="<?= valorProfissional($old, $profissionalEdicao, 'nome_completo') ?>"
                                required
                                autofocus
                            >
                            <div class="invalid-feedback">
                                <?= htmlspecialchars($erros['nome_completo'] ?? 'Informe o nome completo.', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="cpf">CPF</label>
                                <input
                                    type="text"
                                    class="form-control<?= isset($erros['cpf']) ? ' is-invalid' : '' ?>"
                                    id="cpf"
                                    name="cpf"
                                    maxlength="14"
                                    inputmode="numeric"
                                    value="<?= valorProfissional($old, $profissionalEdicao, 'cpf') ?>"
                                    placeholder="000.000.000-00"
                                >
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($erros['cpf'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="data_nascimento">Data de nascimento</label>
                                <input
                                    type="date"
                                    class="form-control<?= isset($erros['data_nascimento']) ? ' is-invalid' : '' ?>"
                                    id="data_nascimento"
                                    name="data_nascimento"
                                    value="<?= valorProfissional($old, $profissionalEdicao, 'data_nascimento') ?>"
                                >
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($erros['data_nascimento'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="genero">Gênero</label>
                                <?php $generoAtual = $temOld ? (string) ($old['genero'] ?? 'nao_informado') : (string) ($profissionalEdicao['genero'] ?? 'nao_informado'); ?>
                                <select
                                    class="custom-select<?= isset($erros['genero']) ? ' is-invalid' : '' ?>"
                                    id="genero"
                                    name="genero"
                                >
                                    <option value="nao_informado" <?= $generoAtual === 'nao_informado' ? 'selected' : '' ?>>Prefiro não informar</option>
                                    <option value="feminino" <?= $generoAtual === 'feminino' ? 'selected' : '' ?>>Feminino</option>
                                    <option value="masculino" <?= $generoAtual === 'masculino' ? 'selected' : '' ?>>Masculino</option>
                                    <option value="nao_binario" <?= $generoAtual === 'nao_binario' ? 'selected' : '' ?>>Não binário</option>
                                </select>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($erros['genero'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="email">E-mail corporativo</label>
                                <input
                                    type="email"
                                    class="form-control<?= isset($erros['email']) ? ' is-invalid' : '' ?>"
                                    id="email"
                                    name="email"
                                    maxlength="190"
                                    value="<?= valorProfissional($old, $profissionalEdicao, 'email') ?>"
                                >
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($erros['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <small class="form-text text-muted">
                                    Este e-mail poderá ser usado posteriormente para liberar acesso ao sistema.
                                </small>
                            </div>
                        </div>

                        <div class="form-row align-items-end">
                            <div class="form-group col-md-8">
                                <label for="telefone">Telefone principal</label>
                                <input
                                    type="text"
                                    class="form-control<?= isset($erros['telefone']) ? ' is-invalid' : '' ?>"
                                    id="telefone"
                                    name="telefone"
                                    maxlength="30"
                                    inputmode="tel"
                                    value="<?= valorProfissional($old, $profissionalEdicao, 'telefone') ?>"
                                    placeholder="(11) 99999-9999"
                                >
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($erros['telefone'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>

                            <div class="form-group col-md-4">
                                <div class="custom-control custom-checkbox cadastro-profissional-whatsapp">
                                    <input
                                        type="checkbox"
                                        class="custom-control-input"
                                        id="whatsapp"
                                        name="whatsapp"
                                        value="1"
                                        <?= $whatsappMarcado ? 'checked' : '' ?>
                                    >
                                    <label class="custom-control-label" for="whatsapp">É WhatsApp</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="app-card mb-4">
                    <div class="app-card-header"><h2>Dados profissionais</h2></div>
                    <div class="app-card-body">
                        <div class="form-group">
                            <label for="cargo">Cargo / especialidade</label>
                            <input
                                type="text"
                                class="form-control<?= isset($erros['cargo']) ? ' is-invalid' : '' ?>"
                                id="cargo"
                                name="cargo"
                                maxlength="120"
                                value="<?= valorProfissional($old, $profissionalEdicao, 'cargo') ?>"
                                placeholder="Ex.: Cabeleireiro, Barbeiro, Manicure"
                            >
                            <div class="invalid-feedback">
                                <?= htmlspecialchars($erros['cargo'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="descricao">Descrição</label>
                            <textarea
                                class="form-control<?= isset($erros['descricao']) ? ' is-invalid' : '' ?>"
                                id="descricao"
                                name="descricao"
                                rows="4"
                                maxlength="3000"
                            ><?= valorProfissional($old, $profissionalEdicao, 'descricao') ?></textarea>
                            <div class="invalid-feedback">
                                <?= htmlspecialchars($erros['descricao'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>

                        <div class="custom-control custom-switch">
                            <input
                                type="checkbox"
                                class="custom-control-input"
                                id="ativo"
                                name="ativo"
                                value="1"
                                <?= $ativoMarcado ? 'checked' : '' ?>
                            >
                            <label class="custom-control-label" for="ativo">Profissional ativo</label>
                        </div>
                    </div>
                </div>

                <div class="app-card mb-4">
                    <div class="app-card-header"><h2>Serviços executados</h2></div>
                    <div class="app-card-body">
                        <?php if (isset($erros['servicos'])): ?>
                            <div class="alert alert-danger">
                                <?= htmlspecialchars($erros['servicos'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!$servicos): ?>
                            <div class="app-empty-state py-4">
                                <h3>Nenhum serviço cadastrado</h3>
                                <p>Cadastre os serviços da empresa antes de vinculá-los aos profissionais.</p>
                                <a href="cadastro-servico.php" class="btn btn-primary">Cadastrar serviços</a>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">
                                Se preço e duração personalizados ficarem vazios, o profissional herda automaticamente
                                os valores atuais do serviço.
                            </p>

                            <div class="cadastro-profissional-servicos">
                                <?php foreach ($servicos as $servico): ?>
                                    <?php
                                    $servicoId = (int) $servico['id'];
                                    $vinculo = $servicosVinculados[$servicoId] ?? null;

                                    if ($temOld) {
                                        $itemOld = is_array($servicosOld[(string) $servicoId] ?? null)
                                            ? $servicosOld[(string) $servicoId]
                                            : [];
                                        $selecionado = !empty($itemOld['selecionado']);
                                        $duracaoPersonalizada = (string) ($itemOld['duracao_minutos'] ?? '');
                                        $precoPersonalizado = (string) ($itemOld['preco'] ?? '');
                                    } else {
                                        $selecionado = $vinculo !== null && (int) $vinculo['ativo'] === 1;
                                        $duracaoPersonalizada = $vinculo !== null && $vinculo['duracao_minutos'] !== null
                                            ? (string) $vinculo['duracao_minutos']
                                            : '';
                                        $precoPersonalizado = $vinculo !== null && $vinculo['preco'] !== null
                                            ? number_format((float) $vinculo['preco'], 2, ',', '.')
                                            : '';
                                    }

                                    $servicoAtivo = (int) $servico['ativo'] === 1;
                                    if (!$servicoAtivo && !$selecionado) {
                                        continue;
                                    }
                                    ?>

                                    <div class="cadastro-profissional-servico<?= !$servicoAtivo ? ' is-inativo' : '' ?>">
                                        <div class="custom-control custom-checkbox">
                                            <input
                                                type="checkbox"
                                                class="custom-control-input js-servico-toggle"
                                                id="servico_<?= $servicoId ?>"
                                                name="servicos[<?= $servicoId ?>][selecionado]"
                                                value="1"
                                                <?= $selecionado ? 'checked' : '' ?>
                                                <?= !$servicoAtivo && !$selecionado ? 'disabled' : '' ?>
                                            >
                                            <label class="custom-control-label" for="servico_<?= $servicoId ?>">
                                                <strong><?= htmlspecialchars($servico['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                <?php if (!$servicoAtivo): ?>
                                                    <span class="badge badge-secondary ml-1">Inativo</span>
                                                <?php endif; ?>
                                            </label>
                                        </div>

                                        <div class="cadastro-profissional-servico-padrao">
                                            Padrão: <?= (int) $servico['duracao_minutos'] ?> min •
                                            R$ <?= number_format((float) $servico['preco'], 2, ',', '.') ?>
                                        </div>

                                        <div class="cadastro-profissional-servico-personalizacao js-servico-personalizacao">
                                            <div class="form-row">
                                                <div class="form-group col-md-6 mb-md-0">
                                                    <label for="duracao_<?= $servicoId ?>">Duração personalizada</label>
                                                    <div class="input-group">
                                                        <input
                                                            type="number"
                                                            class="form-control"
                                                            id="duracao_<?= $servicoId ?>"
                                                            name="servicos[<?= $servicoId ?>][duracao_minutos]"
                                                            min="5"
                                                            max="1440"
                                                            step="5"
                                                            value="<?= htmlspecialchars($duracaoPersonalizada, ENT_QUOTES, 'UTF-8') ?>"
                                                            placeholder="Usar padrão"
                                                        >
                                                        <div class="input-group-append">
                                                            <span class="input-group-text">min</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="form-group col-md-6 mb-0">
                                                    <label for="preco_<?= $servicoId ?>">Preço personalizado</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text">R$</span>
                                                        </div>
                                                        <input
                                                            type="text"
                                                            class="form-control js-preco-profissional"
                                                            id="preco_<?= $servicoId ?>"
                                                            name="servicos[<?= $servicoId ?>][preco]"
                                                            inputmode="decimal"
                                                            maxlength="14"
                                                            value="<?= htmlspecialchars($precoPersonalizado, ENT_QUOTES, 'UTF-8') ?>"
                                                            placeholder="Usar padrão"
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-4">
                    <a href="profissionais.php" class="btn btn-outline-secondary mb-2 mb-sm-0">
                        <?= $modoEdicao ? 'Cancelar edição' : 'Voltar' ?>
                    </a>

                    <button type="submit" class="btn btn-primary" id="btnSalvarProfissional" <?= !$servicos ? 'disabled' : '' ?>>
                        <?= $modoEdicao ? 'Salvar alterações' : 'Salvar profissional' ?>
                    </button>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="app-card cadastro-profissional-ajuda mb-4">
                    <div class="app-card-header"><h2>Como funciona</h2></div>
                    <div class="app-card-body">
                        <p><strong>Cadastro operacional:</strong> criar o profissional não libera acesso ao sistema.</p>
                        <p><strong>E-mail corporativo:</strong> será usado futuramente no processo seguro de ativação do acesso.</p>
                        <p><strong>Serviços:</strong> determinam quais atendimentos poderão ser realizados por este profissional.</p>
                        <p class="mb-0"><strong>Preço e duração:</strong> vazios significam que o valor padrão do serviço será herdado automaticamente.</p>
                    </div>
                </div>
            </div>
        </div>
    </form>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
