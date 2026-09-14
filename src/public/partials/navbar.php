<?php

$usuarioNome = (string) ($_SESSION['user_name'] ?? 'Administrador');
$empresaNome = (string) ($_SESSION['empresa_nome'] ?? 'Minha empresa');
$navbarContexto = (string) ($_SESSION['contexto'] ?? '');

$navbarUsuarioPerfil = match ($navbarContexto) {
    'administrador' => 'Administrador',
    'colaborador' => 'Colaborador',
    'profissional' => 'Profissional',
    default => 'Usuário',
};

$iniciais = '';
$partesNome = preg_split('/\s+/', trim($usuarioNome)) ?: [];

if (!empty($partesNome[0])) {
    $iniciais .= mb_strtoupper(mb_substr($partesNome[0], 0, 1));
}

if (count($partesNome) > 1) {
    $ultimoNome = end($partesNome);

    if (is_string($ultimoNome) && $ultimoNome !== '') {
        $iniciais .= mb_strtoupper(mb_substr($ultimoNome, 0, 1));
    }
}

$navbarFotoUrl = null;

if (
    $navbarContexto === 'profissional'
    && !empty($_SESSION['empresa_id'])
    && !empty($_SESSION['profissional_id'])
) {
    require_once __DIR__ . '/../../includes/db.php';
    require_once __DIR__ . '/../../services/ImagemProfissionalService.php';

    $navbarPdo = getDB();

    $stmtNavbarFoto = $navbarPdo->prepare(
        'SELECT foto_url
         FROM profissionais
         WHERE id = :profissional_id
           AND empresa_id = :empresa_id
         LIMIT 1'
    );
    $stmtNavbarFoto->execute([
        ':profissional_id' => (int) $_SESSION['profissional_id'],
        ':empresa_id' => (int) $_SESSION['empresa_id'],
    ]);

    $fotoBanco = $stmtNavbarFoto->fetchColumn();

    if (is_string($fotoBanco) && $fotoBanco !== '') {
        $fotoUrls = ImagemProfissionalService::urls($fotoBanco);
        $navbarFotoUrl = is_string($fotoUrls['p'] ?? null) && $fotoUrls['p'] !== ''
            ? $fotoUrls['p']
            : null;
    }
}

?>

<div class="app-main">
    <header class="app-navbar">
        <div class="app-navbar-content">
            <div class="app-navbar-left">
                <button
                    type="button"
                    class="app-mobile-menu"
                    id="appMobileMenu"
                    aria-label="Abrir menu"
                >
                    ☰
                </button>

                <p class="app-navbar-title">
                    <?= htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>

            <div class="app-navbar-right">
                <div class="app-user">
                    <div class="app-user-avatar">
                        <?php if ($navbarFotoUrl !== null): ?>
                            <img
                                src="<?= htmlspecialchars($navbarFotoUrl, ENT_QUOTES, 'UTF-8') ?>"
                                width="40"
                                height="40"
                                class="rounded-circle"
                                alt="Foto de <?= htmlspecialchars($usuarioNome, ENT_QUOTES, 'UTF-8') ?>"
                                decoding="async"
                            >
                        <?php else: ?>
                            <?= htmlspecialchars($iniciais ?: 'A', ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </div>

                    <div class="app-user-info">
                        <strong>
                            <?= htmlspecialchars($usuarioNome, ENT_QUOTES, 'UTF-8') ?>
                        </strong>

                        <small>
                            <?= htmlspecialchars($navbarUsuarioPerfil, ENT_QUOTES, 'UTF-8') ?>
                        </small>
                    </div>

                    <a
                        href="alterar-senha.php"
                        class="btn btn-sm btn-outline-primary ml-3"
                    >
                        Alterar senha
                    </a>

                    <a
                        href="logout.php"
                        class="btn btn-sm btn-outline-secondary ml-2"
                    >
                        Sair
                    </a>
                </div>
            </div>
        </div>
    </header>
