<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

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

$slug = mb_strtolower(trim((string) ($_GET['empresa'] ?? '')));

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

    $stmt = $pdo->prepare(
        'SELECT
            nome_fantasia,
            slug,
            email,
            telefone,
            whatsapp,
            cep,
            logradouro,
            numero,
            complemento,
            bairro,
            cidade,
            estado,
            timezone,
            logo_url
         FROM empresas
         WHERE slug = :slug
           AND ativo = 1
         LIMIT 1'
    );

    $stmt->execute([
        ':slug' => $slug,
    ]);

    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$empresa) {
        responderJson(404, [
            'sucesso' => false,
            'erro' => 'Empresa não encontrada.',
        ]);
    }

    responderJson(200, [
        'sucesso' => true,
        'empresa' => [
            'nome_fantasia' => $empresa['nome_fantasia'],
            'slug' => $empresa['slug'],
            'logo_url' => $empresa['logo_url'],
            'contato' => [
                'email' => $empresa['email'],
                'telefone' => $empresa['telefone'],
                'whatsapp' => $empresa['whatsapp'],
            ],
            'endereco' => [
                'cep' => $empresa['cep'],
                'logradouro' => $empresa['logradouro'],
                'numero' => $empresa['numero'],
                'complemento' => $empresa['complemento'],
                'bairro' => $empresa['bairro'],
                'cidade' => $empresa['cidade'],
                'estado' => $empresa['estado'],
            ],
            'timezone' => $empresa['timezone'],
        ],
    ]);
} catch (Throwable $e) {
    error_log(
        'Erro na API pública de empresa: ' . $e->getMessage()
    );

    responderJson(500, [
        'sucesso' => false,
        'erro' => 'Não foi possível consultar a empresa.',
    ]);
}