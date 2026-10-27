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
            nome_fantasia,
            email,
            telefone,
            whatsapp,
            cep,
            logradouro,
            numero,
            complemento,
            bairro,
            cidade,
            estado
        FROM empresas
        WHERE slug = :slug
          AND ativo = 1
        LIMIT 1
        '
    );

    $stmt->execute([
        ':slug' => $slug,
    ]);

    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$empresa) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Empresa não encontrada.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => $empresa,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log(
        'Erro na API pública de empresa: '
        . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Não foi possível consultar os dados da empresa.',
    ], JSON_UNESCAPED_UNICODE);
}