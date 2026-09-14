<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../services/ImagemProfissionalService.php';

exigirProfissional();

$empresaId = (int) $_SESSION['empresa_id'];
$profissionalId = (int) $_SESSION['profissional_id'];
$pessoaId = (int) $_SESSION['pessoa_id'];
$pdo = getDB();

if (empty($_SESSION['csrf_meus_dados_profissional'])) {
    $_SESSION['csrf_meus_dados_profissional'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['csrf_meus_dados_profissional'];

$flash = $_SESSION['flash_meus_dados_profissional'] ?? null;
unset($_SESSION['flash_meus_dados_profissional']);

$old = is_array($flash['old'] ?? null) ? $flash['old'] : [];
$erros = is_array($flash['erros'] ?? null) ? $flash['erros'] : [];
$mensagem = is_string($flash['mensagem'] ?? null) ? $flash['mensagem'] : null;
$tipoMensagem = ($flash['tipo'] ?? '') === 'success' ? 'success' : 'danger';

$stmt = $pdo->prepare(
    'SELECT
        pr.id,
        pr.pessoa_id,
        pr.usuario_id,
        pr.cargo,
        pr.descricao,
        pr.foto_url,
        p.nome_completo,
        p.cpf,
        p.data_nascimento,
        p.genero,
        p.email,
        u.email AS email_acesso,
        tp.numero AS telefone,
        COALESCE(tp.whatsapp, 0) AS whatsapp,
        ep.cep,
        ep.logradouro,
        ep.numero AS endereco_numero,
        ep.complemento,
        ep.bairro,
        ep.cidade,
        ep.estado
     FROM profissionais pr
     INNER JOIN pessoas p
       ON p.id = pr.pessoa_id
      AND p.empresa_id = pr.empresa_id
     INNER JOIN usuarios u
       ON u.id = pr.usuario_id
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
     WHERE pr.id = :profissional_id
       AND pr.pessoa_id = :pessoa_id
       AND pr.empresa_id = :empresa_id
       AND pr.usuario_id = :usuario_id
     LIMIT 1'
);
$stmt->execute([
    ':profissional_id' => $profissionalId,
    ':pessoa_id' => $pessoaId,
    ':empresa_id' => $empresaId,
    ':usuario_id' => (int) $_SESSION['user_id'],
]);
$profissional = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profissional) {
    encerrarSessao();
    header('Location: login.php');
    exit;
}

function valorMeusDados(array $old, array $dados, string $campo, string $padrao = ''): string
{
    $valor = array_key_exists($campo, $old) ? $old[$campo] : ($dados[$campo] ?? $padrao);
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$temOld = $old !== [];
$generoAtual = $temOld
    ? (string) ($old['genero'] ?? 'nao_informado')
    : (string) ($profissional['genero'] ?? 'nao_informado');
$whatsappMarcado = $temOld
    ? !empty($old['whatsapp'])
    : (int) ($profissional['whatsapp'] ?? 0) === 1;

$fotoAtual = is_string($profissional['foto_url'] ?? null) ? $profissional['foto_url'] : '';
$fotoUrls = ImagemProfissionalService::urls($fotoAtual);
$nomePreview = valorMeusDados($old, $profissional, 'nome_completo', 'Profissional');
$iniciaisPreview = ImagemProfissionalService::iniciais(
    html_entity_decode($nomePreview, ENT_QUOTES, 'UTF-8')
);

$pageTitle = 'Meus dados';
$pageCss = 'cadastro-profissional.css';
$pageJs = 'meus-dados-profissional.js';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/partials/navbar.php';
?>

<main class="app-content">
    <div class="app-page-header">
        <h1>Meus dados</h1>
        <p>Mantenha seus dados pessoais, contato, endereço e apresentação profissional atualizados.</p>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipoMensagem ?>" role="alert">
            <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form id="meusDadosProfissionalForm" action="api/meus-dados-profissional.php" method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <div class="row">
            <div class="col-12 col-xl-8">
                <div class="app-card mb-4">
                    <div class="app-card-header"><h2>Dados pessoais</h2></div>
                    <div class="app-card-body">
                        <div class="form-group">
                            <label for="nome_completo">Nome completo <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control<?= isset($erros['nome_completo']) ? ' is-invalid' : '' ?>"
                                   id="nome_completo" name="nome_completo" maxlength="160"
                                   value="<?= valorMeusDados($old, $profissional, 'nome_completo') ?>" required>
                            <div class="invalid-feedback">
                                <?= htmlspecialchars($erros['nome_completo'] ?? 'Informe o nome completo.', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="cpf">CPF</label>
                                <input type="text" class="form-control" id="cpf"
                                       value="<?= valorMeusDados([], $profissional, 'cpf') ?>" readonly>
                                <small class="form-text text-muted">Dado cadastral protegido.</small>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="data_nascimento">Data de nascimento</label>
                                <input type="date"
                                       class="form-control<?= isset($erros['data_nascimento']) ? ' is-invalid' : '' ?>"
                                       id="data_nascimento" name="data_nascimento"
                                       value="<?= valorMeusDados($old, $profissional, 'data_nascimento') ?>">
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($erros['data_nascimento'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="genero">Gênero</label>
                                <select class="custom-select<?= isset($erros['genero']) ? ' is-invalid' : '' ?>"
                                        id="genero" name="genero">
                                    <option value="nao_informado" <?= $generoAtual === 'nao_informado' ? 'selected' : '' ?>>Prefiro não informar</option>
                                    <option value="feminino" <?= $generoAtual === 'feminino' ? 'selected' : '' ?>>Feminino</option>
                                    <option value="masculino" <?= $generoAtual === 'masculino' ? 'selected' : '' ?>>Masculino</option>
                                    <option value="nao_binario" <?= $generoAtual === 'nao_binario' ? 'selected' : '' ?>>Não binário</option>
                                </select>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($erros['genero'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="email_acesso">E-mail de acesso</label>
                                <input type="email" class="form-control" id="email_acesso"
                                       value="<?= valorMeusDados([], $profissional, 'email_acesso') ?>" readonly>
                                <small class="form-text text-muted">O e-mail de acesso não é alterado por esta tela.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="app-card mb-4">
                    <div class="app-card-header"><h2>Contato</h2></div>
                    <div class="app-card-body">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-8">
                                <label for="telefone">Telefone principal</label>
                                <input type="text"
                                       class="form-control<?= isset($erros['telefone']) ? ' is-invalid' : '' ?>"
                                       id="telefone" name="telefone" maxlength="30" inputmode="tel"
                                       value="<?= valorMeusDados($old, $profissional, 'telefone') ?>"
                                       placeholder="(11) 99999-9999">
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($erros['telefone'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                            <div class="form-group col-md-4">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input"
                                           id="whatsapp" name="whatsapp" value="1"
                                           <?= $whatsappMarcado ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="whatsapp">É WhatsApp</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="app-card mb-4">
                    <div class="app-card-header"><h2>Endereço</h2></div>
                    <div class="app-card-body">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="cep">CEP</label>
                                <input type="text" class="form-control<?= isset($erros['cep']) ? ' is-invalid' : '' ?>"
                                       id="cep" name="cep" maxlength="9" inputmode="numeric"
                                       value="<?= valorMeusDados($old, $profissional, 'cep') ?>"
                                       placeholder="00000-000">
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($erros['cep'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <small id="cepFeedback" class="form-text text-muted"></small>
                            </div>
                            <div class="form-group col-md-8">
                                <label for="logradouro">Logradouro</label>
                                <input type="text" class="form-control<?= isset($erros['logradouro']) ? ' is-invalid' : '' ?>"
                                       id="logradouro" name="logradouro" maxlength="180"
                                       value="<?= valorMeusDados($old, $profissional, 'logradouro') ?>">
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($erros['logradouro'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="numero">Número</label>
                                <input type="text" class="form-control<?= isset($erros['numero']) ? ' is-invalid' : '' ?>"
                                       id="numero" name="numero" maxlength="30"
                                       value="<?= valorMeusDados($old, $profissional, 'endereco_numero') ?>">
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($erros['numero'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                            <div class="form-group col-md-8">
                                <label for="complemento">Complemento</label>
                                <input type="text" class="form-control" id="complemento" name="complemento"
                                       maxlength="120" value="<?= valorMeusDados($old, $profissional, 'complemento') ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-5">
                                <label for="bairro">Bairro</label>
                                <input type="text" class="form-control<?= isset($erros['bairro']) ? ' is-invalid' : '' ?>"
                                       id="bairro" name="bairro" maxlength="120"
                                       value="<?= valorMeusDados($old, $profissional, 'bairro') ?>">
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($erros['bairro'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                            <div class="form-group col-md-5">
                                <label for="cidade">Cidade</label>
                                <input type="text" class="form-control<?= isset($erros['cidade']) ? ' is-invalid' : '' ?>"
                                       id="cidade" name="cidade" maxlength="120"
                                       value="<?= valorMeusDados($old, $profissional, 'cidade') ?>" readonly>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($erros['cidade'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="estado">UF</label>
                                <input type="text" class="form-control<?= isset($erros['estado']) ? ' is-invalid' : '' ?>"
                                       id="estado" name="estado" maxlength="2"
                                       value="<?= valorMeusDados($old, $profissional, 'estado') ?>" readonly>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($erros['estado'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                        </div>
                        <small class="form-text text-muted">O endereço é opcional. Ao informar o CEP, complete os dados do endereço.</small>
                    </div>
                </div>

                <div class="app-card mb-4">
                    <div class="app-card-header"><h2>Dados profissionais</h2></div>
                    <div class="app-card-body">
                        <div class="form-group">
                            <label for="cargo">Cargo / especialidade</label>
                            <input type="text" class="form-control<?= isset($erros['cargo']) ? ' is-invalid' : '' ?>"
                                   id="cargo" name="cargo" maxlength="120"
                                   value="<?= valorMeusDados($old, $profissional, 'cargo') ?>">
                            <div class="invalid-feedback">
                                <?= htmlspecialchars($erros['cargo'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="descricao">Descrição</label>
                            <textarea class="form-control<?= isset($erros['descricao']) ? ' is-invalid' : '' ?>"
                                      id="descricao" name="descricao" rows="4" maxlength="3000"><?= valorMeusDados($old, $profissional, 'descricao') ?></textarea>
                            <div class="invalid-feedback">
                                <?= htmlspecialchars($erros['descricao'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4 text-right">
                    <button type="submit" class="btn btn-primary" id="btnSalvarMeusDados">Salvar alterações</button>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="app-card cadastro-profissional-foto-card mb-4">
                    <div class="app-card-header"><h2>Minha foto</h2></div>
                    <div class="app-card-body text-center">
                        <div class="cadastro-profissional-foto-preview" id="fotoProfissionalPreview">
                            <?php if ($fotoUrls['m']): ?>
                                <img src="<?= htmlspecialchars((string) $fotoUrls['m'], ENT_QUOTES, 'UTF-8') ?>"
                                     srcset="<?= htmlspecialchars((string) $fotoUrls['p'], ENT_QUOTES, 'UTF-8') ?> 160w, <?= htmlspecialchars((string) $fotoUrls['m'], ENT_QUOTES, 'UTF-8') ?> 320w, <?= htmlspecialchars((string) $fotoUrls['g'], ENT_QUOTES, 'UTF-8') ?> 500w"
                                     sizes="180px" alt="Minha foto">
                            <?php else: ?>
                                <span id="fotoProfissionalIniciais"><?= htmlspecialchars($iniciaisPreview, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="custom-file text-left mt-3">
                            <input type="file"
                                   class="custom-file-input<?= isset($erros['foto']) ? ' is-invalid' : '' ?>"
                                   id="foto" name="foto" accept="image/jpeg,image/png,image/webp">
                            <label class="custom-file-label" for="foto" data-browse="Escolher">Selecionar foto</label>
                            <?php if (isset($erros['foto'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($erros['foto'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                        </div>
                        <small class="form-text text-muted mt-2">JPG, PNG ou WebP, até 5 MB.</small>
                    </div>
                </div>
            </div>
        </div>
    </form>
</main>

<script src="assets/js/localizacao-brasil.js"></script>
<script src="assets/js/consulta-cep.js"></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
