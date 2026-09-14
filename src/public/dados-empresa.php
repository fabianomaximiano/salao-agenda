<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigirAdministrador();

if (empty($_SESSION['csrf_dados_empresa']) || !is_string($_SESSION['csrf_dados_empresa'])) {
    $_SESSION['csrf_dados_empresa'] = bin2hex(random_bytes(32));
}

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();

$stmt = $pdo->prepare(
    'SELECT e.*, es.segmento_id
     FROM empresas e
     LEFT JOIN empresa_segmentos es ON es.empresa_id = e.id
     WHERE e.id = :empresa_id
     LIMIT 1'
);
$stmt->execute([':empresa_id' => $empresaId]);
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empresa) {
    http_response_code(404);
    exit('Empresa não encontrada.');
}

$segmentos = $pdo->query(
    'SELECT id, nome FROM segmentos WHERE ativo = 1 ORDER BY nome'
)->fetchAll(PDO::FETCH_ASSOC);

$mensagem = $_SESSION['dados_empresa_sucesso'] ?? null;
$erro = $_SESSION['dados_empresa_erro'] ?? null;
unset($_SESSION['dados_empresa_sucesso'], $_SESSION['dados_empresa_erro']);

function e(array $dados, string $campo): string {
    return htmlspecialchars((string) ($dados[$campo] ?? ''), ENT_QUOTES, 'UTF-8');
}

$doc = preg_replace('/\D+/', '', (string) $empresa['documento']) ?? '';
$docFormatado = $doc;
if ($empresa['tipo_documento'] === 'cnpj' && strlen($doc) === 14) {
    $docFormatado = preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $doc) ?? $doc;
} elseif ($empresa['tipo_documento'] === 'cpf' && strlen($doc) === 11) {
    $docFormatado = preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $doc) ?? $doc;
}

$pageTitle = 'Dados da empresa';
$pageJs = 'dados-empresa.js';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/partials/navbar.php';
?>
<main class="app-content">
    <div class="app-page-header">
        <h1>Dados da empresa</h1>
        <p>Mantenha os dados cadastrais, de contato e endereço da empresa atualizados.</p>
    </div>

    <?php if (is_string($mensagem) && $mensagem !== ''): ?>
        <div class="alert alert-success" role="status"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (is_string($erro) && $erro !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="app-card mb-4">
        <div class="app-card-header"><h2>Cadastro da empresa</h2></div>
        <div class="app-card-body">
            <form action="api/dados-empresa.php" method="post" id="dadosEmpresaForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_dados_empresa'], ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-row">
                    <div class="form-group col-md-7">
                        <label for="nome_fantasia">Nome fantasia <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nome_fantasia" name="nome_fantasia" maxlength="150" value="<?= e($empresa, 'nome_fantasia') ?>" required>
                        <div class="invalid-feedback">Informe o nome da empresa.</div>
                    </div>
                    <div class="form-group col-md-5">
                        <label for="segmento_id">Segmento <span class="text-danger">*</span></label>
                        <select class="custom-select" id="segmento_id" name="segmento_id" required>
                            <option value="">Selecione</option>
                            <?php foreach ($segmentos as $segmento): ?>
                                <option value="<?= (int) $segmento['id'] ?>" <?= (int) $empresa['segmento_id'] === (int) $segmento['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $segmento['nome'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Escolha um segmento.</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="razao_social">Razão social</label>
                    <input type="text" class="form-control" id="razao_social" name="razao_social" maxlength="180" value="<?= e($empresa, 'razao_social') ?>">
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="tipo_documento">Tipo de documento</label>
                        <input type="text" class="form-control" id="tipo_documento" value="<?= htmlspecialchars(strtoupper((string) $empresa['tipo_documento']), ENT_QUOTES, 'UTF-8') ?>" readonly>
                    </div>
                    <div class="form-group col-md-8">
                        <label for="documento">CPF / CNPJ</label>
                        <input type="text" class="form-control" id="documento" value="<?= htmlspecialchars($docFormatado, ENT_QUOTES, 'UTF-8') ?>" readonly>
                        <small class="form-text text-muted">O documento da empresa não pode ser alterado por esta tela.</small>
                    </div>
                </div>

                <hr>
                <h3 class="h5 mb-3">Contato</h3>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="email_empresa">E-mail da empresa</label>
                        <input type="email" class="form-control" id="email_empresa" name="email_empresa" maxlength="190" autocomplete="email" value="<?= e($empresa, 'email') ?>">
                        <div class="invalid-feedback">Informe um e-mail válido.</div>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="telefone_empresa">Telefone</label>
                        <input type="tel" class="form-control" id="telefone_empresa" name="telefone_empresa" maxlength="15" inputmode="tel" value="<?= e($empresa, 'telefone') ?>">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="whatsapp_empresa">WhatsApp</label>
                        <input type="tel" class="form-control" id="whatsapp_empresa" name="whatsapp_empresa" maxlength="15" inputmode="tel" value="<?= e($empresa, 'whatsapp') ?>">
                    </div>
                </div>

                <hr>
                <h3 class="h5 mb-3">Endereço</h3>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="cep">CEP <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="cep" name="cep" maxlength="9" inputmode="numeric" autocomplete="postal-code" value="<?= e($empresa, 'cep') ?>" required>
                        <div class="invalid-feedback">Informe um CEP válido.</div>
                        <small id="cepFeedback" class="form-text" aria-live="polite"></small>
                    </div>
                    <div class="form-group col-md-8">
                        <label for="logradouro">Logradouro <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="logradouro" name="logradouro" maxlength="180" autocomplete="address-line1" value="<?= e($empresa, 'logradouro') ?>" required>
                        <div class="invalid-feedback">Informe o logradouro.</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="numero">Número <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="numero" name="numero" maxlength="30" value="<?= e($empresa, 'numero') ?>" required>
                        <div class="invalid-feedback">Informe o número.</div>
                    </div>
                    <div class="form-group col-md-5">
                        <label for="complemento">Complemento</label>
                        <input type="text" class="form-control" id="complemento" name="complemento" maxlength="120" autocomplete="address-line2" value="<?= e($empresa, 'complemento') ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="bairro">Bairro <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="bairro" name="bairro" maxlength="120" value="<?= e($empresa, 'bairro') ?>" required>
                        <div class="invalid-feedback">Informe o bairro.</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-8">
                        <label for="cidade">Cidade <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="cidade" name="cidade" maxlength="120" autocomplete="address-level2" value="<?= e($empresa, 'cidade') ?>" required readonly>
                        <div class="invalid-feedback">Informe a cidade.</div>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="estado">UF <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="estado" name="estado" maxlength="2" autocomplete="address-level1" value="<?= e($empresa, 'estado') ?>" required readonly>
                        <div class="invalid-feedback">Informe a UF.</div>
                    </div>
                </div>

                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mt-4">
                    <a href="dashboard.php" class="btn btn-outline-secondary mb-2 mb-sm-0">Voltar ao dashboard</a>
                    <button type="submit" class="btn btn-primary">Salvar dados da empresa</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script src="assets/js/localizacao-brasil.js"></script>
<script src="assets/js/consulta-cep.js"></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
