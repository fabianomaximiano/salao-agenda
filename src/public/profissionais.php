<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigirAdministrador();

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();

if (empty($_SESSION['csrf_profissionais'])) {
    $_SESSION['csrf_profissionais'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_profissionais'];

$flash = $_SESSION['flash_lista_profissionais'] ?? null;
unset($_SESSION['flash_lista_profissionais']);

$mensagem = is_string($flash['mensagem'] ?? null) ? $flash['mensagem'] : null;
$tipoMensagem = ($flash['tipo'] ?? '') === 'success' ? 'success' : 'danger';

$stmt = $pdo->prepare(
    'SELECT
        pr.id,
        pr.cargo,
        pr.ativo,
        p.nome_completo,
        p.email,
        tp.numero AS telefone,
        tp.whatsapp,
        COUNT(DISTINCT CASE WHEN ps.ativo = 1 THEN ps.servico_id END) AS total_servicos,
        GROUP_CONCAT(
            DISTINCT CASE WHEN ps.ativo = 1 THEN s.nome END
            ORDER BY s.nome ASC
            SEPARATOR ", "
        ) AS servicos_nomes
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
     LEFT JOIN profissional_servicos ps
       ON ps.profissional_id = pr.id
     LEFT JOIN servicos s
       ON s.id = ps.servico_id
      AND s.empresa_id = pr.empresa_id
     WHERE pr.empresa_id = :empresa_id
     GROUP BY
        pr.id,
        pr.cargo,
        pr.ativo,
        p.nome_completo,
        p.email,
        tp.numero,
        tp.whatsapp
     ORDER BY pr.ativo DESC, p.nome_completo ASC'
);
$stmt->execute([':empresa_id' => $empresaId]);
$profissionais = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Profissionais';
$pageCss = 'profissionais.css';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/partials/navbar.php';
?>

<main class="app-content">
    <div class="app-page-header d-md-flex justify-content-between align-items-center">
        <div>
            <h1>Profissionais</h1>
            <p>Gerencie a equipe e os serviços executados por cada profissional.</p>
        </div>

        <div class="mt-3 mt-md-0">
            <a href="cadastro-profissional.php" class="btn btn-primary">
                Cadastrar profissional
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
            <?php if (!$profissionais): ?>
                <div class="app-empty-state">
                    <h3>Nenhum profissional cadastrado</h3>
                    <p>Cadastre quem realizará os serviços e depois configure a disponibilidade na agenda.</p>
                    <a href="cadastro-profissional.php" class="btn btn-primary">
                        Cadastrar primeiro profissional
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 profissionais-table">
                        <thead>
                            <tr>
                                <th>Profissional</th>
                                <th>Contato</th>
                                <th>Serviços</th>
                                <th>Status</th>
                                <th class="text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($profissionais as $profissional): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($profissional['nome_completo'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <?php if (!empty($profissional['cargo'])): ?>
                                            <small class="d-block text-muted">
                                                <?= htmlspecialchars($profissional['cargo'], ENT_QUOTES, 'UTF-8') ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($profissional['email'])): ?>
                                            <span class="d-block">
                                                <?= htmlspecialchars($profissional['email'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if (!empty($profissional['telefone'])): ?>
                                            <small class="d-block text-muted">
                                                <?= htmlspecialchars($profissional['telefone'], ENT_QUOTES, 'UTF-8') ?>
                                                <?= (int) $profissional['whatsapp'] === 1 ? ' • WhatsApp' : '' ?>
                                            </small>
                                        <?php endif; ?>

                                        <?php if (empty($profissional['email']) && empty($profissional['telefone'])): ?>
                                            <span class="text-muted">Não informado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int) $profissional['total_servicos'] > 0): ?>
                                            <span><?= (int) $profissional['total_servicos'] ?> serviço(s)</span>
                                            <small class="d-block text-muted profissionais-servicos-resumo">
                                                <?= htmlspecialchars((string) $profissional['servicos_nomes'], ENT_QUOTES, 'UTF-8') ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">Nenhum serviço</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= (int) $profissional['ativo'] === 1 ? 'success' : 'secondary' ?>">
                                            <?= (int) $profissional['ativo'] === 1 ? 'Ativo' : 'Inativo' ?>
                                        </span>
                                    </td>
                                    <td class="text-right profissionais-acoes">
                                        <a
                                            href="cadastro-profissional.php?editar=<?= (int) $profissional['id'] ?>"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            Editar
                                        </a>

                                        <form action="api/profissionais.php" method="post" class="d-inline">
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
                                            >
                                            <input type="hidden" name="acao" value="alternar_status">
                                            <input type="hidden" name="profissional_id" value="<?= (int) $profissional['id'] ?>">

                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                <?= (int) $profissional['ativo'] === 1 ? 'Desativar' : 'Ativar' ?>
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
