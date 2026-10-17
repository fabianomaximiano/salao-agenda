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

    /*
     * Resolve o identificador interno da empresa exclusivamente
     * pelo slug público.
     */
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

    $empresaId = (int) $empresa['id'];

    /*
     * O empresa_id nunca é recebido do cliente.
     * Ele foi obtido acima a partir do slug da empresa.
     */
    $stmtServicos = $pdo->prepare(
        'SELECT
            s.id,
            s.nome,
            s.descricao,
            s.duracao_minutos,
            s.preco,
            c.id AS categoria_id,
            c.nome AS categoria_nome
         FROM servicos s
         LEFT JOIN categorias_servicos c
            ON c.id = s.categoria_id
           AND c.empresa_id = s.empresa_id
           AND c.ativo = 1
         WHERE s.empresa_id = :empresa_id
           AND s.ativo = 1
           AND s.permite_agendamento_online = 1
         ORDER BY
            COALESCE(c.ordem, 999999),
            c.nome,
            s.nome'
    );

    $stmtServicos->execute([
        ':empresa_id' => $empresaId,
    ]);

    $registros = $stmtServicos->fetchAll(PDO::FETCH_ASSOC);

    $servicos = [];

    foreach ($registros as $registro) {
        $categoria = null;

        if ($registro['categoria_id'] !== null) {
            $categoria = [
                'id' => (int) $registro['categoria_id'],
                'nome' => $registro['categoria_nome'],
            ];
        }

        $servicos[] = [
            'id' => (int) $registro['id'],
            'nome' => $registro['nome'],
            'descricao' => $registro['descricao'],
            'duracao_minutos' => (int) $registro['duracao_minutos'],
            'preco' => $registro['preco'],
            'categoria' => $categoria,
        ];
    }

    responderJson(200, [
        'sucesso' => true,
        'empresa' => $empresa['slug'],
        'servicos' => $servicos,
    ]);
} catch (Throwable $e) {
    error_log(
        'Erro na API pública de serviços: ' . $e->getMessage()
    );

    responderJson(500, [
        'sucesso' => false,
        'erro' => 'Não foi possível consultar os serviços.',
    ]);
}