<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigirAdministrador();

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();

if (empty($_SESSION['csrf_categorias_servicos'])) {
    $_SESSION['csrf_categorias_servicos'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_categorias_servicos'];

$flash = $_SESSION['flash_categorias_servicos'] ?? null;
unset($_SESSION['flash_categorias_servicos']);

$mensagem = is_string($flash['mensagem'] ?? null) ? $flash['mensagem'] : null;
$tipoMensagem = ($flash['tipo'] ?? '') === 'success' ? 'success' : 'danger';
$erros = is_array($flash['erros'] ?? null) ? $flash['erros'] : [];
$old = is_array($flash['old'] ?? null) ? $flash['old'] : [];

$editarId = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
$categoriaEdicao = null;

if ($editarId) {
    $stmtEditar = $pdo->prepare(
        'SELECT id, nome, descricao, ordem, ativo
         FROM categorias_servicos
         WHERE id = :id
           AND empresa_id = :empresa_id
         LIMIT 1'
    );
    $stmtEditar->execute([
        ':id' => $editarId,
        ':empresa_id' => $empresaId,
    ]);
    $categoriaEdicao = $stmtEditar->fetch(PDO::FETCH_ASSOC) ?: null;
}

$stmt = $pdo->prepare(
    'SELECT
        c.id,
        c.nome,
        c.descricao,
        c.ordem,
        c.ativo,
        COUNT(s.id) AS total_servicos
     FROM categorias_servicos c
     LEFT JOIN servicos s
       ON s.categoria_id = c.id
      AND s.empresa_id = c.empresa_id
     WHERE c.empresa_id = :empresa_id
     GROUP BY c.id, c.nome, c.descricao, c.ordem, c.ativo
     ORDER BY c.ativo DESC, c.ordem ASC, c.nome ASC'
);
$stmt->execute([':empresa_id' => $empresaId]);
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

function categoriaOld(array $old, ?array $categoria, string $campo, string $padrao = ''): string
{
    if (array_key_exists($campo, $old)) {
        $valor = $old[$campo];
    } elseif ($categoria !== null && array_key_exists($campo, $categoria)) {
        $valor = $categoria[$campo];
    } else {
        $valor = $padrao;
    }

    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$pageTitle = 'Categorias de serviços';
$pageCss = 'categorias-servicos.css';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/partials/navbar.php';
?>

<main class="app-content">
    <div class="app-page-header d-md-flex justify-content-between align-items-center">
        <div>
            <h1>Categorias de serviços</h1>
            <p>Organize os serviços do jeito que fizer sentido para sua empresa.</p>
        </div>

        <div class="mt-3 mt-md-0">
            <a href="cadastro-servico.php" class="btn btn-primary">Cadastrar serviço</a>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipoMensagem ?>" role="alert">
            <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12 col-xl-4 mb-4">
            <div class="app-card">
                <div class="app-card-header">
                    <h2><?= $categoriaEdicao ? 'Editar categoria' : 'Nova categoria' ?></h2>
                </div>

                <div class="app-card-body">
                    <form action="api/categorias-servicos.php" method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="acao" value="<?= $categoriaEdicao ? 'atualizar' : 'criar' ?>">

                        <?php if ($categoriaEdicao): ?>
                            <input type="hidden" name="categoria_id" value="<?= (int) $categoriaEdicao['id'] ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="nome">
                                Nome <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control<?= isset($erros['nome']) ? ' is-invalid' : '' ?>"
                                id="nome"
                                name="nome"
                                maxlength="120"
                                value="<?= categoriaOld($old, $categoriaEdicao, 'nome') ?>"
                                required
                                autofocus
                            >
                            <div class="invalid-feedback">
                                <?= htmlspecialchars($erros['nome'] ?? 'Informe o nome da categoria.', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="descricao">Descrição</label>
                            <textarea
                                class="form-control<?= isset($erros['descricao']) ? ' is-invalid' : '' ?>"
                                id="descricao"
                                name="descricao"
                                rows="4"
                                maxlength="1000"
                            ><?= categoriaOld($old, $categoriaEdicao, 'descricao') ?></textarea>
                            <?php if (isset($erros['descricao'])): ?>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($erros['descricao'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="ordem">Ordem de exibição</label>
                            <input
                                type="number"
                                class="form-control<?= isset($erros['ordem']) ? ' is-invalid' : '' ?>"
                                id="ordem"
                                name="ordem"
                                min="0"
                                max="9999"
                                value="<?= categoriaOld($old, $categoriaEdicao, 'ordem', '0') ?>"
                            >
                            <div class="invalid-feedback">
                                <?= htmlspecialchars($erros['ordem'] ?? 'Informe uma ordem válida.', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <small class="form-text text-muted">
                                Quanto menor o número, mais acima a categoria aparece.
                            </small>
                        </div>

                        <div class="d-flex flex-column flex-sm-row">
                            <button type="submit" class="btn btn-primary">
                                <?= $categoriaEdicao ? 'Salvar alterações' : 'Criar categoria' ?>
                            </button>

                            <?php if ($categoriaEdicao): ?>
                                <a href="categorias-servicos.php" class="btn btn-outline-secondary ml-sm-2 mt-2 mt-sm-0">
                                    Cancelar
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8 mb-4">
            <div class="app-card">
                <div class="app-card-header">
                    <h2>Categorias cadastradas</h2>
                </div>

                <div class="app-card-body p-0">
                    <?php if (!$categorias): ?>
                        <div class="app-empty-state categorias-empty-state">
                            <h3>Nenhuma categoria cadastrada</h3>
                            <p>
                                Crie categorias como Cabelo, Barba, Unhas, Estética ou qualquer outra
                                organização que faça sentido para sua empresa.
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 categorias-table">
                                <thead>
                                    <tr>
                                        <th>Categoria</th>
                                        <th>Ordem</th>
                                        <th>Serviços</th>
                                        <th>Status</th>
                                        <th class="text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($categoria['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                <?php if (!empty($categoria['descricao'])): ?>
                                                    <small class="d-block text-muted categorias-descricao">
                                                        <?= htmlspecialchars($categoria['descricao'], ENT_QUOTES, 'UTF-8') ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= (int) $categoria['ordem'] ?></td>
                                            <td><?= (int) $categoria['total_servicos'] ?></td>
                                            <td>
                                                <span class="badge badge-<?= (int) $categoria['ativo'] === 1 ? 'success' : 'secondary' ?>">
                                                    <?= (int) $categoria['ativo'] === 1 ? 'Ativa' : 'Inativa' ?>
                                                </span>
                                            </td>
                                            <td class="text-right categorias-acoes">
                                                <a
                                                    href="categorias-servicos.php?editar=<?= (int) $categoria['id'] ?>"
                                                    class="btn btn-sm btn-outline-primary"
                                                >
                                                    Editar
                                                </a>

                                                <form
                                                    action="api/categorias-servicos.php"
                                                    method="post"
                                                    class="d-inline"
                                                >
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="acao" value="alternar_status">
                                                    <input type="hidden" name="categoria_id" value="<?= (int) $categoria['id'] ?>">

                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-outline-secondary"
                                                    >
                                                        <?= (int) $categoria['ativo'] === 1 ? 'Desativar' : 'Ativar' ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
