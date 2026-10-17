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
     * Resolve a empresa exclusivamente pelo slug público.
     * O empresa_id nunca é recebido do cliente.
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
     * Busca somente profissionais ativos pertencentes à empresa.
     *
     * O JOIN com pessoas também valida o empresa_id para impedir
     * qualquer associação cruzada entre tenants.
     */
    $stmtProfissionais = $pdo->prepare(
        'SELECT
            pr.id,
            pe.nome_completo,
            pr.cargo,
            pr.descricao,
            pr.foto_url
         FROM profissionais pr
         INNER JOIN pessoas pe
            ON pe.id = pr.pessoa_id
           AND pe.empresa_id = pr.empresa_id
           AND pe.ativo = 1
         WHERE pr.empresa_id = :empresa_id
           AND pr.ativo = 1
         ORDER BY pe.nome_completo'
    );

    $stmtProfissionais->execute([
        ':empresa_id' => $empresaId,
    ]);

    $registrosProfissionais = $stmtProfissionais->fetchAll(
        PDO::FETCH_ASSOC
    );

    /*
     * Busca os serviços ativos e disponíveis para agendamento
     * online vinculados aos profissionais da mesma empresa.
     */
    $stmtServicos = $pdo->prepare(
        'SELECT
            pr.id AS profissional_id,
            s.id AS servico_id
         FROM profissionais pr
         INNER JOIN pessoas pe
            ON pe.id = pr.pessoa_id
           AND pe.empresa_id = pr.empresa_id
           AND pe.ativo = 1
         INNER JOIN profissional_servicos ps
            ON ps.profissional_id = pr.id
           AND ps.ativo = 1
         INNER JOIN servicos s
            ON s.id = ps.servico_id
           AND s.empresa_id = pr.empresa_id
           AND s.ativo = 1
           AND s.permite_agendamento_online = 1
         WHERE pr.empresa_id = :empresa_id
           AND pr.ativo = 1
         ORDER BY
            pr.id,
            s.nome'
    );

    $stmtServicos->execute([
        ':empresa_id' => $empresaId,
    ]);

    $registrosServicos = $stmtServicos->fetchAll(
        PDO::FETCH_ASSOC
    );

    $servicosPorProfissional = [];

    foreach ($registrosServicos as $registro) {
        $profissionalId = (int) $registro['profissional_id'];

        if (!isset($servicosPorProfissional[$profissionalId])) {
            $servicosPorProfissional[$profissionalId] = [];
        }

        $servicosPorProfissional[$profissionalId][] =
            (int) $registro['servico_id'];
    }

    $profissionais = [];

    foreach ($registrosProfissionais as $registro) {
        $profissionalId = (int) $registro['id'];

        $profissionais[] = [
            'id' => $profissionalId,
            'nome' => $registro['nome_completo'],
            'cargo' => $registro['cargo'],
            'descricao' => $registro['descricao'],
            'foto_url' => $registro['foto_url'],
            'servicos' => $servicosPorProfissional[$profissionalId] ?? [],
        ];
    }

    responderJson(200, [
        'sucesso' => true,
        'empresa' => $empresa['slug'],
        'profissionais' => $profissionais,
    ]);
} catch (Throwable $e) {
    error_log(
        'Erro na API pública de profissionais: ' . $e->getMessage()
    );

    responderJson(500, [
        'sucesso' => false,
        'erro' => 'Não foi possível consultar os profissionais.',
    ]);
}