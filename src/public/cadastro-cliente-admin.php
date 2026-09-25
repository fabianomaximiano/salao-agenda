<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigirAcesso('clientes');

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();

if (empty($_SESSION['csrf_cadastro_cliente_admin'])) {
    $_SESSION['csrf_cadastro_cliente_admin'] = bin2hex(random_bytes(32));
}

$csrf = (string) $_SESSION['csrf_cadastro_cliente_admin'];
$clienteId = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
$edicao = (bool) $clienteId;

$d = [
    'nome_completo' => '',
    'cpf' => '',
    'data_nascimento' => '',
    'genero' => 'nao_informado',
    'email' => '',
    'telefone' => '',
    'whatsapp' => 1,
    'cep' => '',
    'logradouro' => '',
    'numero' => '',
    'complemento' => '',
    'bairro' => '',
    'cidade' => '',
    'estado' => '',
    'observacoes' => '',
    'ativo' => 1,
    'usuario_id' => null,
];

if ($edicao) {
    $stmt = $pdo->prepare(
        'SELECT
            c.id,
            c.usuario_id,
            c.observacoes,
            c.ativo,
            p.nome_completo,
            p.cpf,
            p.data_nascimento,
            p.genero,
            p.email,
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
         LEFT JOIN telefones_pessoa tp
           ON tp.id = (
               SELECT t.id
               FROM telefones_pessoa t
               WHERE t.pessoa_id = p.id
               ORDER BY t.principal DESC, t.id
               LIMIT 1
           )
         LEFT JOIN enderecos_pessoa e
           ON e.pessoa_id = p.id
         WHERE c.id = :id
           AND c.empresa_id = :empresa
         LIMIT 1'
    );

    $stmt->execute([
        ':id' => $clienteId,
        ':empresa' => $empresaId,
    ]);

    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registro) {
        http_response_code(404);
        exit('Cliente não encontrado.');
    }

    $d = array_merge($d, $registro);
}

$flash = $_SESSION['flash_cliente_admin'] ?? null;
unset($_SESSION['flash_cliente_admin']);

$erros = is_array($flash['erros'] ?? null) ? $flash['erros'] : [];

if (is_array($flash['old'] ?? null)) {
    $d = array_merge($d, $flash['old']);
}

function hca(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$pageTitle = $edicao ? 'Editar cliente' : 'Cadastrar cliente';
$pageCss = 'cadastro-cliente-admin.css?v=20260914-2';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/partials/navbar.php';
?>

<main class="app-content cliente-admin-page">
    <div class="app-page-header cliente-admin-header">
        <div>
            <h1><?= $edicao ? 'Editar cliente' : 'Cadastrar cliente' ?></h1>
            <p>Dados pessoais, contato e endereço.</p>
        </div>
    </div>

    <?php if (is_array($flash)): ?>
        <div class="alert alert-<?= ($flash['tipo'] ?? '') === 'success' ? 'success' : 'danger' ?>">
            <?= hca($flash['mensagem'] ?? '') ?>
        </div>
    <?php endif; ?>

    <form
        id="cadastroClienteAdminForm"
        class="cliente-admin-form"
        action="api/clientes.php"
        method="post"
        novalidate
    >
        <input type="hidden" name="csrf_token" value="<?= hca($csrf) ?>">
        <input type="hidden" name="acao" value="<?= $edicao ? 'atualizar' : 'criar' ?>">

        <?php if ($edicao): ?>
            <input type="hidden" name="cliente_id" value="<?= (int) $clienteId ?>">
        <?php endif; ?>

        <section class="cliente-admin-section">
            <div class="cliente-admin-section-header">
                <div>
                    <h2>Dados pessoais</h2>
                    <p>Informações básicas de identificação do cliente.</p>
                </div>
            </div>

            <div class="cliente-admin-section-body">
                <div class="form-row">
                    <div class="form-group col-md-7">
                        <label for="nome_completo">Nome completo</label>
                        <input
                            id="nome_completo"
                            class="form-control<?= isset($erros['nome_completo']) ? ' is-invalid' : '' ?>"
                            name="nome_completo"
                            required
                            maxlength="160"
                            value="<?= hca($d['nome_completo']) ?>"
                        >
                        <?php if (isset($erros['nome_completo'])): ?>
                            <div class="invalid-feedback"><?= hca($erros['nome_completo']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group col-md-5">
                        <label for="cpf">CPF</label>
                        <input
                            id="cpf"
                            class="form-control<?= isset($erros['cpf']) ? ' is-invalid' : '' ?>"
                            name="cpf"
                            maxlength="14"
                            <?= $edicao ? 'readonly' : '' ?>
                            value="<?= hca($d['cpf']) ?>"
                        >
                        <?php if ($edicao): ?>
                            <small class="form-text text-muted">CPF protegido após o cadastro.</small>
                        <?php endif; ?>
                        <?php if (isset($erros['cpf'])): ?>
                            <div class="invalid-feedback"><?= hca($erros['cpf']) ?></div>
                        <?php endif; ?>
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
                            value="<?= hca($d['data_nascimento']) ?>"
                        >
                        <?php if (isset($erros['data_nascimento'])): ?>
                            <div class="invalid-feedback"><?= hca($erros['data_nascimento']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group col-md-6">
                        <label for="genero">Gênero</label>
                        <select
                            id="genero"
                            class="form-control<?= isset($erros['genero']) ? ' is-invalid' : '' ?>"
                            name="genero"
                        >
                            <?php foreach ([
                                'nao_informado' => 'Prefiro não informar',
                                'feminino' => 'Feminino',
                                'masculino' => 'Masculino',
                                'nao_binario' => 'Não binário',
                            ] as $valor => $rotulo): ?>
                                <option value="<?= hca($valor) ?>" <?= $d['genero'] === $valor ? 'selected' : '' ?>>
                                    <?= hca($rotulo) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($erros['genero'])): ?>
                            <div class="invalid-feedback"><?= hca($erros['genero']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="cliente-admin-section">
            <div class="cliente-admin-section-header">
                <div>
                    <h2>Contato</h2>
                    <p>E-mail e telefone principal do cliente.</p>
                </div>
            </div>

            <div class="cliente-admin-section-body">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="email">E-mail</label>
                        <input
                            type="email"
                            id="email"
                            class="form-control<?= isset($erros['email']) ? ' is-invalid' : '' ?>"
                            name="email"
                            maxlength="190"
                            <?= ($edicao && !empty($d['usuario_id'])) ? 'readonly' : '' ?>
                            value="<?= hca($d['email']) ?>"
                        >
                        <?php if ($edicao && !empty($d['usuario_id'])): ?>
                            <small class="form-text text-muted">E-mail vinculado à conta de acesso.</small>
                        <?php endif; ?>
                        <?php if (isset($erros['email'])): ?>
                            <div class="invalid-feedback"><?= hca($erros['email']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group col-md-6">
                        <label for="telefone">Telefone / celular</label>
                        <input
                            id="telefone"
                            class="form-control<?= isset($erros['telefone']) ? ' is-invalid' : '' ?>"
                            name="telefone"
                            maxlength="15"
                            value="<?= hca($d['telefone']) ?>"
                        >
                        <?php if (isset($erros['telefone'])): ?>
                            <div class="invalid-feedback"><?= hca($erros['telefone']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="cliente-admin-check">
                    <div class="custom-control custom-checkbox">
                        <input
                            class="custom-control-input"
                            type="checkbox"
                            id="whatsapp"
                            name="whatsapp"
                            value="1"
                            <?= !empty($d['whatsapp']) ? 'checked' : '' ?>
                        >
                        <label class="custom-control-label" for="whatsapp">
                            Este número possui WhatsApp
                        </label>
                    </div>
                </div>
            </div>
        </section>

        <section class="cliente-admin-section">
            <div class="cliente-admin-section-header">
                <div>
                    <h2>Endereço</h2>
                    <p>Endereço principal utilizado no cadastro do cliente.</p>
                </div>
            </div>

            <div class="cliente-admin-section-body">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="cep">CEP</label>
                        <input
                            id="cep"
                            class="form-control<?= isset($erros['cep']) ? ' is-invalid' : '' ?>"
                            name="cep"
                            maxlength="9"
                            value="<?= hca($d['cep']) ?>"
                        >
                        <small id="cepFeedback" class="form-text"></small>
                        <?php if (isset($erros['cep'])): ?>
                            <div class="invalid-feedback"><?= hca($erros['cep']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group col-md-7">
                        <label for="logradouro">Logradouro</label>
                        <input
                            id="logradouro"
                            class="form-control"
                            name="logradouro"
                            value="<?= hca($d['logradouro']) ?>"
                        >
                    </div>

                    <div class="form-group col-md-2">
                        <label for="numero">Número</label>
                        <input
                            id="numero"
                            class="form-control"
                            name="numero"
                            value="<?= hca($d['numero']) ?>"
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
                            value="<?= hca($d['complemento']) ?>"
                        >
                    </div>

                    <div class="form-group col-md-4">
                        <label for="bairro">Bairro</label>
                        <input
                            id="bairro"
                            class="form-control"
                            name="bairro"
                            value="<?= hca($d['bairro']) ?>"
                        >
                    </div>

                    <div class="form-group col-md-3">
                        <label for="cidade">Cidade</label>
                        <input
                            id="cidade"
                            class="form-control"
                            name="cidade"
                            readonly
                            value="<?= hca($d['cidade']) ?>"
                        >
                    </div>

                    <div class="form-group col-md-1">
                        <label for="estado">UF</label>
                        <input
                            id="estado"
                            class="form-control"
                            name="estado"
                            readonly
                            value="<?= hca($d['estado']) ?>"
                        >
                    </div>
                </div>
            </div>
        </section>

        <section class="cliente-admin-section">
            <div class="cliente-admin-section-header">
                <div>
                    <h2>Administrativo</h2>
                    <p>Observações internas e situação do cadastro.</p>
                </div>
            </div>

            <div class="cliente-admin-section-body">
                <div class="form-group">
                    <label for="observacoes">Observações</label>
                    <textarea
                        id="observacoes"
                        class="form-control"
                        name="observacoes"
                        rows="4"
                    ><?= hca($d['observacoes']) ?></textarea>
                </div>

                <div class="cliente-admin-status">
                    <div class="custom-control custom-switch">
                        <input
                            class="custom-control-input"
                            id="ativo"
                            type="checkbox"
                            name="ativo"
                            value="1"
                            <?= !empty($d['ativo']) ? 'checked' : '' ?>
                        >
                        <label class="custom-control-label" for="ativo">Cliente ativo</label>
                    </div>

                    <?php if ($edicao): ?>
                        <div class="cliente-admin-acesso">
                            <span>Acesso</span>
                            <?php if (!empty($d['usuario_id'])): ?>
                                <strong class="cliente-admin-acesso-badge cliente-admin-acesso-vinculado">
                                    Conta vinculada
                                </strong>
                            <?php else: ?>
                                <strong class="cliente-admin-acesso-badge cliente-admin-acesso-sem-vinculo">
                                    Sem conta vinculada
                                </strong>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <div class="cliente-admin-actions">
            <button class="btn btn-primary" type="submit">
                <?= $edicao ? 'Atualizar cliente' : 'Cadastrar cliente' ?>
            </button>

            <a class="btn btn-outline-secondary" href="clientes.php">Voltar</a>
        </div>
    </form>
</main>

<script src="assets/js/consulta-cep.js"></script>
<script src="assets/js/cadastro-cliente-admin.js"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>
