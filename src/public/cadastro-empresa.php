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
                                    Cadastre sua empresa e crie o primeiro
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
                                        o estabelecimento e o administrador principal.
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
                                                </label>

                                                <select
                                                    class="custom-select"
                                                    id="tipo_documento"
                                                    name="tipo_documento"
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

                                                    <option value="outro">
                                                        Outro
                                                    </option>

                                                </select>

                                            </div>


                                            <div class="form-group col-md-8">

                                                <label for="documento">
                                                    Documento
                                                </label>

                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="documento"
                                                    name="documento"
                                                    maxlength="20"
                                                    placeholder="CPF ou CNPJ"
                                                >

                                                <div class="invalid-feedback">
                                                    Informe um documento válido.
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
                                                    maxlength="30"
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
                                                    maxlength="30"
                                                >

                                            </div>

                                        </div>

                                    </section>


                                    <section class="form-section">

                                        <div class="section-heading">
                                            <span class="section-number">2</span>

                                            <div>
                                                <h3>Endereço</h3>

                                                <p>
                                                    Localização principal da empresa.
                                                </p>
                                            </div>
                                        </div>


                                        <div class="form-row">

                                            <div class="form-group col-md-4">

                                                <label for="cep">
                                                    CEP
                                                </label>

                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="cep"
                                                    name="cep"
                                                    maxlength="10"
                                                    placeholder="00000-000"
                                                >

                                            </div>


                                            <div class="form-group col-md-8">

                                                <label for="logradouro">
                                                    Logradouro
                                                </label>

                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="logradouro"
                                                    name="logradouro"
                                                    maxlength="180"
                                                >

                                            </div>

                                        </div>


                                        <div class="form-row">

                                            <div class="form-group col-md-3">

                                                <label for="numero">
                                                    Número
                                                </label>

                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="numero"
                                                    name="numero"
                                                    maxlength="30"
                                                >

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
                                                >

                                            </div>


                                            <div class="form-group col-md-4">

                                                <label for="bairro">
                                                    Bairro
                                                </label>

                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="bairro"
                                                    name="bairro"
                                                    maxlength="120"
                                                >

                                            </div>

                                        </div>


                                        <div class="form-row">

                                            <div class="form-group col-md-8">

                                                <label for="cidade">
                                                    Cidade
                                                </label>

                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="cidade"
                                                    name="cidade"
                                                    maxlength="120"
                                                >

                                            </div>


                                            <div class="form-group col-md-4">

                                                <label for="estado">
                                                    Estado
                                                </label>

                                                <select
                                                    class="custom-select"
                                                    id="estado"
                                                    name="estado"
                                                >
                                                    <option value="">
                                                        UF
                                                    </option>

                                                    <option value="AC">AC</option>
                                                    <option value="AL">AL</option>
                                                    <option value="AP">AP</option>
                                                    <option value="AM">AM</option>
                                                    <option value="BA">BA</option>
                                                    <option value="CE">CE</option>
                                                    <option value="DF">DF</option>
                                                    <option value="ES">ES</option>
                                                    <option value="GO">GO</option>
                                                    <option value="MA">MA</option>
                                                    <option value="MT">MT</option>
                                                    <option value="MS">MS</option>
                                                    <option value="MG">MG</option>
                                                    <option value="PA">PA</option>
                                                    <option value="PB">PB</option>
                                                    <option value="PR">PR</option>
                                                    <option value="PE">PE</option>
                                                    <option value="PI">PI</option>
                                                    <option value="RJ">RJ</option>
                                                    <option value="RN">RN</option>
                                                    <option value="RS">RS</option>
                                                    <option value="RO">RO</option>
                                                    <option value="RR">RR</option>
                                                    <option value="SC">SC</option>
                                                    <option value="SP">SP</option>
                                                    <option value="SE">SE</option>
                                                    <option value="TO">TO</option>
                                                </select>

                                            </div>

                                        </div>

                                    </section>


                                    <section class="form-section">

                                        <div class="section-heading">
                                            <span class="section-number">3</span>

                                            <div>
                                                <h3>Administrador principal</h3>

                                                <p>
                                                    Esta conta terá acesso à administração da empresa.
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
                                                </label>

                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="admin_cpf"
                                                    name="admin_cpf"
                                                    maxlength="14"
                                                    placeholder="000.000.000-00"
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
                                                    maxlength="30"
                                                    placeholder="(11) 99999-9999"
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


                                        <div class="form-row">

                                            <div class="form-group col-md-6">

                                                <label for="admin_senha">
                                                    Senha
                                                    <span class="required">*</span>
                                                </label>

                                                <input
                                                    type="password"
                                                    class="form-control"
                                                    id="admin_senha"
                                                    name="admin_senha"
                                                    minlength="8"
                                                    required
                                                    autocomplete="new-password"
                                                >

                                                <small class="form-text text-muted">
                                                    Mínimo de 8 caracteres.
                                                </small>

                                                <div class="invalid-feedback">
                                                    A senha deve ter pelo menos 8 caracteres.
                                                </div>

                                            </div>


                                            <div class="form-group col-md-6">

                                                <label for="admin_senha_confirmacao">
                                                    Confirmar senha
                                                    <span class="required">*</span>
                                                </label>

                                                <input
                                                    type="password"
                                                    class="form-control"
                                                    id="admin_senha_confirmacao"
                                                    name="admin_senha_confirmacao"
                                                    minlength="8"
                                                    required
                                                    autocomplete="new-password"
                                                >

                                                <div class="invalid-feedback">
                                                    As senhas precisam ser iguais.
                                                </div>

                                            </div>

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

<script src="assets/js/cadastro-empresa.js"></script>

</body>

</html>