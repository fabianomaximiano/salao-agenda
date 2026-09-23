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
    exit('Método não permitido.');
}

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();
$acao = trim((string) ($_POST['acao'] ?? 'criar'));
$csrf = (string) ($_POST['csrf_token'] ?? '');

$csrfSessao = $acao === 'alternar_status'
    ? (string) ($_SESSION['csrf_colaboradores'] ?? '')
    : (string) ($_SESSION['csrf_cadastro_colaborador'] ?? '');

if ($csrf === '' || $csrfSessao === '' || !hash_equals($csrfSessao, $csrf)) {
    http_response_code(403);
    exit('Token CSRF inválido.');
}

function irListaColaboradores(string $tipo, string $mensagem): never
{
    $_SESSION['flash_lista_colaboradores'] = [
        'tipo' => $tipo,
        'mensagem' => $mensagem,
    ];
    header('Location: ../colaboradores.php');
    exit;
}

function irCadastroColaborador(
    string $tipo,
    string $mensagem,
    ?int $id = null,
    array $erros = [],
    array $old = []
): never {
    $_SESSION['flash_colaborador'] = [
        'tipo' => $tipo,
        'mensagem' => $mensagem,
        'erros' => $erros,
        'old' => $old,
    ];

    $url = '../cadastro-colaborador.php' . ($id ? '?editar=' . $id : '');
    header('Location: ' . $url);
    exit;
}

function somenteDigitosColaborador(string $valor): string
{
    return preg_replace('/\D+/', '', $valor) ?? '';
}

function cpfValidoColaborador(string $cpf): bool
{
    $cpf = somenteDigitosColaborador($cpf);

    if ($cpf === '') {
        return true;
    }

    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    for ($tamanho = 9; $tamanho < 11; $tamanho++) {
        $soma = 0;

        for ($i = 0; $i < $tamanho; $i++) {
            $soma += (int) $cpf[$i] * (($tamanho + 1) - $i);
        }

        if ((int) $cpf[$tamanho] !== ((10 * $soma) % 11) % 10) {
            return false;
        }
    }

    return true;
}

function formatarCpfColaborador(string $cpf): ?string
{
    $digitos = somenteDigitosColaborador($cpf);

    if ($digitos === '') {
        return null;
    }

    return substr($digitos, 0, 3) . '.'
        . substr($digitos, 3, 3) . '.'
        . substr($digitos, 6, 3) . '-'
        . substr($digitos, 9, 2);
}

function dataValidaColaborador(string $data): bool
{
    if ($data === '') {
        return true;
    }

    $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
    $erros = DateTimeImmutable::getLastErrors();

    if (!$objeto || ($erros !== false && ($erros['warning_count'] > 0 || $erros['error_count'] > 0))) {
        return false;
    }

    return $objeto->format('Y-m-d') === $data && $objeto <= new DateTimeImmutable('today');
}

function cepValidoColaborador(string $cep): bool
{
    if ($cep === '') {
        return true;
    }

    return strlen(somenteDigitosColaborador($cep)) === 8;
}

if ($acao === 'alternar_status') {
    $id = filter_input(INPUT_POST, 'colaborador_id', FILTER_VALIDATE_INT);

    if (!$id) {
        irListaColaboradores('danger', 'Colaborador inválido.');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT c.id, c.ativo, c.pessoa_id
               FROM colaboradores c
              WHERE c.id = :id
                AND c.empresa_id = :empresa_id
              LIMIT 1
              FOR UPDATE'
        );
        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);
        $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$colaborador) {
            throw new RuntimeException('Colaborador não encontrado.');
        }

        $novoStatus = (int) $colaborador['ativo'] === 1 ? 0 : 1;

        $stmt = $pdo->prepare(
            'UPDATE colaboradores
                SET ativo = :ativo
              WHERE id = :id
                AND empresa_id = :empresa_id'
        );
        $stmt->execute([
            ':ativo' => $novoStatus,
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);

        $stmt = $pdo->prepare(
            'UPDATE pessoas
                SET ativo = :ativo
              WHERE id = :pessoa_id
                AND empresa_id = :empresa_id'
        );
        $stmt->execute([
            ':ativo' => $novoStatus,
            ':pessoa_id' => (int) $colaborador['pessoa_id'],
            ':empresa_id' => $empresaId,
        ]);

        $pdo->commit();
        $_SESSION['csrf_colaboradores'] = bin2hex(random_bytes(32));

        irListaColaboradores(
            'success',
            $novoStatus === 1
                ? 'Colaborador ativado com sucesso.'
                : 'Colaborador desativado com sucesso.'
        );
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Erro ao alterar status do colaborador: ' . $e->getMessage());
        irListaColaboradores('danger', 'Não foi possível alterar o status do colaborador.');
    }
}

if ($acao === 'liberar_acesso') {
    $id = filter_input(INPUT_POST, 'colaborador_id', FILTER_VALIDATE_INT);

    if (!$id) {
        irListaColaboradores('danger', 'Colaborador inválido.');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT c.id, c.usuario_id, c.ativo AS colaborador_ativo,
                    c.pessoa_id, p.nome_completo, p.email, p.ativo AS pessoa_ativa,
                    e.nome_fantasia, e.ativo AS empresa_ativa
               FROM colaboradores c
               INNER JOIN pessoas p
                       ON p.id = c.pessoa_id
                      AND p.empresa_id = c.empresa_id
               INNER JOIN empresas e ON e.id = c.empresa_id
              WHERE c.id = :id
                AND c.empresa_id = :empresa_id
              LIMIT 1
              FOR UPDATE'
        );
        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);
        $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$colaborador) {
            throw new RuntimeException('Colaborador não encontrado.');
        }

        if (
            (int) $colaborador['colaborador_ativo'] !== 1
            || (int) $colaborador['pessoa_ativa'] !== 1
            || (int) $colaborador['empresa_ativa'] !== 1
        ) {
            throw new RuntimeException('O colaborador e a empresa precisam estar ativos para liberar o acesso.');
        }

        $email = mb_strtolower(trim((string) $colaborador['email']));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Informe um e-mail corporativo válido antes de liberar o acesso.');
        }

        $usuarioId = (int) ($colaborador['usuario_id'] ?? 0);
        $novoUsuario = false;

        if ($usuarioId <= 0) {
            $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1 FOR UPDATE');
            $stmt->execute([':email' => $email]);

            if ($stmt->fetchColumn()) {
                throw new RuntimeException('Este e-mail já está vinculado a outra conta de acesso.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO usuarios (email, senha_hash, google_id, foto_url, ativo)
                 VALUES (:email, NULL, NULL, NULL, 0)'
            );
            $stmt->execute([':email' => $email]);
            $usuarioId = (int) $pdo->lastInsertId();
            $novoUsuario = true;

            $stmt = $pdo->prepare(
                'UPDATE colaboradores
                    SET usuario_id = :usuario_id
                  WHERE id = :id
                    AND empresa_id = :empresa_id'
            );
            $stmt->execute([
                ':usuario_id' => $usuarioId,
                ':id' => $id,
                ':empresa_id' => $empresaId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT email, senha_hash, ativo
                   FROM usuarios
                  WHERE id = :id
                  LIMIT 1
                  FOR UPDATE'
            );
            $stmt->execute([':id' => $usuarioId]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                throw new RuntimeException('A conta vinculada não foi encontrada.');
            }

            if (mb_strtolower((string) $usuario['email']) !== $email) {
                throw new RuntimeException('O e-mail corporativo está vinculado à conta de acesso e não pode ser alterado.');
            }

            if ((int) $usuario['ativo'] === 1 && !empty($usuario['senha_hash'])) {
                throw new RuntimeException('O acesso deste colaborador já está ativo.');
            }
        }

        $pdo->commit();

        $codigoService = new CodigoVerificacaoService();
        $codigo = $novoUsuario
            ? $codigoService->gerar($pdo, $usuarioId)
            : $codigoService->reenviar($pdo, $usuarioId);

        $tokenService = new TokenAtivacaoService();
        $tokenReferencia = $tokenService->gerar($pdo, $usuarioId);

        $appUrl = rtrim((string) (getenv('APP_URL') ?: 'http://localhost:8096'), '/');
        $linkConfirmacao = $appUrl . '/confirmar-codigo.php?token=' . urlencode($tokenReferencia);

        $nome = (string) $colaborador['nome_completo'];
        $empresa = (string) $colaborador['nome_fantasia'];
        $validadeMinutos = 10;

        ob_start();
        require __DIR__ . '/../../templates/emails/acesso-colaborador-codigo.php';
        $html = (string) ob_get_clean();

        (new EmailService())->enviar(
            $email,
            $nome,
            'Ative seu acesso de colaborador - Salão Agenda',
            $html,
            "Olá, {$nome}.\n\nA empresa {$empresa} liberou seu acesso de colaborador.\n\n"
            . "Código: {$codigo}\n\n{$linkConfirmacao}\n\nO código expira em 10 minutos."
        );

        $_SESSION['csrf_cadastro_colaborador'] = bin2hex(random_bytes(32));

        irCadastroColaborador(
            'success',
            $novoUsuario
                ? 'Acesso liberado. Enviamos o convite para o colaborador.'
                : 'Novo convite enviado para o colaborador.',
            $id
        );
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        irCadastroColaborador('danger', $e->getMessage(), $id);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Erro colaborador acesso: ' . $e->getMessage());
        irCadastroColaborador('danger', 'Não foi possível liberar o acesso agora.', $id);
    }
}

if (!in_array($acao, ['criar', 'atualizar'], true)) {
    http_response_code(400);
    exit('Ação inválida.');
}

$id = null;
$atual = null;

if ($acao === 'atualizar') {
    $id = filter_input(INPUT_POST, 'colaborador_id', FILTER_VALIDATE_INT);

    if (!$id) {
        irListaColaboradores('danger', 'Colaborador inválido.');
    }

    $stmt = $pdo->prepare(
        'SELECT c.id, c.pessoa_id, c.usuario_id, p.cpf AS cpf_atual
           FROM colaboradores c
           INNER JOIN pessoas p
                   ON p.id = c.pessoa_id
                  AND p.empresa_id = c.empresa_id
          WHERE c.id = :id
            AND c.empresa_id = :empresa_id
          LIMIT 1'
    );
    $stmt->execute([
        ':id' => $id,
        ':empresa_id' => $empresaId,
    ]);
    $atual = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$atual) {
        irListaColaboradores('danger', 'Colaborador não encontrado.');
    }
}

$nome = trim((string) ($_POST['nome_completo'] ?? ''));
$cpfRaw = $acao === 'atualizar'
    ? (string) ($atual['cpf_atual'] ?? '')
    : trim((string) ($_POST['cpf'] ?? ''));
$dataNascimento = trim((string) ($_POST['data_nascimento'] ?? ''));
$genero = trim((string) ($_POST['genero'] ?? 'nao_informado'));
$email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
$telefone = trim((string) ($_POST['telefone'] ?? ''));
$whatsapp = isset($_POST['whatsapp']) ? 1 : 0;
$cargo = trim((string) ($_POST['cargo'] ?? ''));
$ativo = isset($_POST['ativo']) ? 1 : 0;

$cep = trim((string) ($_POST['cep'] ?? ''));
$logradouro = trim((string) ($_POST['logradouro'] ?? ''));
$numero = trim((string) ($_POST['numero'] ?? ''));
$complemento = trim((string) ($_POST['complemento'] ?? ''));
$bairro = trim((string) ($_POST['bairro'] ?? ''));
$cidade = trim((string) ($_POST['cidade'] ?? ''));
$estado = mb_strtoupper(trim((string) ($_POST['estado'] ?? '')));

$permissoes = [];
foreach (['agenda', 'clientes', 'profissionais', 'servicos', 'financeiro', 'relatorios', 'configuracoes'] as $permissao) {
    $permissoes['pode_' . $permissao] = isset($_POST['pode_' . $permissao]) ? 1 : 0;
}

$old = array_merge(
    [
        'nome_completo' => $nome,
        'cpf' => $cpfRaw,
        'data_nascimento' => $dataNascimento,
        'genero' => $genero,
        'email' => $email,
        'telefone' => $telefone,
        'whatsapp' => $whatsapp,
        'cargo' => $cargo,
        'ativo' => $ativo,
        'cep' => $cep,
        'logradouro' => $logradouro,
        'numero' => $numero,
        'complemento' => $complemento,
        'bairro' => $bairro,
        'cidade' => $cidade,
        'estado' => $estado,
    ],
    $permissoes
);

$erros = [];

if ($nome === '' || mb_strlen($nome) > 160) {
    $erros['nome_completo'] = 'Informe um nome válido.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    $erros['email'] = 'Informe um e-mail válido.';
}

if (!cpfValidoColaborador($cpfRaw)) {
    $erros['cpf'] = 'Informe um CPF válido.';
}

if (!dataValidaColaborador($dataNascimento)) {
    $erros['data_nascimento'] = 'Informe uma data de nascimento válida.';
}

if (!in_array($genero, ['masculino', 'feminino', 'nao_binario', 'nao_informado'], true)) {
    $genero = 'nao_informado';
    $old['genero'] = $genero;
}

if (!cepValidoColaborador($cep)) {
    $erros['cep'] = 'Informe um CEP válido.';
}

if ($estado !== '' && !preg_match('/^[A-Z]{2}$/', $estado)) {
    $erros['estado'] = 'Informe uma UF válida.';
}

$cpf = formatarCpfColaborador($cpfRaw);

if ($acao === 'atualizar' && !empty($atual['usuario_id'])) {
    $stmt = $pdo->prepare('SELECT email FROM usuarios WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $atual['usuario_id']]);
    $emailConta = mb_strtolower((string) $stmt->fetchColumn());

    if ($emailConta !== $email) {
        $erros['email'] = 'O e-mail corporativo está vinculado à conta de acesso e não pode ser alterado.';
    }
}

if ($erros) {
    irCadastroColaborador('danger', 'Revise os campos destacados.', $id, $erros, $old);
}

try {
    $pdo->beginTransaction();

    if ($acao === 'criar') {
        if ($cpf !== null) {
            $stmt = $pdo->prepare(
                'SELECT id
                   FROM pessoas
                  WHERE empresa_id = :empresa_id
                    AND cpf = :cpf
                  LIMIT 1
                  FOR UPDATE'
            );
            $stmt->execute([
                ':empresa_id' => $empresaId,
                ':cpf' => $cpf,
            ]);

            if ($stmt->fetchColumn()) {
                throw new RuntimeException('Já existe uma pessoa cadastrada nesta empresa com este CPF.');
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO pessoas
                (empresa_id, nome_completo, cpf, data_nascimento, genero, email, observacoes, ativo)
             VALUES
                (:empresa_id, :nome, :cpf, :data_nascimento, :genero, :email, NULL, :ativo)'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':nome' => $nome,
            ':cpf' => $cpf,
            ':data_nascimento' => $dataNascimento !== '' ? $dataNascimento : null,
            ':genero' => $genero,
            ':email' => $email,
            ':ativo' => $ativo,
        ]);
        $pessoaId = (int) $pdo->lastInsertId();

        // Colaborador sempre possui uma identidade de acesso vinculada.
        // A conta nasce inativa e sem senha; a ativação continua sendo feita
        // posteriormente pelo fluxo "Liberar acesso".
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1 FOR UPDATE');
        $stmt->execute([':email' => $email]);

        if ($stmt->fetchColumn()) {
            throw new RuntimeException('Este e-mail já está vinculado a outra conta de acesso.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO usuarios (email, senha_hash, google_id, foto_url, ativo)
             VALUES (:email, NULL, NULL, NULL, 0)'
        );
        $stmt->execute([':email' => $email]);
        $usuarioId = (int) $pdo->lastInsertId();

        $camposPermissoes = implode(',', array_keys($permissoes));
        $placeholdersPermissoes = implode(',', array_map(
            static fn(string $campo): string => ':' . $campo,
            array_keys($permissoes)
        ));

        $stmt = $pdo->prepare(
            'INSERT INTO colaboradores
                (empresa_id, pessoa_id, usuario_id, cargo, ' . $camposPermissoes . ', ativo)
             VALUES
                (:empresa_id, :pessoa_id, :usuario_id, :cargo, ' . $placeholdersPermissoes . ', :ativo)'
        );

        $params = [
            ':empresa_id' => $empresaId,
            ':pessoa_id' => $pessoaId,
            ':usuario_id' => $usuarioId,
            ':cargo' => $cargo !== '' ? $cargo : null,
            ':ativo' => $ativo,
        ];

        foreach ($permissoes as $campo => $valor) {
            $params[':' . $campo] = $valor;
        }

        $stmt->execute($params);
    } else {
        $pessoaId = (int) $atual['pessoa_id'];

        $stmt = $pdo->prepare(
            'UPDATE pessoas
                SET nome_completo = :nome,
                    data_nascimento = :data_nascimento,
                    genero = :genero,
                    email = :email,
                    ativo = :ativo
              WHERE id = :id
                AND empresa_id = :empresa_id'
        );
        $stmt->execute([
            ':nome' => $nome,
            ':data_nascimento' => $dataNascimento !== '' ? $dataNascimento : null,
            ':genero' => $genero,
            ':email' => $email,
            ':ativo' => $ativo,
            ':id' => $pessoaId,
            ':empresa_id' => $empresaId,
        ]);

        $setsPermissoes = implode(',', array_map(
            static fn(string $campo): string => $campo . '=:' . $campo,
            array_keys($permissoes)
        ));

        $stmt = $pdo->prepare(
            'UPDATE colaboradores
                SET cargo = :cargo,
                    ' . $setsPermissoes . ',
                    ativo = :ativo
              WHERE id = :id
                AND empresa_id = :empresa_id'
        );

        $params = [
            ':cargo' => $cargo !== '' ? $cargo : null,
            ':ativo' => $ativo,
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ];

        foreach ($permissoes as $campo => $valor) {
            $params[':' . $campo] = $valor;
        }

        $stmt->execute($params);
    }

    $stmt = $pdo->prepare(
        'SELECT id
           FROM telefones_pessoa
          WHERE pessoa_id = :pessoa_id
          ORDER BY principal DESC, id ASC
          LIMIT 1'
    );
    $stmt->execute([':pessoa_id' => $pessoaId]);
    $telefoneId = $stmt->fetchColumn();

    if ($telefone !== '') {
        if ($telefoneId) {
            $stmt = $pdo->prepare(
                'UPDATE telefones_pessoa
                    SET numero = :numero,
                        tipo = "celular",
                        whatsapp = :whatsapp,
                        principal = 1
                  WHERE id = :id'
            );
            $stmt->execute([
                ':numero' => $telefone,
                ':whatsapp' => $whatsapp,
                ':id' => $telefoneId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO telefones_pessoa
                    (pessoa_id, numero, tipo, whatsapp, principal)
                 VALUES
                    (:pessoa_id, :numero, "celular", :whatsapp, 1)'
            );
            $stmt->execute([
                ':pessoa_id' => $pessoaId,
                ':numero' => $telefone,
                ':whatsapp' => $whatsapp,
            ]);
        }
    } elseif ($telefoneId) {
        $stmt = $pdo->prepare('DELETE FROM telefones_pessoa WHERE id = :id');
        $stmt->execute([':id' => $telefoneId]);
    }

    $temEndereco = $cep !== ''
        || $logradouro !== ''
        || $numero !== ''
        || $complemento !== ''
        || $bairro !== ''
        || $cidade !== ''
        || $estado !== '';

    if ($temEndereco) {
        $stmt = $pdo->prepare(
            'INSERT INTO enderecos_pessoa
                (pessoa_id, cep, logradouro, numero, complemento, bairro, cidade, estado)
             VALUES
                (:pessoa_id, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :estado)
             ON DUPLICATE KEY UPDATE
                cep = VALUES(cep),
                logradouro = VALUES(logradouro),
                numero = VALUES(numero),
                complemento = VALUES(complemento),
                bairro = VALUES(bairro),
                cidade = VALUES(cidade),
                estado = VALUES(estado)'
        );
        $stmt->execute([
            ':pessoa_id' => $pessoaId,
            ':cep' => $cep !== '' ? $cep : null,
            ':logradouro' => $logradouro !== '' ? $logradouro : null,
            ':numero' => $numero !== '' ? $numero : null,
            ':complemento' => $complemento !== '' ? $complemento : null,
            ':bairro' => $bairro !== '' ? $bairro : null,
            ':cidade' => $cidade !== '' ? $cidade : null,
            ':estado' => $estado !== '' ? $estado : null,
        ]);
    } else {
        $stmt = $pdo->prepare('DELETE FROM enderecos_pessoa WHERE pessoa_id = :pessoa_id');
        $stmt->execute([':pessoa_id' => $pessoaId]);
    }

    $pdo->commit();
    $_SESSION['csrf_cadastro_colaborador'] = bin2hex(random_bytes(32));

    irListaColaboradores(
        'success',
        $acao === 'criar'
            ? 'Colaborador cadastrado com sucesso.'
            : 'Colaborador atualizado com sucesso.'
    );
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    irCadastroColaborador('danger', $e->getMessage(), $id, [], $old);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Erro ao salvar colaborador: ' . $e->getMessage());
    irCadastroColaborador('danger', 'Não foi possível salvar o colaborador.', $id, [], $old);
}
