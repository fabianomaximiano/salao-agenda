<?php

$currentPage = basename($_SERVER['PHP_SELF']);
$contextoAtual = (string) ($_SESSION['contexto'] ?? '');

$ehAdministrador = $contextoAtual === 'administrador';
$ehColaborador = $contextoAtual === 'colaborador';
$ehProfissional = $contextoAtual === 'profissional';

function menuAtivo(array $paginas): string
{
    global $currentPage;

    return in_array($currentPage, $paginas, true) ? 'active' : '';
}

function podeMenu(string $permissao): bool
{
    global $ehAdministrador, $ehColaborador;

    if ($ehAdministrador) {
        return true;
    }

    if (!$ehColaborador) {
        return false;
    }

    return colaboradorPode($permissao);
}

?>
<aside class="app-sidebar">
    <div class="app-sidebar-brand">
        <img src="assets/img/logo-placeholder.svg" alt="Agenda">
        <strong>Agenda</strong>
    </div>

    <nav class="app-sidebar-nav">
        <?php if ($ehProfissional): ?>
            <div class="app-sidebar-title">Visão geral</div>

            <a
                href="dashboard-profissional.php"
                class="app-sidebar-link <?= menuAtivo(['dashboard-profissional.php']) ?>"
            >
                Dashboard
            </a>

            <div class="app-sidebar-title mt-3">Agenda</div>

            <a
                href="agenda.php"
                class="app-sidebar-link <?= menuAtivo(['agenda.php']) ?>"
            >
                Minha agenda
            </a>

            <a
                href="horarios-profissionais.php"
                class="app-sidebar-link <?= menuAtivo(['horarios-profissionais.php']) ?>"
            >
                Meus horários
            </a>

            <div class="app-sidebar-title mt-3">Conta</div>

            <a
                href="meus-dados.php"
                class="app-sidebar-link <?= menuAtivo(['meus-dados.php']) ?>"
            >
                Meus dados
            </a>
        <?php else: ?>
            <div class="app-sidebar-title">Visão geral</div>

            <a
                href="dashboard.php"
                class="app-sidebar-link <?= menuAtivo(['dashboard.php']) ?>"
            >
                Dashboard
            </a>

            <?php if (podeMenu('agenda')): ?>
                <a
                    href="agenda.php"
                    class="app-sidebar-link <?= menuAtivo(['agenda.php']) ?>"
                >
                    Agenda
                </a>

                <a
                    href="agendamentos.php"
                    class="app-sidebar-link <?= menuAtivo(['agendamentos.php']) ?>"
                >
                    Agendamentos
                </a>
            <?php endif; ?>

            <?php if (podeMenu('clientes') || podeMenu('profissionais') || podeMenu('servicos') || $ehAdministrador): ?>
                <div class="app-sidebar-title mt-3">Cadastros</div>
            <?php endif; ?>

            <?php if (podeMenu('clientes')): ?>
                <a
                    href="clientes.php"
                    class="app-sidebar-link <?= menuAtivo(['clientes.php']) ?>"
                >
                    Clientes
                </a>
            <?php endif; ?>

            <?php if (podeMenu('profissionais')): ?>
                <a
                    href="profissionais.php"
                    class="app-sidebar-link <?= menuAtivo(['profissionais.php', 'cadastro-profissional.php']) ?>"
                >
                    Profissionais
                </a>
            <?php endif; ?>

            <?php if (podeMenu('servicos')): ?>
                <a
                    href="servicos.php"
                    class="app-sidebar-link <?= menuAtivo(['servicos.php', 'cadastro-servico.php']) ?>"
                >
                    Serviços
                </a>
            <?php endif; ?>

            <?php if ($ehAdministrador): ?>
                <a
                    href="colaboradores.php"
                    class="app-sidebar-link <?= menuAtivo(['colaboradores.php', 'cadastro-colaborador.php']) ?>"
                >
                    Colaboradores
                </a>

                <div class="app-sidebar-title mt-3">Configurações</div>

                <a
                    href="dados-empresa.php"
                    class="app-sidebar-link <?= menuAtivo(['dados-empresa.php']) ?>"
                >
                    Dados da empresa
                </a>

                <a
                    href="horario-funcionamento.php"
                    class="app-sidebar-link <?= menuAtivo(['horario-funcionamento.php']) ?>"
                >
                    Horário de funcionamento
                </a>

                <a
                    href="identidade-visual.php"
                    class="app-sidebar-link <?= menuAtivo(['identidade-visual.php']) ?>"
                >
                    Identidade visual
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </nav>
</aside>

<div class="app-overlay" id="appOverlay"></div>
