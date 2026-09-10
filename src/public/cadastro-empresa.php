<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/db.php';

$pdo = getDB();

$stmt = $pdo->query(
    "SELECT id, nome
     FROM segmentos
     WHERE ativo = 1
     ORDER BY nome"
);

$segmentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$erro = $_SESSION['cadastro_empresa_erro'] ?? null;
$sucesso = $_SESSION['cadastro_empresa_sucesso'] ?? null;

unset(
    $_SESSION['cadastro_empresa_erro'],
    $_SESSION['cadastro_empresa_sucesso']
);

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, shrink-to-fit=no"
    >

    <title>Cadastrar empresa | Agenda</title>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/cadastro-empresa.css"
    >
</head>

<body>

<div class="page-wrapper">

    <div class="container py-4 py-md-5">

        <div class="row justify-content-center">

            <div class="col-12 col-xl-10">

                <div class="cadastro-card">

                    <div class="row no-gutters">

                        <div class="col-lg-4 cadastro-sidebar">

                            <div class="sidebar-content">

                                <div class="brand-box mb-4">
                                    <img
                                        src="assets/img/logo-placeholder.svg"
                                        alt="Agenda"
                                        class="brand-logo"
                                    >
                                </div>

                                <h1 class="h3 font-weight-bold">
                                    Comece a organizar seus atendimentos
                                </h1>

                                <p class="sidebar-description">
                                    Cadastre sua empresa e indique o primeiro
                                    administrador da plataforma.
                                </p>

                                <div class="sidebar-steps">

                                    <div class="sidebar-step active">
                                        <span>1</span>

                                        <div>
                                            <strong>Empresa</strong>
                                            <small>Dados do estabelecimento</small>
                                        </div>
                                    </div>

                                    <div class="sidebar-step">
                                        <span>2</span>

                                        <div>
                                            <strong>Administrador</strong>
                                            <small>Responsável principal</small>
                                        </div>
                                    </div>

                                    <div class="sidebar-step">
                                        <span>3</span>

                                        <div>
                                            <strong>Configuração</strong>
                                            <small>Serviços e profissionais</small>
                                        </div>
                                    </div>

                                </div>

                            </div>

                        </div>

                        <div class="col-lg-8">

                            <div class="cadastro-content">

                                <div class="mb-4">

                                    <span class="page-eyebrow">
                                        Configuração inicial
                                    </span>

                                    <h2 class="h3 font-weight-bold mb-2">
                                        Cadastre sua empresa
                                    </h2>

                                    <p class="text-muted mb-0">
                                        Preencha os dados abaixo para criar
                                        o estabelecimento e indicar o administrador principal.
                                    </p>

                                </div>

                                <?php if ($erro): ?>

                                    <div
                                        class="alert alert-danger"
                                        role="alert"
                                    >
                                        <?= htmlspecialchars(
                                            $erro,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>

                                <?php endif; ?>

                                <?php if ($sucesso): ?>

                                    <div
                                        class="alert alert-success"
                                        role="status"
                                    >
                                        <?= htmlspecialchars(
                                            $sucesso,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>

                                <?php endif; ?>

                                <form
                                    id="cadastroEmpresaForm"
                                    method="post"
                                    action="cadastro-empresa.php"
                                    novalidate
                                >

                                    <!-- EMPRESA -->
                                    <section class="form-section">

                                        <div class="section-heading">
                                            <span class="section-number">1</span>

                                            <div>
                                                <h3>Dados da empresa</h3>

                                                <p>
                                                    Informações principais do estabelecimento.
                                                </p>
                                            </div>
                                        </div>

                                        <div class="form-row">

                                            <div class="form-group col-md-7">

                                                <label for="nome_fantasia">
                                                    Nome fantasia
                                                    <span class="required">*</span>
                                                </label>

                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="nome_fantasia"
                                                    name="nome_fantasia"
                                                    maxlength="150"
                                                    required
                                                >

                                                <div class="invalid-feedback">
                                                    Informe o nome da empresa.
                                                </div>

                                            </div>

                                            <div class="form-group col-md-5">

                                                <label for="segmento_id">
                                                    Segmento
                                                    <span class="required">*</span>
                                                </label>

                                                <select
                                                    class="custom-select"
                                                    id="segmento_id"
                                                    name="segmento_id"
                                                    required
                                                >

                                                    <option value="">
                                                        Selecione
                                                    </option>

                                                    <?php foreach ($segmentos as $segmento): ?>

                                                        <option
                                                            value="<?= (int) $segmento['id'] ?>"
                                                        >
                                                            <?= htmlspecialchars(
                                                                $segmento['nome'],
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>
                                                        </option>

                                                    <?php endforeach; ?>

                                                </select>

                                                <div class="invalid-feedback">
                                                    Escolha um segmento.
                                                </div>

                                            </div>

                                        </div>

                                        <div class="form-group">

                                            <label for="razao_social">
                                                Razão social
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                id="razao_social"
                                                name="razao_social"
                                                maxlength="180"
                                            >

                                        </div>

                                        <div class="form-row">

                                            <div class="form-group col-md-4">

                                                <label for="tipo_documento">
                                                    Tipo de documento
                                                    <span class="required">*</span>
                                                </label>

                                                <select
                                                    class="custom-select"
                                                    id="tipo_documento"
                                                    name="tipo_documento"
                                                    required
                                                >

                                                    <option value="">
                                                        Selecione
                                                    </option>

                                                    <option value="cnpj">
                                                        CNPJ
                                                    </option>

                                                    <option value="cpf">
                                                        CPF
                                                    </option>

                                                </select>

                                                <div class="invalid-feedback">
                                                    Escolha CPF ou CNPJ.
                                                </div>

                                            </div>

                                            <div class="form-group col-md-8">

                                                <label for="documento">
                                                    CPF / CNPJ
                                                    <span class="required">*</span>
                                                </label>

                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="documento"
                                                    name="documento"
                                                    maxlength="18"
                                                    placeholder="Selecione o tipo de documento"
                                                    inputmode="numeric"
                                                    required
                                                >

                                                <div class="invalid-feedback">
                                                    Informe um CPF ou CNPJ válido.
                                                </div>

                                            </div>

                                        </div>

                                        <div class="form-row">

                                            <div class="form-group col-md-6">

                                                <label for="email_empresa">
                                                    E-mail da empresa
                                                </label>

                                                <input
                                                    type="email"
                                                    class="form-control"
                                                    id="email_empresa"
                                                    name="email_empresa"
                                                    maxlength="190"
                                                    placeholder="contato@empresa.com.br"
                                                    autocomplete="email"
                                                >

                                                <div class="invalid-feedback">
                                                    Informe um e-mail válido.
                                                </div>

                                            </div>

                                            <div class="form-group col-md-3">

                                                <label for="telefone_empresa">
                                                    Telefone
                                                </label>

                                                <input
                                                    type="tel"
                                                    class="form-control"
                                                    id="telefone_empresa"
                                                    name="telefone_empresa"
                                                    maxlength="15"
                                                    placeholder="(11) 3333-4444"
                                                    inputmode="tel"
                                                >

                                            </div>

                                            <div class="form-group col-md-3">

                                                <label for="whatsapp_empresa">
                                                    WhatsApp
                                                </label>

                                                <input
                                                    type="tel"
                                                    class="form-control"
                                                    id="whatsapp_empresa"
                                                    name="whatsapp_empresa"
                                                    maxlength="15"
                                                    placeholder="(11) 99999-9999"
                                                    inputmode="tel"
                                                >

                                            </div>

                                        </div>

                                    </section>

                                    <!-- ENDEREÇO -->
                                    <section class="form-section">

                                        <div class="section-heading">
                                            <span class="section-number">2</span>

                                            <div>
                                                <h3>Endereço</h3>

                                                <p>
                                                    Informe o CEP para preencher o endereço automaticamente.
                                                </p>
                                            </div>
                                        </div>

                                        <div class="form-row">

                                            <div class="form-group col-md-4">

                                                <label for="cep">
                                                    CEP
                                                    <span class="required">*</span>
                                                </label>

                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="cep"
                                                    name="cep"
                                                    maxlength="9"
                                                    placeholder="00000-000"
                                                    inputmode="numeric"
                                                    autocomplete="postal-code"
                                                    required
                                                >

                                                <div class="invalid-feedback">
                                                    Informe um CEP válido.
                                                </div>

                                                <small
                                                    id="cepFeedback"
                                                    class="form-text"
                                                    aria-live="polite"
                                                ></small>

                                            </div>

                                            <div class="form-group col-md-8">

                                                <label for="logradouro">
                                                    Logradouro
                                                    <span class="required">*</span>
                                                </label>

                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="logradouro"
                                                    name="logradouro"
                                                    maxlength="180"
                                                    autocomplete="address-line1"
                                                    required
                                                >

                                                <div class="invalid-feedback">
                                                    Informe o logradouro.
                                                </div>

                                            </div>

                                        </div>

                                        <div class="form-row">

                                            <div class="form-group col-md-3">

                                                <label for="numero">
                                                    Número
                                                    <span class="required">*</span>
                                                </label>

                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="numero"
                                                    name="numero"
                                                    maxlength="30"
                                                    required
                                                >

                                                <div class="invalid-feedback">
                                                    Informe o número.
                                                </div>

                                            </div>

                                            <div class="form-group col-md-5">

                                                <label for="complemento">
                                                    Complemento
                                                </label>

                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="complemento"
                                                    name="complemento"
                                                    maxlength="120"
                                                    autocomplete="address-line2"
                                                >

                                            </div>

                                            <div class="form-group col-md-4">

                                                <label for="bairro">
                                                    Bairro
                                                    <span class="required">*</span>
                                                </label>

                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="bairro"
                                                    name="bairro"
                                                    maxlength="120"
                                                    required
                                                >

                                                <div class="invalid-feedback">
                                                    Informe o bairro.
                                                </div>

                                            </div>

                                        </div>

                                        <div class="form-row">

                                            <div class="form-group col-md-8">

                                                <label for="cidade">
                                                    Cidade
                                                    <span class="required">*</span>
                                                </label>

                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="cidade"
                                                    name="cidade"
                                                    maxlength="120"
                                                    autocomplete="address-level2"
                                                    required
                                                    readonly
                                                >

                                                <div class="invalid-feedback">
                                                    Informe a cidade.
                                                </div>

                                            </div>

                                            <div class="form-group col-md-4">

                                                <label for="estado">
                                                    UF
                                                    <span class="required">*</span>
                                                </label>

                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="estado"
                                                    name="estado"
                                                    maxlength="2"
                                                    autocomplete="address-level1"
                                                    required
                                                    readonly
                                                >

                                                <div class="invalid-feedback">
                                                    Informe a UF.
                                                </div>

                                            </div>

                                        </div>

                                    </section>

                                    <!-- ADMINISTRADOR -->
                                    <section class="form-section">

                                        <div class="section-heading">
                                            <span class="section-number">3</span>

                                            <div>
                                                <h3>Administrador principal</h3>

                                                <p>
                                                    Esta pessoa será o administrador principal da empresa.
                                                </p>
                                            </div>
                                        </div>

                                        <div class="form-group">

                                            <label for="admin_nome">
                                                Nome completo
                                                <span class="required">*</span>
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                id="admin_nome"
                                                name="admin_nome"
                                                maxlength="160"
                                                autocomplete="name"
                                                required
                                            >

                                            <div class="invalid-feedback">
                                                Informe o nome do administrador.
                                            </div>

                                        </div>

                                        <div class="form-row">

                                            <div class="form-group col-md-6">

                                                <label for="admin_cpf">
                                                    CPF
                                                    <span class="required">*</span>
                                                </label>

                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="admin_cpf"
                                                    name="admin_cpf"
                                                    maxlength="14"
                                                    placeholder="000.000.000-00"
                                                    inputmode="numeric"
                                                    required
                                                >

                                                <div class="invalid-feedback">
                                                    Informe um CPF válido.
                                                </div>

                                            </div>

                                            <div class="form-group col-md-6">

                                                <label for="admin_telefone">
                                                    Telefone / WhatsApp
                                                </label>

                                                <input
                                                    type="tel"
                                                    class="form-control"
                                                    id="admin_telefone"
                                                    name="admin_telefone"
                                                    maxlength="15"
                                                    placeholder="(11) 99999-9999"
                                                    inputmode="tel"
                                                >

                                            </div>

                                        </div>

                                        <div class="form-group">

                                            <label for="admin_email">
                                                E-mail
                                                <span class="required">*</span>
                                            </label>

                                            <input
                                                type="email"
                                                class="form-control"
                                                id="admin_email"
                                                name="admin_email"
                                                maxlength="190"
                                                required
                                                autocomplete="email"
                                            >

                                            <div class="invalid-feedback">
                                                Informe um e-mail válido.
                                            </div>

                                        </div>

                                        <div
                                            class="alert alert-info mb-0"
                                            role="note"
                                        >
                                            <strong>Ativação do acesso</strong>

                                            <p class="mb-0 mt-1">
                                                O administrador receberá um e-mail
                                                com um link para criar sua senha e
                                                ativar o acesso ao Salão Agenda.
                                            </p>
                                        </div>

                                    </section>

                                    <div class="form-actions">

                                        <p class="required-note mb-3 mb-md-0">
                                            <span class="required">*</span>
                                            Campos obrigatórios
                                        </p>

                                        <button
                                            type="submit"
                                            class="btn btn-primary btn-lg px-5"
                                        >
                                            Criar empresa
                                        </button>

                                    </div>

                                </form>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<script
    src="https://code.jquery.com/jquery-3.5.1.slim.min.js"
></script>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"
></script>
<script src="assets/js/localizacao-brasil.js"></script>
<script src="assets/js/cadastro-empresa.js"></script>

</body>

</html>