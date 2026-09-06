<?php

$usuarioNome = $_SESSION['user_name'] ?? 'Administrador';

$empresaNome = $_SESSION['empresa_nome'] ?? 'Minha empresa';

$iniciais = '';

$partesNome = preg_split(
    '/\s+/',
    trim($usuarioNome)
);

if (!empty($partesNome[0])) {
    $iniciais .= mb_strtoupper(
        mb_substr($partesNome[0], 0, 1)
    );
}

if (count($partesNome) > 1) {

    $ultimoNome = end($partesNome);

    $iniciais .= mb_strtoupper(
        mb_substr($ultimoNome, 0, 1)
    );
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
                    <?= htmlspecialchars(
                        $empresaNome,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>

            </div>


            <div class="app-navbar-right">

                <div class="app-user">

                    <div class="app-user-avatar">
                        <?= htmlspecialchars(
                            $iniciais ?: 'A',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>

                    <div class="app-user-info">

                        <strong>
                            <?= htmlspecialchars(
                                $usuarioNome,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </strong>

                        <small>
                            Administrador
                        </small>

                    </div>

                    <a
                        href="logout.php"
                        class="btn btn-sm btn-outline-secondary ml-3"
                    >
                        Sair
                    </a>

                </div>

            </div>

        </div>

    </header>