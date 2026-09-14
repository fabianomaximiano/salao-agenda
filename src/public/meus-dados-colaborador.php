<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigirColaborador();

$usuarioId = (int) ($_SESSION['user_id'] ?? 0);
$empresaId = (int) ($_SESSION['empresa_id'] ?? 0);
$colaboradorId = (int) ($_SESSION['colaborador_id'] ?? 0);
$pessoaId = (int) ($_SESSION['pessoa_id'] ?? 0);

if ($usuarioId <= 0 || $empresaId <= 0 || $colaboradorId <= 0 || $pessoaId <= 0) {
    header('Location: logout.php');
    exit;
}

$pdo = getDB();

if (empty($_SESSION['csrf_meus_dados_colaborador'])) {
    $_SESSION['csrf_meus_dados_colaborador'] = bin2hex(random_bytes(32));
}

$csrfToken = (string) $_SESSION['csrf_meus_dados_colaborador'];

$stmt = $pdo->prepare(
    'SELECT
        c.id AS colaborador_id,
        c.cargo,
        c.ativo AS colaborador_ativo,
        p.id AS pessoa_id,
        p.nome_completo,
        p.cpf,
        p.data_nascimento,
        p.genero,
        p.email,
        p.ativo AS pessoa_ativa,
        u.id AS usuario_id,
        u.email AS email_acesso,
        u.ativo AS usuario_ativo,
        tp.numero AS telefone,
        COALESCE(tp.whatsapp, 0) AS whatsapp,
        ep.cep,
        ep.logradouro,
        ep.numero,
        ep.complemento,
        ep.bairro,
        ep.cidade,
        ep.estado
     FROM colaboradores c
     INNER JOIN pessoas p
             ON p.id = c.pessoa_id
            AND p.empresa_id = c.empresa_id
     INNER JOIN usuarios u
             ON u.id = c.usuario_id
     LEFT JOIN telefones_pessoa tp
            ON tp.id = (
                SELECT tp2.id
                FROM telefones_pessoa tp2
                WHERE tp2.pessoa_id = p.id
                ORDER BY tp2.principal DESC, tp2.id ASC
                LIMIT 1
            )
     LEFT JOIN enderecos_pessoa ep
            ON ep.pessoa_id = p.id
     WHERE c.id = :colaborador_id
       AND c.empresa_id = :empresa_id
       AND c.pessoa_id = :pessoa_id
       AND c.usuario_id = :usuario_id
       AND c.ativo = 1
       AND p.ativo = 1
       AND u.ativo = 1
     LIMIT 1'
);

$stmt->execute([
    ':colaborador_id' => $colaboradorId,
    ':empresa_id' => $empresaId,
    ':pessoa_id' => $pessoaId,
    ':usuario_id' => $usuarioId,
]);

$dados = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dados) {
    header('Location: logout.php');
    exit;
}

$flash = $_SESSION['flash_meus_dados_colaborador'] ?? null;
unset($_SESSION['flash_meus_dados_colaborador']);

$erros = is_array($flash['erros'] ?? null) ? $flash['erros'] : [];

if (is_array($flash['old'] ?? null)) {
    $dados = array_merge($dados, $flash['old']);
}

function hColaboradorDados(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$pageTitle = 'Meus dados';
$pageCss = 'meus-dados-colaborador.css?v=20260914-1';
$pageJs = 'meus-dados-colaborador.js?v=20260914-1';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/partials/navbar.php';
?>

<main class="app-content colaborador-dados-page">
    <div class="app-page-header colaborador-dados-header">
        <h1>Meus dados</h1>
        <p>Mantenha seus dados pessoais, contato e endereço atualizados.</p>
    </div>

    <?php if (is_array($flash) && !empty($flash['mensagem'])): ?>
        <div class="alert alert-<?= ($flash['tipo'] ?? '') === 'success' ? 'success' : 'danger' ?>">
            <?= hColaboradorDados($flash['mensagem']) ?>
        </div>
    <?php endif; ?>

    <form
        action="api/meus-dados-colaborador.php"
        method="post"
        class="colaborador-dados-form"
        novalidate
    >
        <input type="hidden" name="csrf_token" value="<?= hColaboradorDados($csrfToken) ?>">

        <section class="app-card colaborador-dados-section mb-4">
            <div class="app-card-header colaborador-dados-section-header">
                <div>
                    <h2>Dados pessoais</h2>
                    <p>Informações pessoais vinculadas ao seu cadastro.</p>
                </div>
            </div>

            <div class="app-card-body colaborador-dados-section-body">
                <div class="form-group">
                    <label for="nome_completo">Nome completo <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        class="form-control<?= isset($erros['nome_completo']) ? ' is-invalid' : '' ?>"
                        id="nome_completo"
                        name="nome_completo"
                        maxlength="160"
                        required
                        value="<?= hColaboradorDados($dados['nome_completo'] ?? '') ?>"
                    >
                    <div class="invalid-feedback">
                        <?= hColaboradorDados($erros['nome_completo'] ?? 'Informe o nome completo.') ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="cpf">CPF</label>
                        <input
                            type="text"
                            class="form-control"
                            id="cpf"
                            value="<?= hColaboradorDados($dados['cpf'] ?? '') ?>"
                            readonly
                        >
                        <small class="form-text text-muted">
                            CPF protegido. Alterações devem ser tratadas pela administração.
                        </small>
                    </div>

                    <div class="form-group col-md-6">
                        <label for="data_nascimento">Data de nascimento</label>
                        <input
                            type="date"
                            class="form-control<?= isset($erros['data_nascimento']) ? ' is-invalid' : '' ?>"
                            id="data_nascimento"
                            name="data_nascimento"
                            value="<?= hColaboradorDados($dados['data_nascimento'] ?? '') ?>"
                        >
                        <div class="invalid-feedback">
                            <?= hColaboradorDados($erros['data_nascimento'] ?? '') ?>
                        </div>
                    </div>
                </div>

                <?php $generoAtual = (string) ($dados['genero'] ?? 'nao_informado'); ?>
                <div class="form-group mb-0">
                    <label for="genero">Gênero</label>
                    <select class="custom-select" id="genero" name="genero">
                        <option value="nao_informado" <?= $generoAtual === 'nao_informado' ? 'selected' : '' ?>>
                            Prefiro não informar
                        </option>
                        <option value="feminino" <?= $generoAtual === 'feminino' ? 'selected' : '' ?>>
                            Feminino
                        </option>
                        <option value="masculino" <?= $generoAtual === 'masculino' ? 'selected' : '' ?>>
                            Masculino
                        </option>
                        <option value="nao_binario" <?= $generoAtual === 'nao_binario' ? 'selected' : '' ?>>
                            Não binário
                        </option>
                    </select>
                </div>
            </div>
        </section>

        <section class="app-card colaborador-dados-section mb-4">
            <div class="app-card-header colaborador-dados-section-header">
                <div>
                    <h2>Contato</h2>
                    <p>Seu e-mail de acesso permanece protegido.</p>
                </div>
            </div>

            <div class="app-card-body colaborador-dados-section-body">
                <div class="form-group">
                    <label for="email_acesso">E-mail de acesso</label>
                    <input
                        type="email"
                        class="form-control"
                        id="email_acesso"
                        value="<?= hColaboradorDados($dados['email_acesso'] ?? '') ?>"
                        readonly
                    >
                    <small class="form-text text-muted">
                        Este e-mail é utilizado para entrar no sistema e não pode ser alterado aqui.
                    </small>
                </div>

                <div class="form-row align-items-end">
                    <div class="form-group col-md-8">
                        <label for="telefone">Telefone</label>
                        <input
                            type="tel"
                            class="form-control"
                            id="telefone"
                            name="telefone"
                            maxlength="15"
                            inputmode="tel"
                            autocomplete="tel"
                            placeholder="(11) 99999-9999"
                            value="<?= hColaboradorDados($dados['telefone'] ?? '') ?>"
                        >
                    </div>

                    <div class="form-group col-md-4">
                        <div class="custom-control custom-checkbox colaborador-dados-check">
                            <input
                                type="checkbox"
                                class="custom-control-input"
                                id="whatsapp"
                                name="whatsapp"
                                value="1"
                                <?= (int) ($dados['whatsapp'] ?? 0) === 1 ? 'checked' : '' ?>
                            >
                            <label class="custom-control-label" for="whatsapp">É WhatsApp</label>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="app-card colaborador-dados-section mb-4">
            <div class="app-card-header colaborador-dados-section-header">
                <div>
                    <h2>Endereço</h2>
                    <p>Opcional. Informe o CEP para preencher o endereço automaticamente.</p>
                </div>
            </div>

            <div class="app-card-body colaborador-dados-section-body">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="cep">CEP</label>
                        <input
                            type="text"
                            class="form-control<?= isset($erros['cep']) ? ' is-invalid' : '' ?>"
                            id="cep"
                            name="cep"
                            maxlength="9"
                            inputmode="numeric"
                            autocomplete="postal-code"
                            placeholder="00000-000"
                            value="<?= hColaboradorDados($dados['cep'] ?? '') ?>"
                        >
                        <div class="invalid-feedback">
                            <?= hColaboradorDados($erros['cep'] ?? '') ?>
                        </div>
                    </div>

                    <div class="form-group col-md-8">
                        <label for="logradouro">Logradouro</label>
                        <input
                            type="text"
                            class="form-control"
                            id="logradouro"
                            name="logradouro"
                            maxlength="180"
                            value="<?= hColaboradorDados($dados['logradouro'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="numero">Número</label>
                        <input
                            type="text"
                            class="form-control"
                            id="numero"
                            name="numero"
                            maxlength="30"
                            value="<?= hColaboradorDados($dados['numero'] ?? '') ?>"
                        >
                    </div>

                    <div class="form-group col-md-8">
                        <label for="complemento">Complemento</label>
                        <input
                            type="text"
                            class="form-control"
                            id="complemento"
                            name="complemento"
                            maxlength="120"
                            value="<?= hColaboradorDados($dados['complemento'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-5">
                        <label for="bairro">Bairro</label>
                        <input
                            type="text"
                            class="form-control"
                            id="bairro"
                            name="bairro"
                            maxlength="120"
                            value="<?= hColaboradorDados($dados['bairro'] ?? '') ?>"
                        >
                    </div>

                    <div class="form-group col-md-5">
                        <label for="cidade">Cidade</label>
                        <input
                            type="text"
                            class="form-control"
                            id="cidade"
                            name="cidade"
                            maxlength="120"
                            value="<?= hColaboradorDados($dados['cidade'] ?? '') ?>"
                        >
                    </div>

                    <div class="form-group col-md-2">
                        <label for="estado">UF</label>
                        <input
                            type="text"
                            class="form-control text-uppercase<?= isset($erros['estado']) ? ' is-invalid' : '' ?>"
                            id="estado"
                            name="estado"
                            maxlength="2"
                            value="<?= hColaboradorDados($dados['estado'] ?? '') ?>"
                        >
                        <div class="invalid-feedback">
                            <?= hColaboradorDados($erros['estado'] ?? '') ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="app-card colaborador-dados-section colaborador-dados-readonly mb-4">
            <div class="app-card-header colaborador-dados-section-header">
                <div>
                    <h2>Dados administrados pela empresa</h2>
                    <p>Estas informações são apenas para consulta.</p>
                </div>
            </div>

            <div class="app-card-body colaborador-dados-section-body">
                <div class="form-group mb-0">
                    <label>Cargo / função</label>
                    <div class="colaborador-dados-readonly-value">
                        <?= hColaboradorDados($dados['cargo'] ?: 'Não informado') ?>
                    </div>
                    <small class="form-text text-muted">
                        Cargo, permissões, status e acesso são definidos pela administração da empresa.
                    </small>
                </div>
            </div>
        </section>

        <div class="colaborador-dados-actions">
            <a href="dashboard.php" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Atualizar meus dados</button>
        </div>
    </form>
</main>

<script src="assets/js/consulta-cep.js"></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
