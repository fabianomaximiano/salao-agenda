<?php

declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/cliente-auth.php';
$pdo = getDB();
$slug = trim((string)($_GET['empresa'] ?? $_POST['empresa'] ?? ''));
$empresa = clienteEmpresaPorSlug($pdo, $slug);
if (!$empresa) {
    http_response_code(404);
    exit('Empresa não encontrada ou indisponível.');
}
$erro = null;
$v = ['nome_completo' => '', 'cpf' => '', 'data_nascimento' => '', 'genero' => 'nao_informado', 'email' => '', 'celular' => '', 'cep' => '', 'logradouro' => '', 'numero' => '', 'complemento' => '', 'bairro' => '', 'cidade' => '', 'estado' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($v) as $k) $v[$k] = trim((string)($_POST[$k] ?? $v[$k]));
    $senha = (string)($_POST['senha'] ?? '');
    $conf = (string)($_POST['confirmar_senha'] ?? '');
    $email = mb_strtolower($v['email']);
    $gen = ['masculino', 'feminino', 'nao_binario', 'nao_informado'];
    if ($v['nome_completo'] === '') $erro = 'Informe seu nome completo.';
    elseif (strlen(clienteSomenteDigitos($v['cpf'])) !== 11) $erro = 'Informe um CPF válido.';
    elseif (!clienteDataValida($v['data_nascimento'])) $erro = 'Informe sua data de nascimento.';
    elseif (!in_array($v['genero'], $gen, true)) $erro = 'Gênero inválido.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erro = 'Informe um e-mail válido.';
    elseif (strlen(clienteSomenteDigitos($v['celular'])) < 10 || strlen(clienteSomenteDigitos($v['celular'])) > 11) $erro = 'Informe um celular/WhatsApp válido.';
    elseif ($senha === '' || strlen($senha) < 8) $erro = 'A senha deve ter pelo menos 8 caracteres.';
    elseif ($senha !== $conf) $erro = 'As senhas não conferem.';
    else try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email=:email LIMIT 1 FOR UPDATE');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) throw new RuntimeException('Este e-mail já possui uma conta. Entre com sua senha ou use o Google.');
        $stmt = $pdo->prepare('INSERT INTO usuarios (email,senha_hash,ativo) VALUES (:email,:hash,1)');
        $stmt->execute([':email' => $email, ':hash' => password_hash($senha, PASSWORD_DEFAULT)]);
        $uid = (int)$pdo->lastInsertId();
        $cliente = clienteSalvarPerfil($pdo, $empresa, $uid, $email, ['nome_completo' => $v['nome_completo'], 'cpf' => $v['cpf'], 'data_nascimento' => $v['data_nascimento'], 'genero' => $v['genero'], 'celular' => $v['celular'], 'cep' => $v['cep'], 'logradouro' => $v['logradouro'], 'numero' => $v['numero'], 'complemento' => $v['complemento'], 'bairro' => $v['bairro'], 'cidade' => $v['cidade'], 'estado' => mb_strtoupper($v['estado'])]);
        $pdo->commit();
        clienteIniciarSessao($pdo, ['id' => $uid, 'email' => $email], $cliente, $empresa);
        header('Location: dashboard-cliente.php');
        exit;
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $erro = $e->getMessage();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Erro cadastro cliente: ' . $e->getMessage());
        $erro = 'Não foi possível concluir seu cadastro agora.';
    }
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <title>Criar conta | <?= htmlspecialchars((string)$empresa['nome_fantasia'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/cliente-cadastro.css">
</head>

<body>
    <div class="cliente-cadastro-page">
        <div class="container">
            <div class="cliente-cadastro-card">
                <div class="mb-4">
                    <div class="cliente-cadastro-brand"><?= htmlspecialchars((string)$empresa['nome_fantasia'], ENT_QUOTES, 'UTF-8') ?></div>
                    <h1 class="h3 font-weight-bold mt-2">Crie sua conta</h1>
                    <p class="text-muted">E-mail e celular/WhatsApp serão seus contatos principais.</p>
                </div><?php if ($erro): ?><div class="alert alert-danger"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                <form id="cadastroClienteForm" method="post" novalidate>
                    <input type="hidden" name="empresa" value="<?= htmlspecialchars((string)$empresa['slug'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-row">
                        <div class="form-group col-md-8"><label>Nome completo</label><input class="form-control" name="nome_completo" id="nome_completo" required value="<?= htmlspecialchars($v['nome_completo'], ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="form-group col-md-4"><label>CPF</label><input class="form-control" name="cpf" id="cpf" required maxlength="14" value="<?= htmlspecialchars($v['cpf'], ENT_QUOTES, 'UTF-8') ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6"><label>Data de nascimento</label><input type="date" class="form-control" name="data_nascimento" required value="<?= htmlspecialchars($v['data_nascimento'], ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="form-group col-md-6"><label>Gênero</label><select class="form-control" name="genero">
                                <option value="nao_informado">Prefiro não informar</option>
                                <option value="feminino">Feminino</option>
                                <option value="masculino">Masculino</option>
                                <option value="nao_binario">Não binário</option>
                            </select></div>
                    </div>
                    <h2 class="h5 mt-4">Contato</h2>
                    <div class="form-row">
                        <div class="form-group col-md-6"><label>E-mail</label><input type="email" class="form-control" name="email" required value="<?= htmlspecialchars($v['email'], ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="form-group col-md-6"><label>Celular / WhatsApp</label><input class="form-control" name="celular" id="celular" required maxlength="15" value="<?= htmlspecialchars($v['celular'], ENT_QUOTES, 'UTF-8') ?>"></div>
                    </div>
                    <h2 class="h5 mt-4">Endereço</h2>
                    <div class="form-row">
                        <div class="form-group col-md-3"><label>CEP</label><input class="form-control" name="cep" id="cep" maxlength="9" placeholder="00000-000" inputmode="numeric" autocomplete="postal-code"><small id="cepFeedback" class="form-text" aria-live="polite"></small></div>
                        <div class="form-group col-md-7"><label>Logradouro</label><input class="form-control" name="logradouro" id="logradouro"></div>
                        <div class="form-group col-md-2"><label>Número</label><input class="form-control" name="numero" id="numero"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4"><label>Complemento</label><input class="form-control" name="complemento" id="complemento" autocomplete="address-line2"></div>
                        <div class="form-group col-md-4"><label>Bairro</label><input class="form-control" name="bairro" id="bairro"></div>
                        <div class="form-group col-md-3"><label>Cidade</label><input class="form-control" name="cidade" id="cidade" readonly></div>
                        <div class="form-group col-md-1"><label>UF</label><input class="form-control" name="estado" id="estado" maxlength="2" autocomplete="address-level1" readonly></div>
                    </div>
                    <h2 class="h5 mt-4">Acesso</h2>
                    <div class="form-row">
                        <div class="form-group col-md-6"><label>Senha</label><input type="password" class="form-control" name="senha" id="senha" minlength="8" required></div>
                        <div class="form-group col-md-6"><label>Confirmar senha</label><input type="password" class="form-control" name="confirmar_senha" id="confirmar_senha" minlength="8" required></div>
                    </div><button class="btn btn-primary btn-lg btn-block">Criar minha conta</button>
                </form>
                <div class="text-center mt-4"><a href="login-cliente.php?empresa=<?= rawurlencode((string)$empresa['slug']) ?>">Já tenho conta</a></div>
            </div>
        </div>
    </div>
    <script src="assets/js/consulta-cep.js"></script>
    <script src="assets/js/cadastro-cliente.js"></script>
</body>

</html>