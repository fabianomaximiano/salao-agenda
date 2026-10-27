<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');

    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido.',
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

require_once dirname(__DIR__, 3) . '/includes/db.php';

$slug = trim((string) ($_GET['slug'] ?? ''));

if ($slug === '') {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Informe a empresa.',
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    $pdo = getDB();

    $stmt = $pdo->prepare(
        '
        SELECT
            c.id,
            c.nome,
            c.descricao
        FROM categorias_servicos c
        INNER JOIN empresas e
            ON e.id = c.empresa_id
        WHERE e.slug = :slug
          AND e.ativo = 1
          AND c.ativo = 1
        ORDER BY
            c.ordem ASC,
            c.nome ASC
        '
    );

    $stmt->execute([
        ':slug' => $slug,
    ]);

    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $categorias,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log(
        'Erro na API pública de categorias: '
        . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Não foi possível consultar as categorias.',
    ], JSON_UNESCAPED_UNICODE);
}