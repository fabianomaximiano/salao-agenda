<?php

$currentPage = basename($_SERVER['PHP_SELF']);

function menuAtivo(array $paginas): string
{
    global $currentPage;

    return in_array($currentPage, $paginas, true)
        ? 'active'
        : '';
}

?>

<aside class="app-sidebar">

    <div class="app-sidebar-brand">

        <img
            src="assets/img/logo-placeholder.svg"
            alt="Agenda"
        >

        <strong>
            Agenda
        </strong>

    </div>


    <nav class="app-sidebar-nav">

        <div class="app-sidebar-title">
            Visão geral
        </div>

        <a
            href="dashboard.php"
            class="app-sidebar-link <?= menuAtivo(['dashboard.php']) ?>"
        >
            Dashboard
        </a>


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


        <div class="app-sidebar-title mt-3">
            Cadastros
        </div>


        <a
            href="clientes.php"
            class="app-sidebar-link <?= menuAtivo(['clientes.php']) ?>"
        >
            Clientes
        </a>


        <a
            href="profissionais.php"
            class="app-sidebar-link <?= menuAtivo([
                'profissionais.php',
                'cadastro-profissional.php'
            ]) ?>"
        >
            Profissionais
        </a>


        <a
            href="servicos.php"
            class="app-sidebar-link <?= menuAtivo([
                'servicos.php',
                'cadastro-servico.php'
            ]) ?>"
        >
            Serviços
        </a>

    </nav>

</aside>

<div
    class="app-overlay"
    id="appOverlay"
></div>