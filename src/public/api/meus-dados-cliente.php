<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

exigirCliente();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

$pdo = getDB();

$usuarioId = (int) ($_SESSION['user_id'] ?? 0);
$empresaId = (int) ($_SESSION['empresa_id'] ?? 0);
$clienteId = (int) ($_SESSION['cliente_id'] ?? 0);
$pessoaId = (int) ($_SESSION['pessoa_id'] ?? 0);

$csrfSessao = (string) ($_SESSION['csrf_meus_dados_cliente'] ?? '');
$csrfRecebido = (string) ($_POST['csrf_token'] ?? '');

if (
    $csrfSessao === '' ||
    $csrfRecebido === '' ||
    !hash_equals($csrfSessao, $csrfRecebido)
) {
    http_response_code(419);
    exit('Sessão expirada. Recarregue a página e tente novamente.');
}

function somenteDigitosCliente(string $valor): string
{
    return preg_replace('/\D+/', '', $valor) ?? '';
}

function dataClienteValida(string $data): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $data);

    return $dt instanceof DateTime && $dt->format('Y-m-d') === $data;
}

function flashMeusDadosCliente(
    string $tipo,
    string $mensagem,
    array $erros = [],
    array $old = []
): never {
    $_SESSION['flash_meus_dados_cliente'] = [
        'tipo' => $tipo,
        'mensagem' => $mensagem,
        'erros' => $erros,
        'old' => $old,
    ];

    header('Location: ../meus-dados-cliente.php', true, 303);
    exit;
}

$dados = [
    'nome_completo' => trim((string) ($_POST['nome_completo'] ?? '')),
    'data_nascimento' => trim((string) ($_POST['data_nascimento'] ?? '')),
    'genero' => trim((string) ($_POST['genero'] ?? 'nao_informado')),
    'telefone' => trim((string) ($_POST['telefone'] ?? '')),
    'whatsapp' => isset($_POST['whatsapp']) ? 1 : 0,
    'cep' => trim((string) ($_POST['cep'] ?? '')),
    'logradouro' => trim((string) ($_POST['logradouro'] ?? '')),
    'numero' => trim((string) ($_POST['numero'] ?? '')),
    'complemento' => trim((string) ($_POST['complemento'] ?? '')),
    'bairro' => trim((string) ($_POST['bairro'] ?? '')),
    'cidade' => trim((string) ($_POST['cidade'] ?? '')),
    'estado' => mb_strtoupper(trim((string) ($_POST['estado'] ?? ''))),
];

$erros = [];

if ($dados['nome_completo'] === '') {
    $erros['nome_completo'] = 'Informe seu nome completo.';
} elseif (mb_strlen($dados['nome_completo']) > 160) {
    $erros['nome_completo'] = 'O nome informado é muito longo.';
}

if (
    $dados['data_nascimento'] === '' ||
    !dataClienteValida($dados['data_nascimento'])
) {
    $erros['data_nascimento'] = 'Informe uma data de nascimento válida.';
}

$generos = [
    'masculino',
    'feminino',
    'nao_binario',
    'nao_informado',
];

if (!in_array($dados['genero'], $generos, true)) {
    $erros['genero'] = 'Gênero inválido.';
}

$telefoneNumerico = somenteDigitosCliente($dados['telefone']);

if (strlen($telefoneNumerico) < 10 || strlen($telefoneNumerico) > 11) {
    $erros['telefone'] = 'Informe um telefone ou celular válido.';
}

$cepNumerico = somenteDigitosCliente($dados['cep']);

if ($dados['cep'] !== '' && strlen($cepNumerico) !== 8) {
    $erros['cep'] = 'Informe um CEP válido.';
}

if ($dados['estado'] !== '' && !preg_match('/^[A-Z]{2}$/', $dados['estado'])) {
    $erros['cep'] = 'Informe uma UF válida.';
}

if ($erros) {
    flashMeusDadosCliente(
        'danger',
        'Revise os campos destacados.',
        $erros,
        $dados
    );
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT
            c.id AS cliente_id,
            c.pessoa_id,
            c.usuario_id,
            c.ativo AS cliente_ativo,
            p.cpf,
            p.ativo AS pessoa_ativa,
            u.email AS email_acesso,
            u.ativo AS usuario_ativo
         FROM clientes c
         INNER JOIN pessoas p
           ON p.id = c.pessoa_id
          AND p.empresa_id = c.empresa_id
         INNER JOIN usuarios u
           ON u.id = c.usuario_id
         WHERE c.id = :cliente_id
           AND c.empresa_id = :empresa_id
           AND c.pessoa_id = :pessoa_id
           AND c.usuario_id = :usuario_id
         LIMIT 1
         FOR UPDATE'
    );

    $stmt->execute([
        ':cliente_id' => $clienteId,
        ':empresa_id' => $empresaId,
        ':pessoa_id' => $pessoaId,
        ':usuario_id' => $usuarioId,
    ]);

    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$registro ||
        (int) $registro['cliente_ativo'] !== 1 ||
        (int) $registro['pessoa_ativa'] !== 1 ||
        (int) $registro['usuario_ativo'] !== 1
    ) {
        throw new RuntimeException('Seu cadastro não está disponível para atualização.');
    }

    $stmt = $pdo->prepare(
        'UPDATE pessoas
         SET
            nome_completo = :nome_completo,
            data_nascimento = :data_nascimento,
            genero = :genero,
            email = :email
         WHERE id = :pessoa_id
           AND empresa_id = :empresa_id'
    );

    $stmt->execute([
        ':nome_completo' => $dados['nome_completo'],
        ':data_nascimento' => $dados['data_nascimento'],
        ':genero' => $dados['genero'],
        ':email' => (string) $registro['email_acesso'],
        ':pessoa_id' => $pessoaId,
        ':empresa_id' => $empresaId,
    ]);

    $stmt = $pdo->prepare(
        'SELECT id
         FROM telefones_pessoa
         WHERE pessoa_id = :pessoa_id
         ORDER BY principal DESC, id ASC
         LIMIT 1
         FOR UPDATE'
    );

    $stmt->execute([':pessoa_id' => $pessoaId]);
    $telefoneId = $stmt->fetchColumn();

    if ($telefoneId) {
        $stmt = $pdo->prepare(
            'UPDATE telefones_pessoa
             SET
                numero = :numero,
                tipo = "celular",
                whatsapp = :whatsapp,
                principal = 1
             WHERE id = :id
               AND pessoa_id = :pessoa_id'
        );

        $stmt->execute([
            ':numero' => $dados['telefone'],
            ':whatsapp' => $dados['whatsapp'],
            ':id' => (int) $telefoneId,
            ':pessoa_id' => $pessoaId,
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
            ':numero' => $dados['telefone'],
            ':whatsapp' => $dados['whatsapp'],
        ]);
    }

    $enderecoPreenchido =
        $dados['cep'] !== '' ||
        $dados['logradouro'] !== '' ||
        $dados['numero'] !== '' ||
        $dados['complemento'] !== '' ||
        $dados['bairro'] !== '' ||
        $dados['cidade'] !== '' ||
        $dados['estado'] !== '';

    if ($enderecoPreenchido) {
        $stmt = $pdo->prepare(
            'INSERT INTO enderecos_pessoa
                (
                    pessoa_id,
                    cep,
                    logradouro,
                    numero,
                    complemento,
                    bairro,
                    cidade,
                    estado
                )
             VALUES
                (
                    :pessoa_id,
                    :cep,
                    :logradouro,
                    :numero,
                    :complemento,
                    :bairro,
                    :cidade,
                    :estado
                )
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
            ':cep' => $dados['cep'] !== '' ? $dados['cep'] : null,
            ':logradouro' => $dados['logradouro'] !== '' ? $dados['logradouro'] : null,
            ':numero' => $dados['numero'] !== '' ? $dados['numero'] : null,
            ':complemento' => $dados['complemento'] !== '' ? $dados['complemento'] : null,
            ':bairro' => $dados['bairro'] !== '' ? $dados['bairro'] : null,
            ':cidade' => $dados['cidade'] !== '' ? $dados['cidade'] : null,
            ':estado' => $dados['estado'] !== '' ? $dados['estado'] : null,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'DELETE FROM enderecos_pessoa
             WHERE pessoa_id = :pessoa_id'
        );

        $stmt->execute([':pessoa_id' => $pessoaId]);
    }

    $pdo->commit();

    $_SESSION['user_name'] = $dados['nome_completo'];

    flashMeusDadosCliente(
        'success',
        'Seus dados foram atualizados com sucesso.'
    );
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    flashMeusDadosCliente(
        'danger',
        $e->getMessage(),
        [],
        $dados
    );
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Erro ao atualizar dados do cliente: ' . $e->getMessage());

    flashMeusDadosCliente(
        'danger',
        'Não foi possível atualizar seus dados agora.',
        [],
        $dados
    );
}
