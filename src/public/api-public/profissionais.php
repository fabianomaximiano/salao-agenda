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

/*
 * O parâmetro id é opcional.
 *
 * Sem id:
 * retorna a listagem pública atual de profissionais.
 *
 * Com id:
 * retorna somente o perfil público do profissional solicitado.
 */
$profissionalIdFiltro = null;

if (isset($_GET['id'])) {
    $idRecebido = trim((string) $_GET['id']);

    if (
        $idRecebido === ''
        || !ctype_digit($idRecebido)
        || (int) $idRecebido <= 0
    ) {
        responderJson(400, [
            'sucesso' => false,
            'erro' => 'Profissional inválido.',
        ]);
    }

    $profissionalIdFiltro = (int) $idRecebido;
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
     *
     * Quando um id é informado, o filtro é aplicado junto com
     * empresa_id e os status ativos.
     */
    $sqlProfissionais =
        'SELECT
            pr.id,
            pe.nome_completo,
            pr.cargo,
            pr.descricao,
            pr.foto_url,
            pr.ano_inicio_carreira
         FROM profissionais pr
         INNER JOIN pessoas pe
            ON pe.id = pr.pessoa_id
           AND pe.empresa_id = pr.empresa_id
           AND pe.ativo = 1
         WHERE pr.empresa_id = :empresa_id
           AND pr.ativo = 1';

    if ($profissionalIdFiltro !== null) {
        $sqlProfissionais .= '
           AND pr.id = :profissional_id';
    }

    $sqlProfissionais .= '
         ORDER BY pe.nome_completo';

    $stmtProfissionais = $pdo->prepare($sqlProfissionais);

    $parametrosProfissionais = [
        ':empresa_id' => $empresaId,
    ];

    if ($profissionalIdFiltro !== null) {
        $parametrosProfissionais[':profissional_id'] =
            $profissionalIdFiltro;
    }

    $stmtProfissionais->execute($parametrosProfissionais);

    $registrosProfissionais = $stmtProfissionais->fetchAll(
        PDO::FETCH_ASSOC
    );

    /*
     * No modo individual, um profissional inexistente, inativo
     * ou pertencente a outra empresa não pode ser exposto.
     */
    if (
        $profissionalIdFiltro !== null
        && count($registrosProfissionais) === 0
    ) {
        responderJson(404, [
            'sucesso' => false,
            'erro' => 'Profissional não encontrado.',
        ]);
    }

    /*
     * Busca os serviços ativos e disponíveis para agendamento
     * online vinculados aos profissionais da mesma empresa.
     *
     * A consulta também pode ser limitada ao profissional
     * solicitado no modo individual.
     */
    $sqlServicos =
        'SELECT
            pr.id AS profissional_id,
            s.id AS servico_id,
            s.nome AS servico_nome
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
           AND pr.ativo = 1';

    if ($profissionalIdFiltro !== null) {
        $sqlServicos .= '
           AND pr.id = :profissional_id';
    }

    $sqlServicos .= '
         ORDER BY
            pr.id,
            s.nome';

    $stmtServicos = $pdo->prepare($sqlServicos);

    $parametrosServicos = [
        ':empresa_id' => $empresaId,
    ];

    if ($profissionalIdFiltro !== null) {
        $parametrosServicos[':profissional_id'] =
            $profissionalIdFiltro;
    }

    $stmtServicos->execute($parametrosServicos);

    $registrosServicos = $stmtServicos->fetchAll(
        PDO::FETCH_ASSOC
    );

    /*
     * Mantemos duas representações dos serviços:
     *
     * - IDs: preserva integralmente o contrato da listagem atual.
     * - Objetos públicos: usados no perfil individual para que
     *   a página consiga exibir as especialidades pelo nome.
     */
    $servicosIdsPorProfissional = [];
    $servicosPublicosPorProfissional = [];

    foreach ($registrosServicos as $registro) {
        $profissionalId = (int) $registro['profissional_id'];

        if (!isset($servicosIdsPorProfissional[$profissionalId])) {
            $servicosIdsPorProfissional[$profissionalId] = [];
        }

        if (!isset($servicosPublicosPorProfissional[$profissionalId])) {
            $servicosPublicosPorProfissional[$profissionalId] = [];
        }

        $servicosIdsPorProfissional[$profissionalId][] =
            (int) $registro['servico_id'];

        $servicosPublicosPorProfissional[$profissionalId][] = [
            'id' => (int) $registro['servico_id'],
            'nome' => $registro['servico_nome'],
        ];
    }

    /*
     * Modo individual.
     *
     * Retorna um único objeto "profissional", próprio para
     * alimentar a página pública de perfil/currículo.
     */
    if ($profissionalIdFiltro !== null) {
        $registro = $registrosProfissionais[0];
        $profissionalId = (int) $registro['id'];

        $profissional = [
            'id' => $profissionalId,
            'nome' => $registro['nome_completo'],
            'cargo' => $registro['cargo'],
            'descricao' => $registro['descricao'],
            'foto_url' => $registro['foto_url'],
            'ano_inicio_carreira' => $registro['ano_inicio_carreira'] !== null
                ? (int) $registro['ano_inicio_carreira']
                : null,
            'anos_experiencia' => $registro['ano_inicio_carreira'] !== null
                ? max(
                    0,
                    (int) date('Y')
                    - (int) $registro['ano_inicio_carreira']
                )
                : null,
            'servicos' =>
                $servicosPublicosPorProfissional[$profissionalId] ?? [],
        ];

        responderJson(200, [
            'sucesso' => true,
            'empresa' => $empresa['slug'],
            'profissional' => $profissional,
        ]);
    }

    /*
     * Modo listagem.
     *
     * Mantém o contrato já existente, inclusive servicos como
     * array de IDs, evitando quebrar consumidores atuais.
     */
    $profissionais = [];

    foreach ($registrosProfissionais as $registro) {
        $profissionalId = (int) $registro['id'];

        $profissionais[] = [
            'id' => $profissionalId,
            'nome' => $registro['nome_completo'],
            'cargo' => $registro['cargo'],
            'descricao' => $registro['descricao'],
            'foto_url' => $registro['foto_url'],
            'ano_inicio_carreira' => $registro['ano_inicio_carreira'] !== null
                ? (int) $registro['ano_inicio_carreira']
                : null,
            'anos_experiencia' => $registro['ano_inicio_carreira'] !== null
                ? max(
                    0,
                    (int) date('Y')
                    - (int) $registro['ano_inicio_carreira']
                )
                : null,
            'servicos' =>
                $servicosIdsPorProfissional[$profissionalId] ?? [],
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