<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigirAdministrador();

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();

if (empty($_SESSION['csrf_servicos'])) {
    $_SESSION['csrf_servicos'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_servicos'];

$flash = $_SESSION['flash_lista_servicos'] ?? null;
unset($_SESSION['flash_lista_servicos']);

$mensagem = is_string($flash['mensagem'] ?? null) ? $flash['mensagem'] : null;
$tipoMensagem = ($flash['tipo'] ?? '') === 'success' ? 'success' : 'danger';

$stmt = $pdo->prepare(
    'SELECT
        s.id,
        s.nome,
        s.duracao_minutos,
        s.intervalo_minutos,
        s.preco,
        s.permite_agendamento_online,
        s.ativo,
        c.nome AS categoria_nome
     FROM servicos s
     LEFT JOIN categorias_servicos c
       ON c.id = s.categoria_id
      AND c.empresa_id = s.empresa_id
     WHERE s.empresa_id = :empresa_id
     ORDER BY s.ativo DESC, s.nome ASC'
);
$stmt->execute([':empresa_id' => $empresaId]);
$servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Serviços';
$pageCss = 'servicos.css';
$pageJs = 'servicos.js';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/partials/navbar.php';
?>

<main class="app-content">
    <div class="app-page-header d-md-flex justify-content-between align-items-center">
        <div>
            <h1>Serviços</h1>
            <p>Gerencie os serviços oferecidos pela sua empresa.</p>
        </div>

        <div class="mt-3 mt-md-0 d-flex flex-wrap">
            <a href="categorias-servicos.php" class="btn btn-outline-primary mr-2 mb-2 mb-md-0">
                Categorias
            </a>
            <a href="cadastro-servico.php" class="btn btn-primary mb-2 mb-md-0">
                Cadastrar serviço
            </a>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipoMensagem ?>" role="alert">
            <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="app-card">
        <div class="app-card-body p-0">
            <?php if (!$servicos): ?>
                <div class="app-empty-state">
                    <h3>Nenhum serviço cadastrado</h3>
                    <p>
                        Use as sugestões do seu ramo ou cadastre o primeiro serviço manualmente.
                    </p>
                    <div class="d-flex flex-column flex-sm-row justify-content-center">
                        <a href="categorias-servicos.php" class="btn btn-outline-primary mr-sm-2 mb-2 mb-sm-0">
                            Criar categorias
                        </a>
                        <a href="cadastro-servico.php" class="btn btn-primary">
                            Configurar serviços
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 servicos-table">
                        <thead>
                            <tr>
                                <th>Serviço</th>
                                <th>Categoria</th>
                                <th>Duração</th>
                                <th>Preço</th>
                                <th>Online</th>
                                <th>Status</th>
                                <th class="text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($servicos as $servico): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($servico['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <?php if ((int) $servico['intervalo_minutos'] > 0): ?>
                                            <small class="d-block text-muted">
                                                + <?= (int) $servico['intervalo_minutos'] ?> min de intervalo
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars(
                                            $servico['categoria_nome'] ?? 'Sem categoria',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>
                                    <td><?= (int) $servico['duracao_minutos'] ?> min</td>
                                    <td>R$ <?= number_format((float) $servico['preco'], 2, ',', '.') ?></td>
                                    <td><?= (int) $servico['permite_agendamento_online'] === 1 ? 'Sim' : 'Não' ?></td>
                                    <td>
                                        <span class="badge badge-<?= (int) $servico['ativo'] === 1 ? 'success' : 'secondary' ?>">
                                            <?= (int) $servico['ativo'] === 1 ? 'Ativo' : 'Inativo' ?>
                                        </span>
                                    </td>
                                    <td class="text-right servicos-acoes">
                                        <a
                                            href="cadastro-servico.php?editar=<?= (int) $servico['id'] ?>"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            Editar
                                        </a>

                                        <form
                                            action="api/servicos.php"
                                            method="post"
                                            class="d-inline"
                                        >
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
                                            >
                                            <input type="hidden" name="acao" value="alternar_status">
                                            <input type="hidden" name="servico_id" value="<?= (int) $servico['id'] ?>">

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-secondary"
                                            >
                                                <?= (int) $servico['ativo'] === 1 ? 'Desativar' : 'Ativar' ?>
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
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
