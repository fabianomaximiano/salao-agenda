<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responderJson(int $status, array $dados): never
{
    http_response_code($status);

    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');

    responderJson(405, [
        'sucesso' => false,
        'erro' => 'Método não permitido.',
    ]);
}

exigirApiPublicaAutenticada();

$slug = mb_strtolower(trim((string) ($_GET['slug'] ?? $_GET['empresa'] ?? '')));

if (
    $slug === ''
    || mb_strlen($slug) > 160
    || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)
) {
    responderJson(400, [
        'sucesso' => false,
        'erro' => 'Empresa inválida.',
    ]);
}

try {
    $pdo = getDB();

    $stmtEmpresa = $pdo->prepare(
        'SELECT id, slug
         FROM empresas
         WHERE slug = :slug
           AND ativo = 1
         LIMIT 1'
    );

    $stmtEmpresa->execute([
        ':slug' => $slug,
    ]);

    $empresa = $stmtEmpresa->fetch(PDO::FETCH_ASSOC);

    if (!$empresa) {
        responderJson(404, [
            'sucesso' => false,
            'erro' => 'Empresa não encontrada.',
        ]);
    }

    $stmtCategorias = $pdo->prepare(
        'SELECT id, nome, descricao
         FROM categorias_servicos
         WHERE empresa_id = :empresa_id
           AND ativo = 1
         ORDER BY ordem ASC, nome ASC'
    );

    $stmtCategorias->execute([
        ':empresa_id' => (int) $empresa['id'],
    ]);

    $categorias = array_map(
        static fn(array $categoria): array => [
            'id' => (int) $categoria['id'],
            'nome' => $categoria['nome'],
            'descricao' => $categoria['descricao'],
        ],
        $stmtCategorias->fetchAll(PDO::FETCH_ASSOC)
    );

    responderJson(200, [
        'sucesso' => true,
        'empresa' => $empresa['slug'],
        'categorias' => $categorias,
    ]);
} catch (Throwable $e) {
    error_log(
        'Erro na API pública de categorias: ' . $e->getMessage()
    );

    responderJson(500, [
        'sucesso' => false,
        'erro' => 'Não foi possível consultar as categorias.',
    ]);
}
