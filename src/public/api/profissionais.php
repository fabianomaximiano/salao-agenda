<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../services/CodigoVerificacaoService.php';
require_once __DIR__ . '/../../services/TokenAtivacaoService.php';
require_once __DIR__ . '/../../services/EmailService.php';

exigirAdministrador();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método não permitido.');
}

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();

$acao = trim((string) ($_POST['acao'] ?? 'criar'));
$csrfRecebido = (string) ($_POST['csrf_token'] ?? '');

$csrfSessao = $acao === 'alternar_status'
    ? (string) ($_SESSION['csrf_profissionais'] ?? '')
    : (string) ($_SESSION['csrf_cadastro_profissional'] ?? '');

if ($csrfSessao === '' || $csrfRecebido === '' || !hash_equals($csrfSessao, $csrfRecebido)) {
    http_response_code(403);
    exit('Token CSRF inválido.');
}

function redirecionarCadastroProfissional(?int $profissionalId = null): never
{
    $destino = '../cadastro-profissional.php';

    if ($profissionalId !== null && $profissionalId > 0) {
        $destino .= '?editar=' . $profissionalId;
    }

    header('Location: ' . $destino);
    exit;
}

function redirecionarListaProfissionais(): never
{
    header('Location: ../profissionais.php');
    exit;
}

function flashProfissional(string $tipo, string $mensagem, array $erros = [], array $old = []): void
{
    $_SESSION['flash_profissional'] = [
        'tipo' => $tipo,
        'mensagem' => $mensagem,
        'erros' => $erros,
        'old' => $old,
    ];
}

function flashListaProfissionais(string $tipo, string $mensagem): void
{
    $_SESSION['flash_lista_profissionais'] = [
        'tipo' => $tipo,
        'mensagem' => $mensagem,
    ];
}

function normalizarPrecoProfissional(string $valor): ?string
{
    $valor = trim($valor);

    if ($valor === '') {
        return null;
    }

    $valor = preg_replace('/[^\d,.\-]/u', '', $valor) ?? '';

    if ($valor === '' || $valor === '-') {
        return null;
    }

    if (str_contains($valor, ',') && str_contains($valor, '.')) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    } elseif (str_contains($valor, ',')) {
        $valor = str_replace(',', '.', $valor);
    }

    if (!is_numeric($valor)) {
        return null;
    }

    $numero = (float) $valor;

    if ($numero < 0 || $numero > 99999999.99) {
        return null;
    }

    return number_format($numero, 2, '.', '');
}

function cpfValido(string $cpf): bool
{
    $cpf = preg_replace('/\D+/', '', $cpf) ?? '';

    if ($cpf === '') {
        return true;
    }

    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += (int) $cpf[$i] * (($t + 1) - $i);
        }

        $digito = ((10 * $soma) % 11) % 10;

        if ((int) $cpf[$t] !== $digito) {
            return false;
        }
    }

    return true;
}

function formatarCpf(string $cpf): ?string
{
    $digitos = preg_replace('/\D+/', '', $cpf) ?? '';

    if ($digitos === '') {
        return null;
    }

    return substr($digitos, 0, 3) . '.' .
        substr($digitos, 3, 3) . '.' .
        substr($digitos, 6, 3) . '-' .
        substr($digitos, 9, 2);
}

if ($acao === 'alternar_status') {
    $profissionalId = filter_input(INPUT_POST, 'profissional_id', FILTER_VALIDATE_INT);

    if (!$profissionalId) {
        flashListaProfissionais('danger', 'Profissional inválido.');
        redirecionarListaProfissionais();
    }

    $stmt = $pdo->prepare(
        'SELECT id, pessoa_id, ativo
         FROM profissionais
         WHERE id = :id
           AND empresa_id = :empresa_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $profissionalId,
        ':empresa_id' => $empresaId,
    ]);
    $profissional = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profissional) {
        flashListaProfissionais('danger', 'Profissional não encontrado.');
        redirecionarListaProfissionais();
    }

    $novoStatus = (int) $profissional['ativo'] === 1 ? 0 : 1;

    $pdo->beginTransaction();

    try {
        $stmtUpdate = $pdo->prepare(
            'UPDATE profissionais
             SET ativo = :ativo
             WHERE id = :id
               AND empresa_id = :empresa_id'
        );
        $stmtUpdate->execute([
            ':ativo' => $novoStatus,
            ':id' => $profissionalId,
            ':empresa_id' => $empresaId,
        ]);

        $stmtPessoa = $pdo->prepare(
            'UPDATE pessoas
             SET ativo = :ativo
             WHERE id = :pessoa_id
               AND empresa_id = :empresa_id'
        );
        $stmtPessoa->execute([
            ':ativo' => $novoStatus,
            ':pessoa_id' => (int) $profissional['pessoa_id'],
            ':empresa_id' => $empresaId,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $_SESSION['csrf_profissionais'] = bin2hex(random_bytes(32));

    flashListaProfissionais(
        'success',
        $novoStatus === 1
            ? 'Profissional ativado com sucesso.'
            : 'Profissional desativado com sucesso.'
    );

    redirecionarListaProfissionais();
}


if ($acao === 'liberar_acesso') {
    $profissionalId = filter_input(INPUT_POST, 'profissional_id', FILTER_VALIDATE_INT);

    if (!$profissionalId) {
        flashListaProfissionais('danger', 'Profissional inválido.');
        redirecionarListaProfissionais();
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT
                pr.id,
                pr.usuario_id,
                pr.ativo AS profissional_ativo,
                pr.pessoa_id,
                p.nome_completo,
                p.email,
                p.ativo AS pessoa_ativa,
                e.nome_fantasia,
                e.ativo AS empresa_ativa
             FROM profissionais pr
             INNER JOIN pessoas p
               ON p.id = pr.pessoa_id
              AND p.empresa_id = pr.empresa_id
             INNER JOIN empresas e
               ON e.id = pr.empresa_id
             WHERE pr.id = :id
               AND pr.empresa_id = :empresa_id
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([
            ':id' => $profissionalId,
            ':empresa_id' => $empresaId,
        ]);
        $profissional = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$profissional) {
            throw new RuntimeException('Profissional não encontrado.');
        }

        if (
            (int) $profissional['profissional_ativo'] !== 1
            || (int) $profissional['pessoa_ativa'] !== 1
            || (int) $profissional['empresa_ativa'] !== 1
        ) {
            throw new RuntimeException(
                'O profissional e a empresa precisam estar ativos para liberar o acesso.'
            );
        }

        $emailAcesso = mb_strtolower(trim((string) ($profissional['email'] ?? '')));

        if ($emailAcesso === '' || !filter_var($emailAcesso, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(
                'Informe um e-mail corporativo válido antes de liberar o acesso.'
            );
        }

        $usuarioId = (int) ($profissional['usuario_id'] ?? 0);
        $usuarioCriadoAgora = false;

        if ($usuarioId <= 0) {
            $stmtEmail = $pdo->prepare(
                'SELECT id
                 FROM usuarios
                 WHERE email = :email
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmtEmail->execute([':email' => $emailAcesso]);

            if ($stmtEmail->fetchColumn()) {
                throw new RuntimeException(
                    'Este e-mail já está vinculado a outra conta de acesso.'
                );
            }

            $stmtUsuario = $pdo->prepare(
                'INSERT INTO usuarios (
                    email,
                    senha_hash,
                    google_id,
                    foto_url,
                    ativo
                 ) VALUES (
                    :email,
                    NULL,
                    NULL,
                    NULL,
                    0
                 )'
            );
            $stmtUsuario->execute([':email' => $emailAcesso]);
            $usuarioId = (int) $pdo->lastInsertId();
            $usuarioCriadoAgora = true;

            $stmtVincular = $pdo->prepare(
                'UPDATE profissionais
                 SET usuario_id = :usuario_id
                 WHERE id = :profissional_id
                   AND empresa_id = :empresa_id
                   AND usuario_id IS NULL'
            );
            $stmtVincular->execute([
                ':usuario_id' => $usuarioId,
                ':profissional_id' => $profissionalId,
                ':empresa_id' => $empresaId,
            ]);

            if ($stmtVincular->rowCount() !== 1) {
                throw new RuntimeException(
                    'Não foi possível vincular a conta ao profissional.'
                );
            }
        } else {
            $stmtUsuario = $pdo->prepare(
                'SELECT id, email, senha_hash, ativo
                 FROM usuarios
                 WHERE id = :usuario_id
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmtUsuario->execute([':usuario_id' => $usuarioId]);
            $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                throw new RuntimeException('A conta vinculada ao profissional não foi encontrada.');
            }

            if (mb_strtolower((string) $usuario['email']) !== $emailAcesso) {
                throw new RuntimeException(
                    'O e-mail corporativo foi alterado após a criação da conta de acesso. '
                    . 'Ajuste o acesso antes de reenviar o convite.'
                );
            }

            if ((int) $usuario['ativo'] === 1 && !empty($usuario['senha_hash'])) {
                throw new RuntimeException('O acesso deste profissional já está ativo.');
            }
        }

        $pdo->commit();

        $codigoService = new CodigoVerificacaoService();
        $codigo = $usuarioCriadoAgora
            ? $codigoService->gerar($pdo, $usuarioId)
            : $codigoService->reenviar($pdo, $usuarioId);

        $tokenService = new TokenAtivacaoService();
        $tokenReferencia = $tokenService->gerar($pdo, $usuarioId);

        $appUrl = rtrim(
            (string) (getenv('APP_URL') ?: 'http://localhost:8096'),
            '/'
        );

        $linkConfirmacao = $appUrl
            . '/confirmar-codigo.php?token='
            . urlencode($tokenReferencia);

        $nome = (string) $profissional['nome_completo'];
        $empresa = (string) $profissional['nome_fantasia'];
        $validadeMinutos = 10;

        ob_start();
        require __DIR__ . '/../../templates/emails/acesso-profissional-codigo.php';
        $html = (string) ob_get_clean();

        $emailService = new EmailService();
        $emailService->enviar(
            $emailAcesso,
            $nome,
            'Ative seu acesso profissional - Salão Agenda',
            $html,
            "Olá, {$nome}.\n\n"
            . "A empresa {$empresa} liberou seu acesso profissional.\n\n"
            . "Código de verificação: {$codigo}\n\n"
            . "Abra este link para confirmar seu e-mail:\n{$linkConfirmacao}\n\n"
            . "O código expira em 10 minutos."
        );

        $_SESSION['csrf_cadastro_profissional'] = bin2hex(random_bytes(32));

        flashProfissional(
            'success',
            $usuarioCriadoAgora
                ? 'Acesso liberado. Enviamos o convite para o e-mail corporativo do profissional.'
                : 'Novo convite de acesso enviado para o profissional.'
        );

        redirecionarCadastroProfissional($profissionalId);
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        flashProfissional('danger', $e->getMessage());
        redirecionarCadastroProfissional($profissionalId ?: null);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Erro ao liberar acesso do profissional id=' . (int) $profissionalId
            . ': ' . $e->getMessage()
        );

        flashProfissional(
            'danger',
            'Não foi possível liberar o acesso agora. Tente novamente.'
        );
        redirecionarCadastroProfissional($profissionalId ?: null);
    }
}

if (!in_array($acao, ['criar', 'atualizar'], true)) {
    http_response_code(400);
    exit('Ação inválida.');
}

$profissionalId = null;
$profissionalAtual = null;

if ($acao === 'atualizar') {
    $profissionalId = filter_input(INPUT_POST, 'profissional_id', FILTER_VALIDATE_INT);

    if (!$profissionalId) {
        flashListaProfissionais('danger', 'Profissional inválido.');
        redirecionarListaProfissionais();
    }

    $stmt = $pdo->prepare(
        'SELECT id, pessoa_id, usuario_id
         FROM profissionais
         WHERE id = :id
           AND empresa_id = :empresa_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $profissionalId,
        ':empresa_id' => $empresaId,
    ]);
    $profissionalAtual = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profissionalAtual) {
        flashListaProfissionais('danger', 'Profissional não encontrado.');
        redirecionarListaProfissionais();
    }
}

$nomeCompleto = trim((string) ($_POST['nome_completo'] ?? ''));
$cpfRaw = trim((string) ($_POST['cpf'] ?? ''));
$dataNascimento = trim((string) ($_POST['data_nascimento'] ?? ''));
$genero = trim((string) ($_POST['genero'] ?? 'nao_informado'));
$email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
$telefone = trim((string) ($_POST['telefone'] ?? ''));
$whatsapp = isset($_POST['whatsapp']) ? 1 : 0;
$cargo = trim((string) ($_POST['cargo'] ?? ''));
$descricao = trim((string) ($_POST['descricao'] ?? ''));
$ativo = isset($_POST['ativo']) ? 1 : 0;
$servicosRecebidos = is_array($_POST['servicos'] ?? null) ? $_POST['servicos'] : [];

$old = [
    'nome_completo' => $nomeCompleto,
    'cpf' => $cpfRaw,
    'data_nascimento' => $dataNascimento,
    'genero' => $genero,
    'email' => $email,
    'telefone' => $telefone,
    'whatsapp' => $whatsapp,
    'cargo' => $cargo,
    'descricao' => $descricao,
    'ativo' => $ativo,
    'servicos' => $servicosRecebidos,
];

$erros = [];

if ($nomeCompleto === '') {
    $erros['nome_completo'] = 'Informe o nome completo.';
} elseif (mb_strlen($nomeCompleto) > 160) {
    $erros['nome_completo'] = 'O nome deve ter no máximo 160 caracteres.';
}

if ($cpfRaw !== '' && !cpfValido($cpfRaw)) {
    $erros['cpf'] = 'Informe um CPF válido.';
}
$cpf = formatarCpf($cpfRaw);

if ($dataNascimento !== '') {
    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $dataNascimento);
    $dataValida = $data && $data->format('Y-m-d') === $dataNascimento;

    if (!$dataValida) {
        $erros['data_nascimento'] = 'Informe uma data válida.';
    } elseif ($data > new DateTimeImmutable('today')) {
        $erros['data_nascimento'] = 'A data de nascimento não pode estar no futuro.';
    }
}

$generosValidos = ['masculino', 'feminino', 'nao_binario', 'nao_informado'];
if (!in_array($genero, $generosValidos, true)) {
    $erros['genero'] = 'Gênero inválido.';
}

if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190)) {
    $erros['email'] = 'Informe um e-mail válido.';
}

if (
    $acao === 'atualizar'
    && !empty($profissionalAtual['usuario_id'])
    && !isset($erros['email'])
) {
    $stmtEmailAcesso = $pdo->prepare(
        'SELECT email
         FROM usuarios
         WHERE id = :usuario_id
         LIMIT 1'
    );
    $stmtEmailAcesso->execute([
        ':usuario_id' => (int) $profissionalAtual['usuario_id'],
    ]);
    $emailAcessoAtual = $stmtEmailAcesso->fetchColumn();

    if (
        $emailAcessoAtual === false
        || mb_strtolower((string) $emailAcessoAtual) !== $email
    ) {
        $erros['email'] =
            'O e-mail corporativo está vinculado à conta de acesso e não pode ser alterado por este formulário.';
    }
}

if (mb_strlen($telefone) > 30) {
    $erros['telefone'] = 'O telefone deve ter no máximo 30 caracteres.';
}

if ($telefone === '' && $whatsapp === 1) {
    $erros['telefone'] = 'Informe o telefone antes de marcar WhatsApp.';
}

if (mb_strlen($cargo) > 120) {
    $erros['cargo'] = 'O cargo deve ter no máximo 120 caracteres.';
}

if (mb_strlen($descricao) > 3000) {
    $erros['descricao'] = 'A descrição deve ter no máximo 3000 caracteres.';
}

if ($cpf !== null && !isset($erros['cpf'])) {
    $sql = 'SELECT id
            FROM pessoas
            WHERE empresa_id = :empresa_id
              AND cpf = :cpf';
    $params = [
        ':empresa_id' => $empresaId,
        ':cpf' => $cpf,
    ];

    if ($acao === 'atualizar') {
        $sql .= ' AND id <> :pessoa_id';
        $params[':pessoa_id'] = (int) $profissionalAtual['pessoa_id'];
    }

    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->fetchColumn()) {
        $erros['cpf'] = 'Já existe uma pessoa com este CPF nesta empresa.';
    }
}

$servicosNormalizados = [];

foreach ($servicosRecebidos as $servicoIdRaw => $dados) {
    $servicoId = filter_var($servicoIdRaw, FILTER_VALIDATE_INT);

    if ($servicoId === false || $servicoId <= 0 || !is_array($dados) || empty($dados['selecionado'])) {
        continue;
    }

    $duracaoRaw = trim((string) ($dados['duracao_minutos'] ?? ''));
    $precoRaw = trim((string) ($dados['preco'] ?? ''));

    $duracao = null;
    if ($duracaoRaw !== '') {
        $duracaoValidada = filter_var($duracaoRaw, FILTER_VALIDATE_INT);

        if ($duracaoValidada === false || $duracaoValidada < 5 || $duracaoValidada > 1440) {
            $erros['servicos'] = 'Revise as durações personalizadas dos serviços.';
            continue;
        }

        $duracao = (int) $duracaoValidada;
    }

    $preco = null;
    if ($precoRaw !== '') {
        $preco = normalizarPrecoProfissional($precoRaw);

        if ($preco === null) {
            $erros['servicos'] = 'Revise os preços personalizados dos serviços.';
            continue;
        }
    }

    $servicosNormalizados[(int) $servicoId] = [
        'duracao_minutos' => $duracao,
        'preco' => $preco,
    ];
}

if (!$servicosNormalizados) {
    $erros['servicos'] = 'Selecione pelo menos um serviço executado pelo profissional.';
}

if ($servicosNormalizados) {
    $ids = array_keys($servicosNormalizados);
    $placeholders = [];
    $params = [':empresa_id' => $empresaId];

    foreach ($ids as $indice => $id) {
        $chave = ':servico_' . $indice;
        $placeholders[] = $chave;
        $params[$chave] = $id;
    }

    $sql =
        'SELECT id, ativo
         FROM servicos
         WHERE empresa_id = :empresa_id
           AND id IN (' . implode(', ', $placeholders) . ')';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $servicosEmpresa = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $encontrados = [];
    foreach ($servicosEmpresa as $servicoEmpresa) {
        $encontrados[(int) $servicoEmpresa['id']] = (int) $servicoEmpresa['ativo'];
    }

    foreach ($ids as $id) {
        if (!array_key_exists($id, $encontrados)) {
            $erros['servicos'] = 'Um dos serviços selecionados não pertence a esta empresa.';
            break;
        }

        if ($encontrados[$id] !== 1) {
            $jaVinculado = false;

            if ($acao === 'atualizar') {
                $stmtVinculo = $pdo->prepare(
                    'SELECT 1
                     FROM profissional_servicos
                     WHERE profissional_id = :profissional_id
                       AND servico_id = :servico_id
                     LIMIT 1'
                );
                $stmtVinculo->execute([
                    ':profissional_id' => $profissionalId,
                    ':servico_id' => $id,
                ]);
                $jaVinculado = (bool) $stmtVinculo->fetchColumn();
            }

            if (!$jaVinculado) {
                $erros['servicos'] = 'Não é possível vincular um serviço inativo.';
                break;
            }
        }
    }
}

if ($erros) {
    flashProfissional('danger', 'Revise os campos destacados.', $erros, $old);
    redirecionarCadastroProfissional($acao === 'atualizar' ? $profissionalId : null);
}

$pdo->beginTransaction();

try {
    if ($acao === 'criar') {
        $stmtPessoa = $pdo->prepare(
            'INSERT INTO pessoas (
                empresa_id,
                nome_completo,
                cpf,
                data_nascimento,
                genero,
                email,
                observacoes,
                ativo
             ) VALUES (
                :empresa_id,
                :nome_completo,
                :cpf,
                :data_nascimento,
                :genero,
                :email,
                NULL,
                :ativo
             )'
        );
        $stmtPessoa->execute([
            ':empresa_id' => $empresaId,
            ':nome_completo' => $nomeCompleto,
            ':cpf' => $cpf,
            ':data_nascimento' => $dataNascimento !== '' ? $dataNascimento : null,
            ':genero' => $genero,
            ':email' => $email !== '' ? $email : null,
            ':ativo' => $ativo,
        ]);

        $pessoaId = (int) $pdo->lastInsertId();

        $stmtProfissional = $pdo->prepare(
            'INSERT INTO profissionais (
                empresa_id,
                pessoa_id,
                usuario_id,
                cargo,
                descricao,
                ativo
             ) VALUES (
                :empresa_id,
                :pessoa_id,
                NULL,
                :cargo,
                :descricao,
                :ativo
             )'
        );
        $stmtProfissional->execute([
            ':empresa_id' => $empresaId,
            ':pessoa_id' => $pessoaId,
            ':cargo' => $cargo !== '' ? $cargo : null,
            ':descricao' => $descricao !== '' ? $descricao : null,
            ':ativo' => $ativo,
        ]);

        $profissionalId = (int) $pdo->lastInsertId();
    } else {
        $pessoaId = (int) $profissionalAtual['pessoa_id'];

        $stmtPessoa = $pdo->prepare(
            'UPDATE pessoas
             SET nome_completo = :nome_completo,
                 cpf = :cpf,
                 data_nascimento = :data_nascimento,
                 genero = :genero,
                 email = :email,
                 ativo = :ativo
             WHERE id = :id
               AND empresa_id = :empresa_id'
        );
        $stmtPessoa->execute([
            ':nome_completo' => $nomeCompleto,
            ':cpf' => $cpf,
            ':data_nascimento' => $dataNascimento !== '' ? $dataNascimento : null,
            ':genero' => $genero,
            ':email' => $email !== '' ? $email : null,
            ':ativo' => $ativo,
            ':id' => $pessoaId,
            ':empresa_id' => $empresaId,
        ]);

        $stmtProfissional = $pdo->prepare(
            'UPDATE profissionais
             SET cargo = :cargo,
                 descricao = :descricao,
                 ativo = :ativo
             WHERE id = :id
               AND empresa_id = :empresa_id'
        );
        $stmtProfissional->execute([
            ':cargo' => $cargo !== '' ? $cargo : null,
            ':descricao' => $descricao !== '' ? $descricao : null,
            ':ativo' => $ativo,
            ':id' => $profissionalId,
            ':empresa_id' => $empresaId,
        ]);
    }

    $stmtTelefoneExistente = $pdo->prepare(
        'SELECT id
         FROM telefones_pessoa
         WHERE pessoa_id = :pessoa_id
         ORDER BY principal DESC, id ASC
         LIMIT 1'
    );
    $stmtTelefoneExistente->execute([':pessoa_id' => $pessoaId]);
    $telefoneId = $stmtTelefoneExistente->fetchColumn();

    if ($telefone !== '') {
        if ($telefoneId) {
            $stmtTelefone = $pdo->prepare(
                'UPDATE telefones_pessoa
                 SET numero = :numero,
                     tipo = "celular",
                     whatsapp = :whatsapp,
                     principal = 1
                 WHERE id = :id
                   AND pessoa_id = :pessoa_id'
            );
            $stmtTelefone->execute([
                ':numero' => $telefone,
                ':whatsapp' => $whatsapp,
                ':id' => (int) $telefoneId,
                ':pessoa_id' => $pessoaId,
            ]);
        } else {
            $stmtTelefone = $pdo->prepare(
                'INSERT INTO telefones_pessoa (
                    pessoa_id,
                    numero,
                    tipo,
                    whatsapp,
                    principal
                 ) VALUES (
                    :pessoa_id,
                    :numero,
                    "celular",
                    :whatsapp,
                    1
                 )'
            );
            $stmtTelefone->execute([
                ':pessoa_id' => $pessoaId,
                ':numero' => $telefone,
                ':whatsapp' => $whatsapp,
            ]);
        }
    } elseif ($telefoneId) {
        $stmtTelefone = $pdo->prepare(
            'DELETE FROM telefones_pessoa
             WHERE id = :id
               AND pessoa_id = :pessoa_id'
        );
        $stmtTelefone->execute([
            ':id' => (int) $telefoneId,
            ':pessoa_id' => $pessoaId,
        ]);
    }

    $stmtDesativar = $pdo->prepare(
        'UPDATE profissional_servicos
         SET ativo = 0
         WHERE profissional_id = :profissional_id'
    );
    $stmtDesativar->execute([':profissional_id' => $profissionalId]);

    $stmtVincular = $pdo->prepare(
        'INSERT INTO profissional_servicos (
            profissional_id,
            servico_id,
            duracao_minutos,
            preco,
            ativo
         ) VALUES (
            :profissional_id,
            :servico_id,
            :duracao_minutos,
            :preco,
            1
         )
         ON DUPLICATE KEY UPDATE
            duracao_minutos = VALUES(duracao_minutos),
            preco = VALUES(preco),
            ativo = 1'
    );

    foreach ($servicosNormalizados as $servicoId => $config) {
        $stmtVincular->execute([
            ':profissional_id' => $profissionalId,
            ':servico_id' => $servicoId,
            ':duracao_minutos' => $config['duracao_minutos'],
            ':preco' => $config['preco'],
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e instanceof PDOException && $e->getCode() === '23000') {
        flashProfissional(
            'danger',
            'Não foi possível salvar porque já existe um cadastro com um dado exclusivo informado.',
            [],
            $old
        );
        redirecionarCadastroProfissional($acao === 'atualizar' ? $profissionalId : null);
    }

    throw $e;
}

$_SESSION['csrf_cadastro_profissional'] = bin2hex(random_bytes(32));

if ($acao === 'criar') {
    flashListaProfissionais('success', 'Profissional cadastrado com sucesso.');
} else {
    flashListaProfissionais('success', 'Profissional atualizado com sucesso.');
}

redirecionarListaProfissionais();
