<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigirAdministrador();

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();

if (empty($_SESSION['csrf_cadastro_colaborador'])) {
    $_SESSION['csrf_cadastro_colaborador'] = bin2hex(random_bytes(32));
}

$csrfToken = (string) $_SESSION['csrf_cadastro_colaborador'];
$flash = $_SESSION['flash_colaborador'] ?? null;
unset($_SESSION['flash_colaborador']);

$old = is_array($flash['old'] ?? null) ? $flash['old'] : [];
$erros = is_array($flash['erros'] ?? null) ? $flash['erros'] : [];
$editarId = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
$c = null;

if ($editarId) {
    $stmt = $pdo->prepare(
        'SELECT c.*, p.nome_completo, p.cpf, p.data_nascimento, p.genero, p.email,
                tp.numero AS telefone, COALESCE(tp.whatsapp, 0) AS whatsapp,
                ep.cep, ep.logradouro, ep.numero, ep.complemento, ep.bairro, ep.cidade, ep.estado,
                u.ativo AS usuario_ativo, u.senha_hash
           FROM colaboradores c
           INNER JOIN pessoas p
                   ON p.id = c.pessoa_id
                  AND p.empresa_id = c.empresa_id
           LEFT JOIN telefones_pessoa tp
                  ON tp.id = (
                        SELECT tp2.id
                          FROM telefones_pessoa tp2
                         WHERE tp2.pessoa_id = p.id
                         ORDER BY tp2.principal DESC, tp2.id ASC
                         LIMIT 1
                  )
           LEFT JOIN enderecos_pessoa ep ON ep.pessoa_id = p.id
           LEFT JOIN usuarios u ON u.id = c.usuario_id
          WHERE c.id = :id
            AND c.empresa_id = :empresa_id
          LIMIT 1'
    );
    $stmt->execute([
        ':id' => $editarId,
        ':empresa_id' => $empresaId,
    ]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$c) {
        header('Location: colaboradores.php');
        exit;
    }
}

function valorColaborador(array $old, ?array $c, string $campo, string $padrao = ''): string
{
    $valor = array_key_exists($campo, $old) ? $old[$campo] : ($c[$campo] ?? $padrao);
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$modoEdicao = $c !== null;
$temOld = $old !== [];
$ativo = $temOld ? !empty($old['ativo']) : (!$modoEdicao || (int) $c['ativo'] === 1);
$whatsapp = $temOld ? !empty($old['whatsapp']) : ($modoEdicao && (int) $c['whatsapp'] === 1);

$permissoes = [
    'agenda' => 'Agenda',
    'clientes' => 'Clientes',
    'profissionais' => 'Profissionais',
    'servicos' => 'Serviços',
    'financeiro' => 'Financeiro',
    'relatorios' => 'Relatórios',
    'configuracoes' => 'Configurações',
];

$temConta = $modoEdicao && (int) ($c['usuario_id'] ?? 0) > 0;
$acessoAtivo = $temConta
    && (int) ($c['usuario_ativo'] ?? 0) === 1
    && !empty($c['senha_hash']);

$pageTitle = $modoEdicao ? 'Editar colaborador' : 'Cadastrar colaborador';
$pageCss = 'cadastro-colaborador.css?v=20260914-1';
$pageJs = 'cadastro-colaborador.js?v=20260914-1';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/partials/navbar.php';
?>
<main class="app-content colaborador-admin-page">
    <div class="app-page-header colaborador-admin-header">
        <h1><?= $modoEdicao ? 'Editar colaborador' : 'Cadastrar colaborador' ?></h1>
        <p>Dados pessoais, contato, endereço, função e permissões de acesso.</p>
    </div>

    <?php if (!empty($flash['mensagem'])): ?>
        <div class="alert alert-<?= ($flash['tipo'] ?? '') === 'success' ? 'success' : 'danger' ?>">
            <?= htmlspecialchars((string) $flash['mensagem'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form action="api/colaboradores.php" method="post" class="colaborador-admin-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="acao" value="<?= $modoEdicao ? 'atualizar' : 'criar' ?>">
        <?php if ($modoEdicao): ?>
            <input type="hidden" name="colaborador_id" value="<?= (int) $c['id'] ?>">
        <?php endif; ?>

        <div class="row">
            <div class="col-12 col-xl-8">
                <section class="app-card colaborador-admin-section mb-4">
                    <div class="app-card-header colaborador-admin-section-header">
                        <div>
                            <h2>Dados pessoais</h2>
                            <p>Identificação do colaborador.</p>
                        </div>
                    </div>
                    <div class="app-card-body colaborador-admin-section-body">
                        <div class="form-group">
                            <label for="nome_completo">Nome completo <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                class="form-control<?= isset($erros['nome_completo']) ? ' is-invalid' : '' ?>"
                                id="nome_completo"
                                name="nome_completo"
                                maxlength="160"
                                required
                                value="<?= valorColaborador($old, $c, 'nome_completo') ?>"
                            >
                            <div class="invalid-feedback">
                                <?= htmlspecialchars($erros['nome_completo'] ?? 'Informe o nome completo.', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="cpf">CPF</label>
                                <input
                                    type="text"
                                    class="form-control<?= isset($erros['cpf']) ? ' is-invalid' : '' ?>"
                                    id="cpf"
                                    name="cpf"
                                    maxlength="14"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    placeholder="000.000.000-00"
                                    value="<?= valorColaborador($old, $c, 'cpf') ?>"
                                    <?= $modoEdicao ? 'readonly' : '' ?>
                                >
                                <?php if ($modoEdicao): ?>
                                    <small class="form-text text-muted">CPF protegido após o cadastro.</small>
                                <?php endif; ?>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($erros['cpf'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="data_nascimento">Data de nascimento</label>
                                <input
                                    type="date"
                                    class="form-control<?= isset($erros['data_nascimento']) ? ' is-invalid' : '' ?>"
                                    id="data_nascimento"
                                    name="data_nascimento"
                                    value="<?= valorColaborador($old, $c, 'data_nascimento') ?>"
                                >
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($erros['data_nascimento'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-0">
                            <label for="genero">Gênero</label>
                            <?php
                            $genero = $temOld
                                ? (string) ($old['genero'] ?? 'nao_informado')
                                : (string) ($c['genero'] ?? 'nao_informado');
                            ?>
                            <select class="custom-select" id="genero" name="genero">
                                <option value="nao_informado" <?= $genero === 'nao_informado' ? 'selected' : '' ?>>Prefiro não informar</option>
                                <option value="feminino" <?= $genero === 'feminino' ? 'selected' : '' ?>>Feminino</option>
                                <option value="masculino" <?= $genero === 'masculino' ? 'selected' : '' ?>>Masculino</option>
                                <option value="nao_binario" <?= $genero === 'nao_binario' ? 'selected' : '' ?>>Não binário</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="app-card colaborador-admin-section mb-4">
                    <div class="app-card-header colaborador-admin-section-header">
                        <div>
                            <h2>Contato</h2>
                            <p>Dados utilizados pela empresa e para acesso ao sistema.</p>
                        </div>
                    </div>
                    <div class="app-card-body colaborador-admin-section-body">
                        <div class="form-group">
                            <label for="email">E-mail corporativo <span class="text-danger">*</span></label>
                            <input
                                type="email"
                                class="form-control<?= isset($erros['email']) ? ' is-invalid' : '' ?>"
                                id="email"
                                name="email"
                                maxlength="190"
                                required
                                autocomplete="email"
                                value="<?= valorColaborador($old, $c, 'email') ?>"
                                <?= $temConta ? 'readonly' : '' ?>
                            >
                            <?php if ($temConta): ?>
                                <small class="form-text text-muted">E-mail protegido porque já está vinculado à conta de acesso.</small>
                            <?php endif; ?>
                            <div class="invalid-feedback">
                                <?= htmlspecialchars($erros['email'] ?? 'Informe um e-mail válido.', ENT_QUOTES, 'UTF-8') ?>
                            </div>
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
                                    value="<?= valorColaborador($old, $c, 'telefone') ?>"
                                >
                            </div>
                            <div class="form-group col-md-4">
                                <div class="custom-control custom-checkbox colaborador-admin-check">
                                    <input
                                        type="checkbox"
                                        class="custom-control-input"
                                        id="whatsapp"
                                        name="whatsapp"
                                        value="1"
                                        <?= $whatsapp ? 'checked' : '' ?>
                                    >
                                    <label class="custom-control-label" for="whatsapp">É WhatsApp</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="app-card colaborador-admin-section mb-4">
                    <div class="app-card-header colaborador-admin-section-header">
                        <div>
                            <h2>Endereço</h2>
                            <p>Opcional. Informe o CEP para preencher o endereço automaticamente.</p>
                        </div>
                    </div>
                    <div class="app-card-body colaborador-admin-section-body">
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
                                    value="<?= valorColaborador($old, $c, 'cep') ?>"
                                >
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($erros['cep'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                            <div class="form-group col-md-8">
                                <label for="logradouro">Logradouro</label>
                                <input type="text" class="form-control" id="logradouro" name="logradouro" maxlength="180" value="<?= valorColaborador($old, $c, 'logradouro') ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="numero">Número</label>
                                <input type="text" class="form-control" id="numero" name="numero" maxlength="30" value="<?= valorColaborador($old, $c, 'numero') ?>">
                            </div>
                            <div class="form-group col-md-8">
                                <label for="complemento">Complemento</label>
                                <input type="text" class="form-control" id="complemento" name="complemento" maxlength="120" value="<?= valorColaborador($old, $c, 'complemento') ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-5">
                                <label for="bairro">Bairro</label>
                                <input type="text" class="form-control" id="bairro" name="bairro" maxlength="120" value="<?= valorColaborador($old, $c, 'bairro') ?>">
                            </div>
                            <div class="form-group col-md-5">
                                <label for="cidade">Cidade</label>
                                <input type="text" class="form-control" id="cidade" name="cidade" maxlength="120" value="<?= valorColaborador($old, $c, 'cidade') ?>">
                            </div>
                            <div class="form-group col-md-2">
                                <label for="estado">UF</label>
                                <input type="text" class="form-control text-uppercase" id="estado" name="estado" maxlength="2" value="<?= valorColaborador($old, $c, 'estado') ?>">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="app-card colaborador-admin-section mb-4">
                    <div class="app-card-header colaborador-admin-section-header">
                        <div>
                            <h2>Função e permissões</h2>
                            <p>Defina o que este colaborador poderá administrar no sistema.</p>
                        </div>
                    </div>
                    <div class="app-card-body colaborador-admin-section-body">
                        <div class="form-group">
                            <label for="cargo">Cargo / função</label>
                            <input
                                type="text"
                                class="form-control"
                                id="cargo"
                                name="cargo"
                                maxlength="120"
                                placeholder="Ex.: Recepcionista, Gerente"
                                value="<?= valorColaborador($old, $c, 'cargo') ?>"
                            >
                        </div>

                        <div class="row colaborador-permissoes">
                            <?php foreach ($permissoes as $chave => $rotulo): ?>
                                <?php
                                $campo = 'pode_' . $chave;
                                $marcado = $temOld
                                    ? !empty($old[$campo])
                                    : ($modoEdicao
                                        ? (int) $c[$campo] === 1
                                        : in_array($chave, ['agenda', 'clientes', 'profissionais', 'servicos'], true));
                                ?>
                                <div class="col-md-6 mb-3">
                                    <div class="custom-control custom-switch">
                                        <input
                                            type="checkbox"
                                            class="custom-control-input"
                                            id="<?= $campo ?>"
                                            name="<?= $campo ?>"
                                            value="1"
                                            <?= $marcado ? 'checked' : '' ?>
                                        >
                                        <label class="custom-control-label" for="<?= $campo ?>"><?= $rotulo ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <div class="colaborador-admin-actions">
                    <a href="colaboradores.php" class="btn btn-outline-secondary">Voltar</a>
                    <button class="btn btn-primary" type="submit">
                        <?= $modoEdicao ? 'Salvar alterações' : 'Salvar colaborador' ?>
                    </button>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <section class="app-card colaborador-admin-section mb-4">
                    <div class="app-card-header colaborador-admin-section-header">
                        <div>
                            <h2>Status</h2>
                            <p>Controle administrativo do colaborador.</p>
                        </div>
                    </div>
                    <div class="app-card-body colaborador-admin-section-body">
                        <div class="custom-control custom-switch colaborador-admin-status">
                            <input
                                type="checkbox"
                                class="custom-control-input"
                                id="ativo"
                                name="ativo"
                                value="1"
                                <?= $ativo ? 'checked' : '' ?>
                            >
                            <label class="custom-control-label" for="ativo">Colaborador ativo</label>
                        </div>
                    </div>
                </section>

                <?php if ($modoEdicao): ?>
                    <section class="app-card colaborador-admin-section mb-4">
                        <div class="app-card-header colaborador-admin-section-header">
                            <div>
                                <h2>Acesso ao sistema</h2>
                                <p>Situação da conta vinculada.</p>
                            </div>
                        </div>
                        <div class="app-card-body colaborador-admin-section-body">
                            <div class="colaborador-admin-acesso">
                                <?php if ($acessoAtivo): ?>
                                    <span class="badge badge-success">Acesso ativo</span>
                                    <p class="text-muted">A conta do colaborador já está ativa.</p>
                                <?php elseif ($temConta): ?>
                                    <span class="badge badge-warning">Aguardando ativação</span>
                                    <p class="text-muted">Você pode reenviar o convite para o e-mail corporativo cadastrado.</p>
                                    <button class="btn btn-outline-primary btn-block" form="formAcessoColaborador" type="submit">
                                        Reenviar convite
                                    </button>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Sem acesso</span>
                                    <p class="text-muted">Libere o acesso para enviar o convite ao colaborador.</p>
                                    <button class="btn btn-outline-primary btn-block" form="formAcessoColaborador" type="submit">
                                        Liberar acesso
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <?php if ($modoEdicao && !$acessoAtivo): ?>
        <form id="formAcessoColaborador" action="api/colaboradores.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="acao" value="liberar_acesso">
            <input type="hidden" name="colaborador_id" value="<?= (int) $c['id'] ?>">
        </form>
    <?php endif; ?>
</main>

<script src="assets/js/consulta-cep.js"></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
