<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../services/ImagemProfissionalService.php';

exigirProfissional();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método não permitido.');
}

$csrfSessao = (string) ($_SESSION['csrf_meus_dados_profissional'] ?? '');
$csrfRecebido = (string) ($_POST['csrf_token'] ?? '');

if ($csrfSessao === '' || $csrfRecebido === '' || !hash_equals($csrfSessao, $csrfRecebido)) {
    http_response_code(403);
    exit('Token CSRF inválido.');
}

$empresaId = (int) $_SESSION['empresa_id'];
$profissionalId = (int) $_SESSION['profissional_id'];
$pessoaId = (int) $_SESSION['pessoa_id'];
$usuarioId = (int) $_SESSION['user_id'];
$pdo = getDB();

function voltarMeusDados(string $tipo, string $mensagem, array $erros = [], array $old = []): never
{
    $_SESSION['flash_meus_dados_profissional'] = [
        'tipo' => $tipo,
        'mensagem' => $mensagem,
        'erros' => $erros,
        'old' => $old,
    ];
    header('Location: ../meus-dados.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT pr.id, pr.pessoa_id, pr.usuario_id, pr.foto_url
     FROM profissionais pr
     INNER JOIN pessoas p
       ON p.id = pr.pessoa_id
      AND p.empresa_id = pr.empresa_id
     WHERE pr.id = :profissional_id
       AND pr.pessoa_id = :pessoa_id
       AND pr.usuario_id = :usuario_id
       AND pr.empresa_id = :empresa_id
       AND pr.ativo = 1
       AND p.ativo = 1
     LIMIT 1'
);
$stmt->execute([
    ':profissional_id' => $profissionalId,
    ':pessoa_id' => $pessoaId,
    ':usuario_id' => $usuarioId,
    ':empresa_id' => $empresaId,
]);
$profissional = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profissional) {
    http_response_code(403);
    exit('Acesso inválido.');
}

$nomeCompleto = trim((string) ($_POST['nome_completo'] ?? ''));
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
$estado = strtoupper(trim((string) ($_POST['estado'] ?? '')));
$cargo = trim((string) ($_POST['cargo'] ?? ''));
$descricao = trim((string) ($_POST['descricao'] ?? ''));

$old = compact(
    'nomeCompleto', 'dataNascimento', 'genero', 'telefone', 'whatsapp',
    'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade',
    'estado', 'cargo', 'descricao'
);
$old['nome_completo'] = $nomeCompleto;
$old['data_nascimento'] = $dataNascimento;
unset($old['nomeCompleto'], $old['dataNascimento']);

$erros = [];

if ($nomeCompleto === '') {
    $erros['nome_completo'] = 'Informe o nome completo.';
} elseif (mb_strlen($nomeCompleto) > 160) {
    $erros['nome_completo'] = 'O nome deve ter no máximo 160 caracteres.';
}

if ($dataNascimento !== '') {
    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $dataNascimento);
    if (!$data || $data->format('Y-m-d') !== $dataNascimento) {
        $erros['data_nascimento'] = 'Informe uma data válida.';
    } elseif ($data > new DateTimeImmutable('today')) {
        $erros['data_nascimento'] = 'A data de nascimento não pode estar no futuro.';
    }
}

if (!in_array($genero, ['masculino', 'feminino', 'nao_binario', 'nao_informado'], true)) {
    $erros['genero'] = 'Gênero inválido.';
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

$temEndereco = $cep !== '' || $logradouro !== '' || $numero !== '' || $complemento !== ''
    || $bairro !== '' || $cidade !== '' || $estado !== '';

if ($temEndereco) {
    $cepDigitos = preg_replace('/\D+/', '', $cep) ?? '';
    if (strlen($cepDigitos) !== 8) {
        $erros['cep'] = 'Informe um CEP válido.';
    } else {
        $cep = substr($cepDigitos, 0, 5) . '-' . substr($cepDigitos, 5, 3);
        $old['cep'] = $cep;
    }

    if ($logradouro === '') {
        $erros['logradouro'] = 'Informe o logradouro.';
    } elseif (mb_strlen($logradouro) > 180) {
        $erros['logradouro'] = 'O logradouro deve ter no máximo 180 caracteres.';
    }

    if ($numero === '') {
        $erros['numero'] = 'Informe o número.';
    } elseif (mb_strlen($numero) > 30) {
        $erros['numero'] = 'O número deve ter no máximo 30 caracteres.';
    }

    if (mb_strlen($complemento) > 120) {
        $erros['complemento'] = 'O complemento deve ter no máximo 120 caracteres.';
    }

    if ($bairro === '') {
        $erros['bairro'] = 'Informe o bairro.';
    } elseif (mb_strlen($bairro) > 120) {
        $erros['bairro'] = 'O bairro deve ter no máximo 120 caracteres.';
    }

    if ($cidade === '') {
        $erros['cidade'] = 'Informe a cidade.';
    } elseif (mb_strlen($cidade) > 120) {
        $erros['cidade'] = 'A cidade deve ter no máximo 120 caracteres.';
    }

    if (!preg_match('/^[A-Z]{2}$/', $estado)) {
        $erros['estado'] = 'Informe uma UF válida.';
    }
}

if ($erros) {
    voltarMeusDados('danger', 'Revise os campos destacados.', $erros, $old);
}

$fotoAtual = is_string($profissional['foto_url'] ?? null) ? $profissional['foto_url'] : null;
$arquivosNovaFoto = [];
$novaFotoUrl = null;

if (isset($_FILES['foto']) && is_array($_FILES['foto'])) {
    try {
        $arquivosNovaFoto = ImagemProfissionalService::processarUpload($_FILES['foto'], $empresaId);
        $novaFotoUrl = isset($arquivosNovaFoto['g']['url']) && is_string($arquivosNovaFoto['g']['url'])
            ? $arquivosNovaFoto['g']['url']
            : null;
    } catch (Throwable $e) {
        voltarMeusDados('danger', 'Revise a foto do profissional.', ['foto' => $e->getMessage()], $old);
    }
}

$fotoParaSalvar = $novaFotoUrl ?? $fotoAtual;

$pdo->beginTransaction();

try {
    $stmtPessoa = $pdo->prepare(
        'UPDATE pessoas
         SET nome_completo = :nome_completo,
             data_nascimento = :data_nascimento,
             genero = :genero
         WHERE id = :pessoa_id
           AND empresa_id = :empresa_id'
    );
    $stmtPessoa->execute([
        ':nome_completo' => $nomeCompleto,
        ':data_nascimento' => $dataNascimento !== '' ? $dataNascimento : null,
        ':genero' => $genero,
        ':pessoa_id' => $pessoaId,
        ':empresa_id' => $empresaId,
    ]);

    $stmtProfissional = $pdo->prepare(
        'UPDATE profissionais
         SET cargo = :cargo,
             descricao = :descricao,
             foto_url = :foto_url
         WHERE id = :profissional_id
           AND pessoa_id = :pessoa_id
           AND usuario_id = :usuario_id
           AND empresa_id = :empresa_id'
    );
    $stmtProfissional->execute([
        ':cargo' => $cargo !== '' ? $cargo : null,
        ':descricao' => $descricao !== '' ? $descricao : null,
        ':foto_url' => $fotoParaSalvar,
        ':profissional_id' => $profissionalId,
        ':pessoa_id' => $pessoaId,
        ':usuario_id' => $usuarioId,
        ':empresa_id' => $empresaId,
    ]);

    $stmtTelefone = $pdo->prepare(
        'SELECT id
         FROM telefones_pessoa
         WHERE pessoa_id = :pessoa_id
         ORDER BY principal DESC, id ASC
         LIMIT 1'
    );
    $stmtTelefone->execute([':pessoa_id' => $pessoaId]);
    $telefoneId = $stmtTelefone->fetchColumn();

    if ($telefone !== '') {
        if ($telefoneId) {
            $stmt = $pdo->prepare(
                'UPDATE telefones_pessoa
                 SET numero = :numero, tipo = "celular", whatsapp = :whatsapp, principal = 1
                 WHERE id = :id AND pessoa_id = :pessoa_id'
            );
            $stmt->execute([
                ':numero' => $telefone,
                ':whatsapp' => $whatsapp,
                ':id' => (int) $telefoneId,
                ':pessoa_id' => $pessoaId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO telefones_pessoa (pessoa_id, numero, tipo, whatsapp, principal)
                 VALUES (:pessoa_id, :numero, "celular", :whatsapp, 1)'
            );
            $stmt->execute([
                ':pessoa_id' => $pessoaId,
                ':numero' => $telefone,
                ':whatsapp' => $whatsapp,
            ]);
        }
    } elseif ($telefoneId) {
        $stmt = $pdo->prepare(
            'DELETE FROM telefones_pessoa WHERE id = :id AND pessoa_id = :pessoa_id'
        );
        $stmt->execute([':id' => (int) $telefoneId, ':pessoa_id' => $pessoaId]);
    }

    if ($temEndereco) {
        $stmtEndereco = $pdo->prepare(
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
        $stmtEndereco->execute([
            ':pessoa_id' => $pessoaId,
            ':cep' => $cep,
            ':logradouro' => $logradouro,
            ':numero' => $numero,
            ':complemento' => $complemento !== '' ? $complemento : null,
            ':bairro' => $bairro,
            ':cidade' => $cidade,
            ':estado' => $estado,
        ]);
    } else {
        $stmtEndereco = $pdo->prepare(
            'DELETE FROM enderecos_pessoa WHERE pessoa_id = :pessoa_id'
        );
        $stmtEndereco->execute([':pessoa_id' => $pessoaId]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ImagemProfissionalService::excluirArquivosGerados($arquivosNovaFoto);
    error_log('Erro ao atualizar meus dados profissional id=' . $profissionalId . ': ' . $e->getMessage());
    voltarMeusDados('danger', 'Não foi possível atualizar seus dados agora. Tente novamente.', [], $old);
}

if ($novaFotoUrl !== null && $fotoAtual !== null && $fotoAtual !== $novaFotoUrl) {
    ImagemProfissionalService::excluirPorUrl($fotoAtual, $empresaId);
}

$_SESSION['user_name'] = $nomeCompleto;
$_SESSION['csrf_meus_dados_profissional'] = bin2hex(random_bytes(32));

voltarMeusDados('success', 'Seus dados foram atualizados com sucesso.');
