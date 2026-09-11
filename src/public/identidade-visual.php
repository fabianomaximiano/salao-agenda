<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigirAdministrador();

if (
    empty($_SESSION['csrf_identidade_visual'])
    || !is_string($_SESSION['csrf_identidade_visual'])
) {
    $_SESSION['csrf_identidade_visual'] = bin2hex(random_bytes(32));
}

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();

$stmt = $pdo->prepare(
    'SELECT logo_arquivo, cor_primaria, cor_secundaria
     FROM empresa_identidade_visual
     WHERE empresa_id = :empresa_id
     LIMIT 1'
);
$stmt->execute([':empresa_id' => $empresaId]);
$identidade = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$logoArquivo = isset($identidade['logo_arquivo']) && is_string($identidade['logo_arquivo'])
    ? $identidade['logo_arquivo']
    : '';
$corPrimaria = isset($identidade['cor_primaria']) && is_string($identidade['cor_primaria'])
    ? $identidade['cor_primaria']
    : '';
$corSecundaria = isset($identidade['cor_secundaria']) && is_string($identidade['cor_secundaria'])
    ? $identidade['cor_secundaria']
    : '';

$mensagem = $_SESSION['identidade_visual_sucesso'] ?? null;
$erro = $_SESSION['identidade_visual_erro'] ?? null;
unset($_SESSION['identidade_visual_sucesso'], $_SESSION['identidade_visual_erro']);

$pageTitle = 'Identidade visual';
$pageCss = 'identidade-visual.css';
$pageJs = 'identidade-visual.js';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/partials/navbar.php';
?>

<main class="app-content">
    <div class="app-page-header">
        <h1>Identidade visual</h1>
        <p>Configure os elementos básicos da marca da sua empresa.</p>
    </div>

    <?php if (is_string($mensagem) && $mensagem !== ''): ?>
        <div class="alert alert-success" role="status">
            <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (is_string($erro) && $erro !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12 col-xl-7 mb-4">
            <div class="app-card">
                <div class="app-card-header">
                    <h2>Marca da empresa</h2>
                </div>

                <div class="app-card-body">
                    <form
                        action="api/identidade-visual.php"
                        method="post"
                        enctype="multipart/form-data"
                        id="identidadeVisualForm"
                        novalidate
                    >
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(
                                $_SESSION['csrf_identidade_visual'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                        <div class="form-group">
                            <label for="logo">Logo da empresa</label>

                            <?php if ($logoArquivo !== ''): ?>
                                <div class="identidade-logo-atual mb-3">
                                    <img
                                        src="<?= htmlspecialchars($logoArquivo, ENT_QUOTES, 'UTF-8') ?>"
                                        alt="Logo atual da empresa"
                                    >
                                </div>
                            <?php endif; ?>

                            <input
                                type="file"
                                class="form-control-file"
                                id="logo"
                                name="logo"
                                accept="image/jpeg,image/png,image/webp"
                            >
                            <small class="form-text text-muted">
                                JPG, PNG ou WebP. Máximo de 8 MB. O arquivo será convertido para WebP.
                            </small>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="cor_primaria">Cor principal</label>
                                <div class="identidade-cor-control">
                                    <input
                                        type="color"
                                        class="identidade-color-picker"
                                        id="cor_primaria_picker"
                                        aria-label="Selecionar cor principal"
                                    >
                                    <input
                                        type="text"
                                        class="form-control identidade-hex"
                                        id="cor_primaria"
                                        name="cor_primaria"
                                        value="<?= htmlspecialchars($corPrimaria, ENT_QUOTES, 'UTF-8') ?>"
                                        placeholder="#RRGGBB"
                                        maxlength="7"
                                        autocomplete="off"
                                        spellcheck="false"
                                    >
                                </div>
                                <div class="invalid-feedback">Informe uma cor hexadecimal válida.</div>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="cor_secundaria">Cor secundária</label>
                                <div class="identidade-cor-control">
                                    <input
                                        type="color"
                                        class="identidade-color-picker"
                                        id="cor_secundaria_picker"
                                        aria-label="Selecionar cor secundária"
                                    >
                                    <input
                                        type="text"
                                        class="form-control identidade-hex"
                                        id="cor_secundaria"
                                        name="cor_secundaria"
                                        value="<?= htmlspecialchars($corSecundaria, ENT_QUOTES, 'UTF-8') ?>"
                                        placeholder="#RRGGBB"
                                        maxlength="7"
                                        autocomplete="off"
                                        spellcheck="false"
                                    >
                                </div>
                                <div class="invalid-feedback">Informe uma cor hexadecimal válida.</div>
                            </div>
                        </div>

                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mt-4">
                            <a href="dashboard.php" class="btn btn-outline-secondary mb-2 mb-sm-0">
                                Voltar ao dashboard
                            </a>
                            <button type="submit" class="btn btn-primary">
                                Salvar identidade visual
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5 mb-4">
            <div class="app-card identidade-preview-card">
                <div class="app-card-header">
                    <h2>Prévia da marca</h2>
                </div>
                <div class="app-card-body">
                    <div class="identidade-preview" id="identidadePreview">
                        <div class="identidade-preview-logo" id="previewLogo">
                            <?php if ($logoArquivo !== ''): ?>
                                <img
                                    src="<?= htmlspecialchars($logoArquivo, ENT_QUOTES, 'UTF-8') ?>"
                                    alt="Prévia do logo"
                                >
                            <?php else: ?>
                                <span>Logo</span>
                            <?php endif; ?>
                        </div>

                        <strong class="identidade-preview-nome">
                            <?= htmlspecialchars(
                                (string) ($_SESSION['empresa_nome'] ?? 'Sua empresa'),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </strong>

                        <div class="identidade-preview-cores">
                            <div>
                                <span>Principal</span>
                                <i id="previewCorPrimaria"></i>
                            </div>
                            <div>
                                <span>Secundária</span>
                                <i id="previewCorSecundaria"></i>
                            </div>
                        </div>
                    </div>

                    <p class="text-muted small mb-0 mt-3">
                        As cores são opcionais e representam tokens da marca. Cada sistema consumidor decide como aplicá-las.
                    </p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
