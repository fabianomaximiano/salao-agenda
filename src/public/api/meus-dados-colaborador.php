<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

exigirColaborador();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

$csrf = (string) ($_POST['csrf_token'] ?? '');
$csrfSessao = (string) ($_SESSION['csrf_meus_dados_colaborador'] ?? '');

if ($csrf === '' || $csrfSessao === '' || !hash_equals($csrfSessao, $csrf)) {
    http_response_code(403);
    exit('Token CSRF inválido.');
}

$usuarioId = (int) ($_SESSION['user_id'] ?? 0);
$empresaId = (int) ($_SESSION['empresa_id'] ?? 0);
$colaboradorId = (int) ($_SESSION['colaborador_id'] ?? 0);
$pessoaId = (int) ($_SESSION['pessoa_id'] ?? 0);

if ($usuarioId <= 0 || $empresaId <= 0 || $colaboradorId <= 0 || $pessoaId <= 0) {
    header('Location: ../logout.php');
    exit;
}

$pdo = getDB();

function redirecionarDadosColaborador(
    string $tipo,
    string $mensagem,
    array $erros = [],
    array $old = []
): never {
    $_SESSION['flash_meus_dados_colaborador'] = [
        'tipo' => $tipo,
        'mensagem' => $mensagem,
        'erros' => $erros,
        'old' => $old,
    ];

    header('Location: ../meus-dados-colaborador.php');
    exit;
}

function somenteDigitosDadosColaborador(string $valor): string
{
    return preg_replace('/\D+/', '', $valor) ?? '';
}

function dataValidaDadosColaborador(string $data): bool
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

$nome = trim((string) ($_POST['nome_completo'] ?? ''));
$dataNascimento = trim((string) ($_POST['data_nascimento'] ?? ''));
$genero = trim((string) ($_POST['genero'] ?? 'nao_informado'));
$telefone = trim((string) ($_POST['telefone'] ?? ''));
$whatsapp = isset($_POST['whatsapp']) ? 1 : 0;

$cep = trim((string) ($_POST['cep'] ?? ''));
$logradouro = trim((string) ($_POST['logradouro'] ?? ''));
$numero = trim((string) ($_POST['numero'] ?? ''));
$complemento = trim((string) ($_POST['complemento'] ?? ''));
$bairro = trim((string) ($_POST['bairro'] ?? ''));
$cidade = trim((string) ($_POST['cidade'] ?? ''));
$estado = mb_strtoupper(trim((string) ($_POST['estado'] ?? '')));

$old = [
    'nome_completo' => $nome,
    'data_nascimento' => $dataNascimento,
    'genero' => $genero,
    'telefone' => $telefone,
    'whatsapp' => $whatsapp,
    'cep' => $cep,
    'logradouro' => $logradouro,
    'numero' => $numero,
    'complemento' => $complemento,
    'bairro' => $bairro,
    'cidade' => $cidade,
    'estado' => $estado,
];

$erros = [];

if ($nome === '' || mb_strlen($nome) > 160) {
    $erros['nome_completo'] = 'Informe um nome válido.';
}

if (!dataValidaDadosColaborador($dataNascimento)) {
    $erros['data_nascimento'] = 'Informe uma data de nascimento válida.';
}

if (!in_array($genero, ['masculino', 'feminino', 'nao_binario', 'nao_informado'], true)) {
    $genero = 'nao_informado';
    $old['genero'] = $genero;
}

if ($cep !== '' && strlen(somenteDigitosDadosColaborador($cep)) !== 8) {
    $erros['cep'] = 'Informe um CEP válido.';
}

if ($estado !== '' && !preg_match('/^[A-Z]{2}$/', $estado)) {
    $erros['estado'] = 'Informe uma UF válida.';
}

if ($erros) {
    redirecionarDadosColaborador(
        'danger',
        'Revise os campos destacados.',
        $erros,
        $old
    );
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT
            c.id,
            c.pessoa_id,
            c.usuario_id,
            p.email AS pessoa_email,
            u.email AS usuario_email
         FROM colaboradores c
         INNER JOIN pessoas p
                 ON p.id = c.pessoa_id
                AND p.empresa_id = c.empresa_id
         INNER JOIN usuarios u
                 ON u.id = c.usuario_id
         WHERE c.id = :colaborador_id
           AND c.empresa_id = :empresa_id
           AND c.pessoa_id = :pessoa_id
           AND c.usuario_id = :usuario_id
           AND c.ativo = 1
           AND p.ativo = 1
           AND u.ativo = 1
         LIMIT 1
         FOR UPDATE'
    );

    $stmt->execute([
        ':colaborador_id' => $colaboradorId,
        ':empresa_id' => $empresaId,
        ':pessoa_id' => $pessoaId,
        ':usuario_id' => $usuarioId,
    ]);

    $vinculo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vinculo) {
        throw new RuntimeException('Seu cadastro de colaborador não está disponível.');
    }

    $emailAcesso = mb_strtolower(trim((string) $vinculo['usuario_email']));

    $stmt = $pdo->prepare(
        'UPDATE pessoas
            SET nome_completo = :nome_completo,
                data_nascimento = :data_nascimento,
                genero = :genero,
                email = :email
          WHERE id = :pessoa_id
            AND empresa_id = :empresa_id'
    );

    $stmt->execute([
        ':nome_completo' => $nome,
        ':data_nascimento' => $dataNascimento !== '' ? $dataNascimento : null,
        ':genero' => $genero,
        ':email' => $emailAcesso,
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
                ':id' => (int) $telefoneId,
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
        $stmt->execute([':id' => (int) $telefoneId]);
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
        $stmt = $pdo->prepare(
            'DELETE FROM enderecos_pessoa
             WHERE pessoa_id = :pessoa_id'
        );
        $stmt->execute([':pessoa_id' => $pessoaId]);
    }

    $pdo->commit();

    $_SESSION['user_name'] = $nome;
    $_SESSION['user_email'] = $emailAcesso;
    $_SESSION['csrf_meus_dados_colaborador'] = bin2hex(random_bytes(32));

    redirecionarDadosColaborador(
        'success',
        'Seus dados foram atualizados com sucesso.'
    );
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    redirecionarDadosColaborador(
        'danger',
        $e->getMessage(),
        [],
        $old
    );
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Erro ao atualizar dados do colaborador: ' . $e->getMessage());

    redirecionarDadosColaborador(
        'danger',
        'Não foi possível atualizar seus dados agora.',
        [],
        $old
    );
}
