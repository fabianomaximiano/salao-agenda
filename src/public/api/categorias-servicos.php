<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

exigirAdministrador();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método não permitido.');
}

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();

$csrfRecebido = (string) ($_POST['csrf_token'] ?? '');
$csrfSessao = (string) ($_SESSION['csrf_categorias_servicos'] ?? '');

if ($csrfSessao === '' || $csrfRecebido === '' || !hash_equals($csrfSessao, $csrfRecebido)) {
    http_response_code(403);
    exit('Token CSRF inválido.');
}

$acao = (string) ($_POST['acao'] ?? '');

function redirecionarCategorias(): never
{
    header('Location: ../categorias-servicos.php');
    exit;
}

function definirFlashCategoria(string $tipo, string $mensagem, array $erros = [], array $old = []): void
{
    $_SESSION['flash_categorias_servicos'] = [
        'tipo' => $tipo,
        'mensagem' => $mensagem,
        'erros' => $erros,
        'old' => $old,
    ];
}

if ($acao === 'alternar_status') {
    $categoriaId = filter_input(INPUT_POST, 'categoria_id', FILTER_VALIDATE_INT);

    if (!$categoriaId) {
        definirFlashCategoria('danger', 'Categoria inválida.');
        redirecionarCategorias();
    }

    $stmt = $pdo->prepare(
        'SELECT id, ativo
         FROM categorias_servicos
         WHERE id = :id
           AND empresa_id = :empresa_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $categoriaId,
        ':empresa_id' => $empresaId,
    ]);

    $categoria = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$categoria) {
        definirFlashCategoria('danger', 'Categoria não encontrada.');
        redirecionarCategorias();
    }

    $novoStatus = (int) $categoria['ativo'] === 1 ? 0 : 1;

    $stmtUpdate = $pdo->prepare(
        'UPDATE categorias_servicos
         SET ativo = :ativo
         WHERE id = :id
           AND empresa_id = :empresa_id'
    );
    $stmtUpdate->execute([
        ':ativo' => $novoStatus,
        ':id' => $categoriaId,
        ':empresa_id' => $empresaId,
    ]);

    definirFlashCategoria(
        'success',
        $novoStatus === 1 ? 'Categoria ativada com sucesso.' : 'Categoria desativada com sucesso.'
    );
    redirecionarCategorias();
}

if (!in_array($acao, ['criar', 'atualizar'], true)) {
    http_response_code(400);
    exit('Ação inválida.');
}

$nome = trim((string) ($_POST['nome'] ?? ''));
$descricao = trim((string) ($_POST['descricao'] ?? ''));
$ordemRaw = trim((string) ($_POST['ordem'] ?? '0'));
$categoriaId = null;

if ($acao === 'atualizar') {
    $categoriaId = filter_input(INPUT_POST, 'categoria_id', FILTER_VALIDATE_INT);

    if (!$categoriaId) {
        definirFlashCategoria('danger', 'Categoria inválida.');
        redirecionarCategorias();
    }

    $stmtExiste = $pdo->prepare(
        'SELECT id
         FROM categorias_servicos
         WHERE id = :id
           AND empresa_id = :empresa_id
         LIMIT 1'
    );
    $stmtExiste->execute([
        ':id' => $categoriaId,
        ':empresa_id' => $empresaId,
    ]);

    if (!$stmtExiste->fetchColumn()) {
        definirFlashCategoria('danger', 'Categoria não encontrada.');
        redirecionarCategorias();
    }
}

$old = [
    'nome' => $nome,
    'descricao' => $descricao,
    'ordem' => $ordemRaw,
];

$erros = [];

if ($nome === '') {
    $erros['nome'] = 'Informe o nome da categoria.';
} elseif (mb_strlen($nome) > 120) {
    $erros['nome'] = 'O nome deve ter no máximo 120 caracteres.';
}

if (mb_strlen($descricao) > 1000) {
    $erros['descricao'] = 'A descrição deve ter no máximo 1000 caracteres.';
}

$ordem = filter_var($ordemRaw === '' ? '0' : $ordemRaw, FILTER_VALIDATE_INT);
if ($ordem === false || $ordem < 0 || $ordem > 9999) {
    $erros['ordem'] = 'Informe uma ordem entre 0 e 9999.';
}

if (!$erros) {
    $sqlDuplicado =
        'SELECT id
         FROM categorias_servicos
         WHERE empresa_id = :empresa_id
           AND nome = :nome';

    $paramsDuplicado = [
        ':empresa_id' => $empresaId,
        ':nome' => $nome,
    ];

    if ($acao === 'atualizar') {
        $sqlDuplicado .= ' AND id <> :id';
        $paramsDuplicado[':id'] = $categoriaId;
    }

    $sqlDuplicado .= ' LIMIT 1';

    $stmtDuplicado = $pdo->prepare($sqlDuplicado);
    $stmtDuplicado->execute($paramsDuplicado);

    if ($stmtDuplicado->fetchColumn()) {
        $erros['nome'] = 'Já existe uma categoria com esse nome.';
    }
}

if ($erros) {
    definirFlashCategoria('danger', 'Revise os campos destacados.', $erros, $old);

    if ($acao === 'atualizar' && $categoriaId) {
        header('Location: ../categorias-servicos.php?editar=' . $categoriaId);
        exit;
    }

    redirecionarCategorias();
}

if ($acao === 'criar') {
    $stmtInsert = $pdo->prepare(
        'INSERT INTO categorias_servicos (
            empresa_id,
            nome,
            descricao,
            ordem,
            ativo
        ) VALUES (
            :empresa_id,
            :nome,
            :descricao,
            :ordem,
            1
        )'
    );
    $stmtInsert->execute([
        ':empresa_id' => $empresaId,
        ':nome' => $nome,
        ':descricao' => $descricao !== '' ? $descricao : null,
        ':ordem' => $ordem,
    ]);

    definirFlashCategoria('success', 'Categoria criada com sucesso.');
} else {
    $stmtUpdate = $pdo->prepare(
        'UPDATE categorias_servicos
         SET nome = :nome,
             descricao = :descricao,
             ordem = :ordem
         WHERE id = :id
           AND empresa_id = :empresa_id'
    );
    $stmtUpdate->execute([
        ':nome' => $nome,
        ':descricao' => $descricao !== '' ? $descricao : null,
        ':ordem' => $ordem,
        ':id' => $categoriaId,
        ':empresa_id' => $empresaId,
    ]);

    definirFlashCategoria('success', 'Categoria atualizada com sucesso.');
}

$_SESSION['csrf_categorias_servicos'] = bin2hex(random_bytes(32));

redirecionarCategorias();
