<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../services/ImagemProfissionalService.php';

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

$stmtIdentidade = $pdo->prepare(
    'SELECT cor_primaria, cor_secundaria
     FROM empresa_identidade_visual
     WHERE empresa_id = :empresa_id
     LIMIT 1'
);
$stmtIdentidade->execute([':empresa_id' => $empresaId]);
$identidade = $stmtIdentidade->fetch(PDO::FETCH_ASSOC) ?: [];
$corPrimaria = is_string($identidade['cor_primaria'] ?? null)
    && preg_match('/^#[0-9A-Fa-f]{6}$/', $identidade['cor_primaria'])
    ? $identidade['cor_primaria']
    : '#6C757D';
$corSecundaria = is_string($identidade['cor_secundaria'] ?? null)
    && preg_match('/^#[0-9A-Fa-f]{6}$/', $identidade['cor_secundaria'])
    ? $identidade['cor_secundaria']
    : $corPrimaria;

$stmt = $pdo->prepare(
    'SELECT
        pr.id,
        pr.cargo,
        pr.descricao,
        pr.foto_url,
        pr.ativo,
        p.nome_completo,
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
     LEFT JOIN profissional_servicos ps
       ON ps.profissional_id = pr.id
     LEFT JOIN servicos s
       ON s.id = ps.servico_id
      AND s.empresa_id = pr.empresa_id
     WHERE pr.empresa_id = :empresa_id
     GROUP BY
        pr.id,
        pr.cargo,
        pr.descricao,
        pr.foto_url,
        pr.ativo,
        p.nome_completo
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
            <a href="cadastro-profissional.php" class="btn btn-primary">Cadastrar profissional</a>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipoMensagem ?>" role="alert">
            <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!$profissionais): ?>
        <div class="app-card">
            <div class="app-empty-state">
                <h3>Nenhum profissional cadastrado</h3>
                <p>Cadastre quem realizará os serviços e depois configure a disponibilidade na agenda.</p>
                <a href="cadastro-profissional.php" class="btn btn-primary">Cadastrar primeiro profissional</a>
            </div>
        </div>
    <?php else: ?>
        <div
            class="profissionais-grid"
            style="--marca-primaria: <?= htmlspecialchars($corPrimaria, ENT_QUOTES, 'UTF-8') ?>; --marca-secundaria: <?= htmlspecialchars($corSecundaria, ENT_QUOTES, 'UTF-8') ?>;"
        >
            <?php foreach ($profissionais as $profissional): ?>
                <?php
                $nome = (string) $profissional['nome_completo'];
                $fotoUrls = ImagemProfissionalService::urls(
                    is_string($profissional['foto_url'] ?? null) ? $profissional['foto_url'] : null
                );
                $iniciais = ImagemProfissionalService::iniciais($nome);
                ?>
                <article class="profissional-card<?= (int) $profissional['ativo'] === 1 ? '' : ' profissional-card-inativo' ?>">
                    <div class="profissional-card-status">
                        <span class="badge badge-<?= (int) $profissional['ativo'] === 1 ? 'success' : 'secondary' ?>">
                            <?= (int) $profissional['ativo'] === 1 ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </div>

                    <div class="profissional-foto-moldura">
                        <?php if ($fotoUrls['m']): ?>
                            <img
                                class="profissional-foto"
                                src="<?= htmlspecialchars((string) $fotoUrls['m'], ENT_QUOTES, 'UTF-8') ?>"
                                srcset="<?= htmlspecialchars((string) $fotoUrls['p'], ENT_QUOTES, 'UTF-8') ?> 160w, <?= htmlspecialchars((string) $fotoUrls['m'], ENT_QUOTES, 'UTF-8') ?> 320w, <?= htmlspecialchars((string) $fotoUrls['g'], ENT_QUOTES, 'UTF-8') ?> 500w"
                                sizes="(max-width: 575px) 160px, 180px"
                                alt="Foto de <?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>"
                                loading="lazy"
                                decoding="async"
                            >
                        <?php else: ?>
                            <div class="profissional-foto profissional-foto-iniciais" aria-hidden="true">
                                <?= htmlspecialchars($iniciais, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="profissional-card-corpo">
                        <h2><?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?></h2>

                        <?php if (!empty($profissional['cargo'])): ?>
                            <p class="profissional-cargo"><?= htmlspecialchars((string) $profissional['cargo'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>

                        <div class="profissional-avaliacao" aria-label="Profissional ainda sem avaliações">
                            <span class="profissional-estrelas" aria-hidden="true">☆☆☆☆☆</span>
                            <span>Sem avaliações</span>
                        </div>

                        <?php if (!empty($profissional['descricao'])): ?>
                            <p class="profissional-descricao"><?= htmlspecialchars((string) $profissional['descricao'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>

                        <?php if ((int) $profissional['total_servicos'] > 0): ?>
                            <p class="profissional-servicos">
                                <strong><?= (int) $profissional['total_servicos'] ?> serviço(s)</strong>
                                <span><?= htmlspecialchars((string) $profissional['servicos_nomes'], ENT_QUOTES, 'UTF-8') ?></span>
                            </p>
                        <?php else: ?>
                            <p class="profissional-servicos"><span>Nenhum serviço vinculado</span></p>
                        <?php endif; ?>
                    </div>

                    <div class="profissional-card-acoes">
                        <a href="cadastro-profissional.php?editar=<?= (int) $profissional['id'] ?>" class="btn btn-outline-primary btn-sm">Editar</a>

                        <form action="api/profissionais.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="acao" value="alternar_status">
                            <input type="hidden" name="profissional_id" value="<?= (int) $profissional['id'] ?>">
                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                <?= (int) $profissional['ativo'] === 1 ? 'Desativar' : 'Ativar' ?>
                            </button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <p class="text-muted small mt-3 mb-0"><?= count($profissionais) ?> profissional(is) cadastrado(s).</p>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
