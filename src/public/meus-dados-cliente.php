<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigirCliente();

$pdo = getDB();

$usuarioId = (int) ($_SESSION['user_id'] ?? 0);
$empresaId = (int) ($_SESSION['empresa_id'] ?? 0);
$clienteId = (int) ($_SESSION['cliente_id'] ?? 0);
$pessoaId = (int) ($_SESSION['pessoa_id'] ?? 0);
$empresa = trim((string) ($_SESSION['empresa_nome'] ?? ''));

if (
    $usuarioId <= 0 ||
    $empresaId <= 0 ||
    $clienteId <= 0 ||
    $pessoaId <= 0
) {
    http_response_code(403);
    exit('Sessão de cliente inválida.');
}

if (empty($_SESSION['csrf_meus_dados_cliente'])) {
    $_SESSION['csrf_meus_dados_cliente'] = bin2hex(random_bytes(32));
}

$csrf = (string) $_SESSION['csrf_meus_dados_cliente'];

$stmt = $pdo->prepare(
    'SELECT
        c.id AS cliente_id,
        c.pessoa_id,
        c.usuario_id,
        c.ativo AS cliente_ativo,
        p.nome_completo,
        p.cpf,
        p.data_nascimento,
        p.genero,
        p.email,
        p.ativo AS pessoa_ativa,
        u.email AS email_acesso,
        u.ativo AS usuario_ativo,
        tp.numero AS telefone,
        tp.whatsapp,
        e.cep,
        e.logradouro,
        e.numero,
        e.complemento,
        e.bairro,
        e.cidade,
        e.estado
     FROM clientes c
     INNER JOIN pessoas p
       ON p.id = c.pessoa_id
      AND p.empresa_id = c.empresa_id
     INNER JOIN usuarios u
       ON u.id = c.usuario_id
     LEFT JOIN telefones_pessoa tp
       ON tp.id = (
           SELECT t.id
           FROM telefones_pessoa t
           WHERE t.pessoa_id = p.id
           ORDER BY t.principal DESC, t.id ASC
           LIMIT 1
       )
     LEFT JOIN enderecos_pessoa e
       ON e.pessoa_id = p.id
     WHERE c.id = :cliente_id
       AND c.empresa_id = :empresa_id
       AND c.pessoa_id = :pessoa_id
       AND c.usuario_id = :usuario_id
     LIMIT 1'
);

$stmt->execute([
    ':cliente_id' => $clienteId,
    ':empresa_id' => $empresaId,
    ':pessoa_id' => $pessoaId,
    ':usuario_id' => $usuarioId,
]);

$dados = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    !$dados ||
    (int) $dados['cliente_ativo'] !== 1 ||
    (int) $dados['pessoa_ativa'] !== 1 ||
    (int) $dados['usuario_ativo'] !== 1
) {
    http_response_code(403);
    exit('Cadastro de cliente indisponível.');
}

$flash = $_SESSION['flash_meus_dados_cliente'] ?? null;
unset($_SESSION['flash_meus_dados_cliente']);

$erros = is_array($flash['erros'] ?? null) ? $flash['erros'] : [];

if (is_array($flash['old'] ?? null)) {
    $dados = array_merge($dados, $flash['old']);
}

function hMeusDadosCliente(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$nome = trim((string) ($dados['nome_completo'] ?? 'Cliente'));
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <title>Meus dados<?= $empresa !== '' ? ' | ' . hMeusDadosCliente($empresa) : '' ?></title>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
    >
    <link rel="stylesheet" href="assets/css/cliente-area.css?v=20260914-2">
    <link rel="stylesheet" href="assets/css/meus-dados-cliente.css?v=20260914-1">
</head>
<body>
    <div class="cliente-area-page">
        <header class="cliente-area-topbar">
            <div class="container cliente-area-container">
                <div class="cliente-area-topbar-inner">
                    <a class="cliente-area-brand" href="dashboard-cliente.php">
                        <?= hMeusDadosCliente($empresa !== '' ? $empresa : 'Minha área') ?>
                    </a>

                    <div class="cliente-area-user">
                        <div class="cliente-area-user-text">
                            <strong><?= hMeusDadosCliente($nome) ?></strong>
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

        <main class="container cliente-area-container cliente-area-main meus-dados-cliente-page">
            <div class="meus-dados-cliente-heading">
                <div>
                    <span class="cliente-area-eyebrow">Minha conta</span>
                    <h1>Meus dados</h1>
                    <p>Mantenha seus dados pessoais, telefone e endereço atualizados.</p>
                </div>

                <a class="btn btn-outline-secondary" href="dashboard-cliente.php">
                    Voltar para minha área
                </a>
            </div>

            <?php if (is_array($flash)): ?>
                <div
                    class="alert alert-<?= ($flash['tipo'] ?? '') === 'success' ? 'success' : 'danger' ?>"
                    role="alert"
                >
                    <?= hMeusDadosCliente($flash['mensagem'] ?? '') ?>
                </div>
            <?php endif; ?>

            <form
                id="meusDadosClienteForm"
                class="meus-dados-cliente-form"
                action="api/meus-dados-cliente.php"
                method="post"
                novalidate
            >
                <input type="hidden" name="csrf_token" value="<?= hMeusDadosCliente($csrf) ?>">

                <section class="meus-dados-cliente-section">
                    <div class="meus-dados-cliente-section-header">
                        <div>
                            <h2>Dados pessoais</h2>
                            <p>Informações básicas do seu cadastro.</p>
                        </div>
                    </div>

                    <div class="meus-dados-cliente-section-body">
                        <div class="form-row">
                            <div class="form-group col-md-7">
                                <label for="nome_completo">Nome completo</label>
                                <input
                                    id="nome_completo"
                                    class="form-control<?= isset($erros['nome_completo']) ? ' is-invalid' : '' ?>"
                                    name="nome_completo"
                                    required
                                    maxlength="160"
                                    autocomplete="name"
                                    value="<?= hMeusDadosCliente($dados['nome_completo'] ?? '') ?>"
                                >
                                <?php if (isset($erros['nome_completo'])): ?>
                                    <div class="invalid-feedback">
                                        <?= hMeusDadosCliente($erros['nome_completo']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group col-md-5">
                                <label for="cpf">CPF</label>
                                <input
                                    id="cpf"
                                    class="form-control"
                                    value="<?= hMeusDadosCliente($dados['cpf'] ?? '') ?>"
                                    readonly
                                    aria-describedby="cpfHelp"
                                >
                                <small id="cpfHelp" class="form-text text-muted">
                                    CPF protegido após o cadastro.
                                </small>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="data_nascimento">Data de nascimento</label>
                                <input
                                    type="date"
                                    id="data_nascimento"
                                    class="form-control<?= isset($erros['data_nascimento']) ? ' is-invalid' : '' ?>"
                                    name="data_nascimento"
                                    required
                                    value="<?= hMeusDadosCliente($dados['data_nascimento'] ?? '') ?>"
                                >
                                <?php if (isset($erros['data_nascimento'])): ?>
                                    <div class="invalid-feedback">
                                        <?= hMeusDadosCliente($erros['data_nascimento']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="genero">Gênero</label>
                                <select
                                    id="genero"
                                    class="form-control<?= isset($erros['genero']) ? ' is-invalid' : '' ?>"
                                    name="genero"
                                    required
                                >
                                    <?php foreach ([
                                        'nao_informado' => 'Prefiro não informar',
                                        'feminino' => 'Feminino',
                                        'masculino' => 'Masculino',
                                        'nao_binario' => 'Não binário',
                                    ] as $valor => $rotulo): ?>
                                        <option
                                            value="<?= hMeusDadosCliente($valor) ?>"
                                            <?= ($dados['genero'] ?? '') === $valor ? 'selected' : '' ?>
                                        >
                                            <?= hMeusDadosCliente($rotulo) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($erros['genero'])): ?>
                                    <div class="invalid-feedback">
                                        <?= hMeusDadosCliente($erros['genero']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="meus-dados-cliente-section">
                    <div class="meus-dados-cliente-section-header">
                        <div>
                            <h2>Contato</h2>
                            <p>Seu e-mail de acesso fica protegido. Você pode atualizar seu telefone.</p>
                        </div>
                    </div>

                    <div class="meus-dados-cliente-section-body">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="email">E-mail de acesso</label>
                                <input
                                    type="email"
                                    id="email"
                                    class="form-control"
                                    value="<?= hMeusDadosCliente($dados['email_acesso'] ?? '') ?>"
                                    readonly
                                    aria-describedby="emailHelp"
                                >
                                <small id="emailHelp" class="form-text text-muted">
                                    Este e-mail está vinculado à sua conta de acesso.
                                </small>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="telefone">Telefone / celular</label>
                                <input
                                    id="telefone"
                                    class="form-control<?= isset($erros['telefone']) ? ' is-invalid' : '' ?>"
                                    name="telefone"
                                    maxlength="15"
                                    inputmode="tel"
                                    autocomplete="tel"
                                    required
                                    value="<?= hMeusDadosCliente($dados['telefone'] ?? '') ?>"
                                >
                                <?php if (isset($erros['telefone'])): ?>
                                    <div class="invalid-feedback">
                                        <?= hMeusDadosCliente($erros['telefone']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="meus-dados-cliente-check">
                            <div class="custom-control custom-checkbox">
                                <input
                                    class="custom-control-input"
                                    type="checkbox"
                                    id="whatsapp"
                                    name="whatsapp"
                                    value="1"
                                    <?= !empty($dados['whatsapp']) ? 'checked' : '' ?>
                                >
                                <label class="custom-control-label" for="whatsapp">
                                    Este número possui WhatsApp
                                </label>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="meus-dados-cliente-section">
                    <div class="meus-dados-cliente-section-header">
                        <div>
                            <h2>Endereço</h2>
                            <p>Seu endereço é opcional e pode ser atualizado quando necessário.</p>
                        </div>
                    </div>

                    <div class="meus-dados-cliente-section-body">
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label for="cep">CEP</label>
                                <input
                                    id="cep"
                                    class="form-control<?= isset($erros['cep']) ? ' is-invalid' : '' ?>"
                                    name="cep"
                                    maxlength="9"
                                    inputmode="numeric"
                                    autocomplete="postal-code"
                                    value="<?= hMeusDadosCliente($dados['cep'] ?? '') ?>"
                                >
                                <small id="cepFeedback" class="form-text" aria-live="polite"></small>
                                <?php if (isset($erros['cep'])): ?>
                                    <div class="invalid-feedback">
                                        <?= hMeusDadosCliente($erros['cep']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group col-md-7">
                                <label for="logradouro">Logradouro</label>
                                <input
                                    id="logradouro"
                                    class="form-control"
                                    name="logradouro"
                                    maxlength="180"
                                    autocomplete="address-line1"
                                    value="<?= hMeusDadosCliente($dados['logradouro'] ?? '') ?>"
                                >
                            </div>

                            <div class="form-group col-md-2">
                                <label for="numero">Número</label>
                                <input
                                    id="numero"
                                    class="form-control"
                                    name="numero"
                                    maxlength="30"
                                    value="<?= hMeusDadosCliente($dados['numero'] ?? '') ?>"
                                >
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="complemento">Complemento</label>
                                <input
                                    id="complemento"
                                    class="form-control"
                                    name="complemento"
                                    maxlength="120"
                                    autocomplete="address-line2"
                                    value="<?= hMeusDadosCliente($dados['complemento'] ?? '') ?>"
                                >
                            </div>

                            <div class="form-group col-md-4">
                                <label for="bairro">Bairro</label>
                                <input
                                    id="bairro"
                                    class="form-control"
                                    name="bairro"
                                    maxlength="120"
                                    value="<?= hMeusDadosCliente($dados['bairro'] ?? '') ?>"
                                >
                            </div>

                            <div class="form-group col-md-3">
                                <label for="cidade">Cidade</label>
                                <input
                                    id="cidade"
                                    class="form-control"
                                    name="cidade"
                                    maxlength="120"
                                    autocomplete="address-level2"
                                    readonly
                                    value="<?= hMeusDadosCliente($dados['cidade'] ?? '') ?>"
                                >
                            </div>

                            <div class="form-group col-md-1">
                                <label for="estado">UF</label>
                                <input
                                    id="estado"
                                    class="form-control"
                                    name="estado"
                                    maxlength="2"
                                    autocomplete="address-level1"
                                    readonly
                                    value="<?= hMeusDadosCliente($dados['estado'] ?? '') ?>"
                                >
                            </div>
                        </div>
                    </div>
                </section>

                <div class="meus-dados-cliente-actions">
                    <button class="btn btn-primary" type="submit">
                        Atualizar meus dados
                    </button>

                    <a class="btn btn-outline-secondary" href="dashboard-cliente.php">
                        Cancelar
                    </a>
                </div>
            </form>
        </main>

        <footer class="cliente-area-footer">
            <div class="container cliente-area-container">
                <span><?= hMeusDadosCliente($empresa !== '' ? $empresa : 'Área do cliente') ?></span>
                <span>Área do cliente</span>
            </div>
        </footer>
    </div>

    <script src="assets/js/consulta-cep.js"></script>
    <script src="assets/js/meus-dados-cliente.js"></script>
</body>
</html>
