<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigirAdministrador();

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();

if (empty($_SESSION['csrf_clientes'])) {
    $_SESSION['csrf_clientes'] = bin2hex(random_bytes(32));
}

$csrf = (string) $_SESSION['csrf_clientes'];
$flash = $_SESSION['flash_lista_clientes'] ?? null;
unset($_SESSION['flash_lista_clientes']);

$stmt = $pdo->prepare(
    'SELECT
        c.id,
        c.usuario_id,
        c.observacoes,
        c.ativo,
        p.nome_completo,
        p.cpf,
        p.email,
        tp.numero AS telefone,
        tp.whatsapp,
        u.ativo AS usuario_ativo
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
     LEFT JOIN usuarios u
       ON u.id = c.usuario_id
     WHERE c.empresa_id = :empresa_id
     ORDER BY c.ativo DESC, p.nome_completo ASC'
);
$stmt->execute([':empresa_id' => $empresaId]);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Clientes';
$pageCss = 'clientes.css';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/partials/navbar.php';
?>

<main class="app-content">
    <div class="app-page-header d-md-flex justify-content-between align-items-center">
        <div>
            <h1>Clientes</h1>
            <p>Gerencie os clientes da empresa.</p>
        </div>

        <div class="mt-3 mt-md-0">
            <a class="btn btn-primary" href="cadastro-cliente-admin.php">Cadastrar cliente</a>
        </div>
    </div>

    <?php if (is_array($flash)): ?>
        <div class="alert alert-<?= ($flash['tipo'] ?? '') === 'success' ? 'success' : 'danger' ?>" role="alert">
            <?= htmlspecialchars((string) ($flash['mensagem'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!$clientes): ?>
        <div class="app-card">
            <div class="app-empty-state">
                <h3>Nenhum cliente cadastrado</h3>
                <p>Cadastre o primeiro cliente.</p>
                <a class="btn btn-primary" href="cadastro-cliente-admin.php">Cadastrar primeiro cliente</a>
            </div>
        </div>
    <?php else: ?>
        <div class="clientes-grid">
            <?php foreach ($clientes as $cliente): ?>
                <?php
                $temAcesso = !empty($cliente['usuario_id']);
                $acessoAtivo = $temAcesso && (int) ($cliente['usuario_ativo'] ?? 0) === 1;
                $clienteAtivo = (int) $cliente['ativo'] === 1;
                ?>

                <article class="cliente-card<?= $clienteAtivo ? '' : ' cliente-card-inativo' ?>">
                    <div class="cliente-card-topo">
                        <div class="cliente-identificacao">
                            <h2><?= htmlspecialchars((string) $cliente['nome_completo'], ENT_QUOTES, 'UTF-8') ?></h2>

                            <?php if (!empty($cliente['cpf'])): ?>
                                <p class="cliente-cpf">
                                    <?= htmlspecialchars((string) $cliente['cpf'], ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <span class="cliente-status <?= $clienteAtivo ? 'cliente-status-ativo' : 'cliente-status-inativo' ?>">
                            <?= $clienteAtivo ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </div>

                    <div class="cliente-card-corpo">
                        <div class="cliente-info">
                            <span class="cliente-info-label">Telefone</span>
                            <div class="cliente-info-valor">
                                <?php if (!empty($cliente['telefone'])): ?>
                                    <span><?= htmlspecialchars((string) $cliente['telefone'], ENT_QUOTES, 'UTF-8') ?></span>

                                    <?php if ((int) ($cliente['whatsapp'] ?? 0) === 1): ?>
                                        <span class="cliente-whatsapp">WhatsApp</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="cliente-info-vazio">Não informado</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="cliente-info">
                            <span class="cliente-info-label">E-mail</span>
                            <div class="cliente-info-valor">
                                <?php if (!empty($cliente['email'])): ?>
                                    <span class="cliente-email">
                                        <?= htmlspecialchars((string) $cliente['email'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="cliente-info-vazio">Não informado</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="cliente-info">
                            <span class="cliente-info-label">Acesso</span>
                            <div class="cliente-info-valor">
                                <?php if (!$temAcesso): ?>
                                    <span class="cliente-acesso cliente-acesso-neutro">Não vinculado</span>
                                <?php elseif ($acessoAtivo): ?>
                                    <span class="cliente-acesso cliente-acesso-ativo">Ativo</span>
                                <?php else: ?>
                                    <span class="cliente-acesso cliente-acesso-pendente">Vinculado / inativo</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($cliente['observacoes'])): ?>
                            <div class="cliente-observacoes">
                                <?= nl2br(htmlspecialchars((string) $cliente['observacoes'], ENT_QUOTES, 'UTF-8')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="cliente-card-acoes">
                        <a
                            class="btn btn-outline-primary btn-sm"
                            href="cadastro-cliente-admin.php?editar=<?= (int) $cliente['id'] ?>"
                        >
                            Editar
                        </a>

                        <form action="api/clientes.php" method="post">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"
                            >
                            <input type="hidden" name="acao" value="alternar_status">
                            <input type="hidden" name="cliente_id" value="<?= (int) $cliente['id'] ?>">

                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                <?= $clienteAtivo ? 'Desativar' : 'Ativar' ?>
                            </button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <p class="clientes-total"><?= count($clientes) ?> cliente(s) cadastrado(s).</p>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
