<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigirAdministrador();

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();

if (empty($_SESSION['csrf_cadastro_servico'])) {
    $_SESSION['csrf_cadastro_servico'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_cadastro_servico'];
$flash = $_SESSION['flash_servico'] ?? null;
unset($_SESSION['flash_servico']);

$old = is_array($flash['old'] ?? null) ? $flash['old'] : [];
$erros = is_array($flash['erros'] ?? null) ? $flash['erros'] : [];
$mensagem = is_string($flash['mensagem'] ?? null) ? $flash['mensagem'] : null;
$tipoMensagem = ($flash['tipo'] ?? '') === 'success' ? 'success' : 'danger';

$editarSolicitado = array_key_exists('editar', $_GET);
$editarId = $editarSolicitado
    ? filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT)
    : null;

if ($editarSolicitado && (!$editarId || $editarId <= 0)) {
    $_SESSION['flash_lista_servicos'] = [
        'tipo' => 'danger',
        'mensagem' => 'Serviço inválido.',
    ];

    header('Location: servicos.php');
    exit;
}

$servicoEdicao = null;

if ($editarId) {
    $stmtServico = $pdo->prepare(
        'SELECT
            id,
            categoria_id,
            nome,
            descricao,
            duracao_minutos,
            intervalo_minutos,
            preco,
            permite_agendamento_online,
            ativo
         FROM servicos
         WHERE id = :id
           AND empresa_id = :empresa_id
         LIMIT 1'
    );

    $stmtServico->execute([
        ':id' => $editarId,
        ':empresa_id' => $empresaId,
    ]);

    $servicoEdicao = $stmtServico->fetch(PDO::FETCH_ASSOC);

    if (!$servicoEdicao) {
        $_SESSION['flash_lista_servicos'] = [
            'tipo' => 'danger',
            'mensagem' => 'Serviço não encontrado.',
        ];

        header('Location: servicos.php');
        exit;
    }
}

$stmtCategorias = $pdo->prepare(
    'SELECT id, nome, ativo
     FROM categorias_servicos
     WHERE empresa_id = :empresa_id
     ORDER BY ativo DESC, ordem ASC, nome ASC'
);
$stmtCategorias->execute([':empresa_id' => $empresaId]);
$categorias = $stmtCategorias->fetchAll(PDO::FETCH_ASSOC);

$stmtSugestoes = $pdo->prepare(
    'SELECT
        sb.id,
        sb.nome,
        sb.duracao_sugerida,
        seg.nome AS segmento_nome
     FROM servicos_base sb
     INNER JOIN segmentos seg
        ON seg.id = sb.segmento_id
       AND seg.ativo = 1
     INNER JOIN empresa_segmentos es
        ON es.segmento_id = sb.segmento_id
       AND es.empresa_id = :empresa_id
     LEFT JOIN servicos s
        ON s.empresa_id = :empresa_id_servicos
       AND s.nome = sb.nome
     WHERE sb.ativo = 1
       AND s.id IS NULL
     ORDER BY seg.nome ASC, sb.ordem ASC, sb.nome ASC'
);
$stmtSugestoes->execute([
    ':empresa_id' => $empresaId,
    ':empresa_id_servicos' => $empresaId,
]);
$sugestoes = $stmtSugestoes->fetchAll(PDO::FETCH_ASSOC);

$sugestoesPorSegmento = [];
foreach ($sugestoes as $sugestao) {
    $segmento = (string) $sugestao['segmento_nome'];
    $sugestoesPorSegmento[$segmento][] = $sugestao;
}

function valorCampo(
    array $old,
    ?array $servico,
    string $campo,
    string $padrao = ''
): string {
    if (array_key_exists($campo, $old)) {
        $valor = $old[$campo];
    } elseif ($servico !== null && array_key_exists($campo, $servico)) {
        $valor = $servico[$campo];
    } else {
        $valor = $padrao;
    }

    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$temOld = $old !== [];

$categoriaSelecionada = '';
if (array_key_exists('categoria_id', $old)) {
    $categoriaSelecionada = (string) $old['categoria_id'];
} elseif ($servicoEdicao !== null && $servicoEdicao['categoria_id'] !== null) {
    $categoriaSelecionada = (string) $servicoEdicao['categoria_id'];
}

if ($temOld) {
    $onlineMarcado = !empty($old['permite_agendamento_online']);
} elseif ($servicoEdicao !== null) {
    $onlineMarcado = (int) $servicoEdicao['permite_agendamento_online'] === 1;
} else {
    $onlineMarcado = true;
}

$precoPadrao = '0,00';
if (!$temOld && $servicoEdicao !== null) {
    $precoPadrao = number_format((float) $servicoEdicao['preco'], 2, ',', '.');
}

$modoEdicao = $servicoEdicao !== null;

$pageTitle = $modoEdicao ? 'Editar serviço' : 'Cadastrar serviço';
$pageCss = 'cadastro-servico.css';
$pageJs = 'cadastro-servico.js';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/partials/navbar.php';
?>

<main class="app-content">

    <div class="app-page-header d-md-flex justify-content-between align-items-center">
        <div>
            <h1><?= $modoEdicao ? 'Editar serviço' : 'Cadastrar serviço' ?></h1>
            <p>
                <?= $modoEdicao
                    ? 'Atualize os dados do serviço selecionado.'
                    : 'Cadastre manualmente ou use os serviços básicos sugeridos para o ramo da sua empresa.' ?>
            </p>
        </div>

        <div class="mt-3 mt-md-0 d-flex flex-wrap">
            <a href="categorias-servicos.php" class="btn btn-outline-primary mr-2 mb-2 mb-md-0">
                Categorias
            </a>
            <a href="servicos.php" class="btn btn-outline-secondary mb-2 mb-md-0">
                Ver serviços
            </a>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipoMensagem ?>" role="alert">
            <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!$modoEdicao): ?>
        <div class="app-card cadastro-servico-sugestoes mb-4">
            <div class="app-card-header">
                <h2>Serviços básicos sugeridos</h2>
            </div>

            <div class="app-card-body">
                <?php if (!$sugestoes): ?>
                    <div class="cadastro-servico-sem-sugestoes">
                        <strong>Nenhuma sugestão pendente.</strong>
                        <p class="mb-0">
                            Os serviços básicos do ramo já foram adicionados ou este segmento não possui sugestões cadastradas.
                        </p>
                    </div>
                <?php else: ?>
                    <p class="text-muted">
                        Selecione apenas os serviços que a empresa realmente oferece. Depois você poderá ajustar
                        preço, duração, categoria e demais informações individualmente.
                    </p>

                    <form action="api/servicos.php" method="post">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
                        >
                        <input type="hidden" name="acao" value="adicionar_sugeridos">

                        <?php foreach ($sugestoesPorSegmento as $segmentoNome => $itens): ?>
                            <div class="cadastro-servico-segmento mb-4">
                                <h3><?= htmlspecialchars($segmentoNome, ENT_QUOTES, 'UTF-8') ?></h3>

                                <div class="row">
                                    <?php foreach ($itens as $sugestao): ?>
                                        <div class="col-12 col-md-6 col-xl-4 mb-2">
                                            <label class="cadastro-servico-sugestao">
                                                <input
                                                    type="checkbox"
                                                    name="servicos_base_ids[]"
                                                    value="<?= (int) $sugestao['id'] ?>"
                                                >
                                                <span>
                                                    <strong>
                                                        <?= htmlspecialchars($sugestao['nome'], ENT_QUOTES, 'UTF-8') ?>
                                                    </strong>
                                                    <small>
                                                        Duração sugerida:
                                                        <?= (int) $sugestao['duracao_sugerida'] ?> min
                                                    </small>
                                                </span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="d-flex flex-column flex-sm-row align-items-sm-center">
                            <button type="submit" class="btn btn-primary">
                                Adicionar selecionados
                            </button>
                            <small class="text-muted ml-sm-3 mt-2 mt-sm-0">
                                Os serviços entram com preço R$ 0,00 e agendamento online desativado até você revisar.
                            </small>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12 col-xl-8 mb-4">
            <div class="app-card">
                <div class="app-card-header">
                    <h2><?= $modoEdicao ? 'Dados do serviço' : 'Cadastro manual' ?></h2>
                </div>

                <div class="app-card-body">
                    <form
                        id="cadastroServicoForm"
                        action="api/servicos.php"
                        method="post"
                        novalidate
                    >
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
                        >

                        <input
                            type="hidden"
                            name="acao"
                            value="<?= $modoEdicao ? 'atualizar' : 'criar' ?>"
                        >

                        <?php if ($modoEdicao): ?>
                            <input
                                type="hidden"
                                name="servico_id"
                                value="<?= (int) $servicoEdicao['id'] ?>"
                            >
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="nome">
                                Nome do serviço <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control<?= isset($erros['nome']) ? ' is-invalid' : '' ?>"
                                id="nome"
                                name="nome"
                                maxlength="150"
                                value="<?= valorCampo($old, $servicoEdicao, 'nome') ?>"
                                required
                                autofocus
                            >
                            <div class="invalid-feedback">
                                <?= htmlspecialchars(
                                    $erros['nome'] ?? 'Informe o nome do serviço.',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="categoria_id" class="mb-0">Categoria</label>
                                <a href="categorias-servicos.php" class="small">Gerenciar categorias</a>
                            </div>

                            <select
                                class="custom-select<?= isset($erros['categoria_id']) ? ' is-invalid' : '' ?>"
                                id="categoria_id"
                                name="categoria_id"
                            >
                                <option value="">Sem categoria</option>

                                <?php foreach ($categorias as $categoria): ?>
                                    <?php
                                    $categoriaIdAtual = (string) $categoria['id'];
                                    $categoriaAtiva = (int) $categoria['ativo'] === 1;
                                    $categoriaEhSelecionada = $categoriaSelecionada === $categoriaIdAtual;

                                    if (!$categoriaAtiva && !$categoriaEhSelecionada) {
                                        continue;
                                    }
                                    ?>
                                    <option
                                        value="<?= (int) $categoria['id'] ?>"
                                        <?= $categoriaEhSelecionada ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($categoria['nome'], ENT_QUOTES, 'UTF-8') ?>
                                        <?= !$categoriaAtiva ? ' (inativa)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php if (isset($erros['categoria_id'])): ?>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($erros['categoria_id'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>

                            <?php
                            $temCategoriaAtiva = false;
                            foreach ($categorias as $categoria) {
                                if ((int) $categoria['ativo'] === 1) {
                                    $temCategoriaAtiva = true;
                                    break;
                                }
                            }
                            ?>

                            <?php if (!$temCategoriaAtiva): ?>
                                <div class="cadastro-servico-categoria-vazia mt-2">
                                    <span>Nenhuma categoria ativa cadastrada.</span>
                                    <a
                                        href="categorias-servicos.php"
                                        class="btn btn-sm btn-outline-primary ml-sm-2 mt-2 mt-sm-0"
                                    >
                                        + Criar categoria
                                    </a>
                                </div>
                                <small class="form-text text-muted">
                                    A categoria é opcional. Você também pode salvar este serviço sem categoria.
                                </small>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="descricao">Descrição</label>
                            <textarea
                                class="form-control<?= isset($erros['descricao']) ? ' is-invalid' : '' ?>"
                                id="descricao"
                                name="descricao"
                                rows="4"
                                maxlength="3000"
                            ><?= valorCampo($old, $servicoEdicao, 'descricao') ?></textarea>
                            <div class="invalid-feedback">
                                <?= htmlspecialchars($erros['descricao'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="duracao_minutos">
                                    Duração <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input
                                        type="number"
                                        class="form-control<?= isset($erros['duracao_minutos']) ? ' is-invalid' : '' ?>"
                                        id="duracao_minutos"
                                        name="duracao_minutos"
                                        min="5"
                                        max="1440"
                                        step="5"
                                        value="<?= valorCampo($old, $servicoEdicao, 'duracao_minutos', '30') ?>"
                                        required
                                    >
                                    <div class="input-group-append">
                                        <span class="input-group-text">min</span>
                                    </div>
                                    <div class="invalid-feedback">
                                        <?= htmlspecialchars(
                                            $erros['duracao_minutos'] ?? 'Informe uma duração válida.',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="intervalo_minutos">Intervalo após o serviço</label>
                                <div class="input-group">
                                    <input
                                        type="number"
                                        class="form-control<?= isset($erros['intervalo_minutos']) ? ' is-invalid' : '' ?>"
                                        id="intervalo_minutos"
                                        name="intervalo_minutos"
                                        min="0"
                                        max="240"
                                        step="5"
                                        value="<?= valorCampo($old, $servicoEdicao, 'intervalo_minutos', '0') ?>"
                                    >
                                    <div class="input-group-append">
                                        <span class="input-group-text">min</span>
                                    </div>
                                    <div class="invalid-feedback">
                                        <?= htmlspecialchars(
                                            $erros['intervalo_minutos'] ?? 'Informe um intervalo válido.',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="preco">
                                    Preço <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">R$</span>
                                    </div>
                                    <input
                                        type="text"
                                        inputmode="decimal"
                                        class="form-control<?= isset($erros['preco']) ? ' is-invalid' : '' ?>"
                                        id="preco"
                                        name="preco"
                                        maxlength="14"
                                        value="<?= valorCampo($old, null, 'preco', $precoPadrao) ?>"
                                        required
                                    >
                                    <div class="invalid-feedback">
                                        <?= htmlspecialchars(
                                            $erros['preco'] ?? 'Informe um preço válido.',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="custom-control custom-switch mb-4">
                            <input
                                type="checkbox"
                                class="custom-control-input"
                                id="permite_agendamento_online"
                                name="permite_agendamento_online"
                                value="1"
                                <?= $onlineMarcado ? 'checked' : '' ?>
                            >
                            <label class="custom-control-label" for="permite_agendamento_online">
                                Permitir agendamento online
                            </label>
                        </div>

                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center">
                            <a
                                href="<?= $modoEdicao ? 'servicos.php' : 'dashboard.php' ?>"
                                class="btn btn-outline-secondary mb-2 mb-sm-0"
                            >
                                <?= $modoEdicao ? 'Cancelar edição' : 'Voltar ao dashboard' ?>
                            </a>

                            <button type="submit" class="btn btn-primary" id="btnSalvarServico">
                                <?= $modoEdicao ? 'Salvar alterações' : 'Salvar serviço' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4 mb-4">
            <div class="app-card cadastro-servico-ajuda">
                <div class="app-card-header">
                    <h2>Como esses dados são usados</h2>
                </div>

                <div class="app-card-body">
                    <p><strong>Categoria</strong> organiza os serviços do jeito que fizer sentido para sua empresa.</p>
                    <p><strong>Duração</strong> é usada para calcular os horários disponíveis na agenda.</p>
                    <p><strong>Intervalo</strong> reserva alguns minutos após o atendimento antes do próximo horário.</p>
                    <p><strong>Preço</strong> é o valor padrão do serviço e poderá ser ajustado por profissional posteriormente.</p>
                    <p class="mb-0"><strong>Agendamento online</strong> define se o serviço poderá aparecer para clientes nos canais externos.</p>
                </div>
            </div>
        </div>
    </div>

</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
