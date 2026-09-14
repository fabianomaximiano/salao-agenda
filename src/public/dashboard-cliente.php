<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

exigirCliente();

$nome = trim((string) ($_SESSION['user_name'] ?? 'Cliente'));
$empresa = trim((string) ($_SESSION['empresa_nome'] ?? ''));

$primeiroNome = $nome !== '' ? explode(' ', $nome)[0] : 'Cliente';

function hCliente(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <title>Minha área<?= $empresa !== '' ? ' | ' . hCliente($empresa) : '' ?></title>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
    >
    <link rel="stylesheet" href="assets/css/cliente-area.css?v=20260914-2">
</head>
<body>
    <div class="cliente-area-page">
        <header class="cliente-area-topbar">
            <div class="container cliente-area-container">
                <div class="cliente-area-topbar-inner">
                    <a class="cliente-area-brand" href="dashboard-cliente.php">
                        <?= hCliente($empresa !== '' ? $empresa : 'Minha área') ?>
                    </a>

                    <div class="cliente-area-user">
                        <div class="cliente-area-user-text">
                            <strong><?= hCliente($nome) ?></strong>
                            <span>Cliente</span>
                        </div>

                        <a class="btn btn-outline-secondary btn-sm" href="alterar-senha.php">
                            Alterar senha
                        </a>

                        <a class="btn btn-link btn-sm cliente-area-logout" href="logout.php">
                            Sair
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="container cliente-area-container cliente-area-main">
            <section class="cliente-area-hero">
                <div class="cliente-area-hero-content">
                    <span class="cliente-area-eyebrow">Minha área</span>
                    <h1>Olá, <?= hCliente($primeiroNome) ?>!</h1>
                    <p>
                        Aqui você poderá cuidar dos seus dados e acompanhar seus
                        agendamentos com <?= hCliente($empresa !== '' ? $empresa : 'a empresa') ?>.
                    </p>
                </div>

                <div class="cliente-area-hero-action">
                    <span class="cliente-area-hero-action-label">Próxima etapa</span>
                    <strong>Agendamento online</strong>
                    <span>Em breve nesta área.</span>
                </div>
            </section>

            <section class="cliente-area-section" aria-labelledby="cliente-acoes-title">
                <div class="cliente-area-section-heading">
                    <div>
                        <h2 id="cliente-acoes-title">O que você quer fazer?</h2>
                        <p>Acesse rapidamente as principais áreas da sua conta.</p>
                    </div>
                </div>

                <div class="cliente-area-actions-grid">
                    <a
                        class="cliente-area-action-card cliente-area-action-link cliente-area-action-primary"
                        href="meus-dados-cliente.php"
                    >
                        <div class="cliente-area-action-icon" aria-hidden="true">01</div>
                        <div class="cliente-area-action-content">
                            <h3>Meus dados</h3>
                            <p>
                                Consulte e mantenha seus dados pessoais, telefone e endereço atualizados.
                            </p>
                        </div>
                        <span class="cliente-area-action-status">Acessar</span>
                    </a>

                    <article class="cliente-area-action-card">
                        <div class="cliente-area-action-icon" aria-hidden="true">02</div>
                        <div class="cliente-area-action-content">
                            <h3>Meus agendamentos</h3>
                            <p>
                                Acompanhe seus próximos horários e os serviços reservados.
                            </p>
                        </div>
                        <span class="cliente-area-action-status">Em breve</span>
                    </article>

                    <article class="cliente-area-action-card">
                        <div class="cliente-area-action-icon" aria-hidden="true">03</div>
                        <div class="cliente-area-action-content">
                            <h3>Histórico</h3>
                            <p>
                                Consulte atendimentos anteriores e facilite novos agendamentos.
                            </p>
                        </div>
                        <span class="cliente-area-action-status">Em breve</span>
                    </article>
                </div>
            </section>

            <section class="cliente-area-summary">
                <div class="cliente-area-summary-item">
                    <span>Próximo agendamento</span>
                    <strong>—</strong>
                    <small>Nenhum agendamento disponível.</small>
                </div>

                <div class="cliente-area-summary-item">
                    <span>Atendimentos concluídos</span>
                    <strong>0</strong>
                    <small>Seu histórico aparecerá aqui.</small>
                </div>

                <div class="cliente-area-summary-item">
                    <span>Avaliações pendentes</span>
                    <strong>0</strong>
                    <small>Após um atendimento concluído.</small>
                </div>
            </section>

            <section class="cliente-area-info-card">
                <div>
                    <span class="cliente-area-eyebrow">Sua conta</span>
                    <h2>Seus dados são administrados por você</h2>
                    <p>
                        Use <strong>Meus dados</strong> para manter suas informações pessoais,
                        telefone e endereço atualizados. CPF e e-mail de acesso permanecem protegidos.
                    </p>
                </div>
            </section>
        </main>

        <footer class="cliente-area-footer">
            <div class="container cliente-area-container">
                <span><?= hCliente($empresa !== '' ? $empresa : 'Área do cliente') ?></span>
                <span>Área do cliente</span>
            </div>
        </footer>
    </div>
</body>
</html>
