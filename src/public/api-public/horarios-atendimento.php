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

$slug = mb_strtolower(
    trim((string) ($_GET['slug'] ?? $_GET['empresa'] ?? ''))
);

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
     * Resolve a empresa pelo slug público.
     * O empresa_id permanece exclusivamente no servidor.
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

    /*
     * Consulta todos os períodos ativos de funcionamento.
     *
     * Convenção existente no banco:
     * 1 = segunda-feira
     * 2 = terça-feira
     * 3 = quarta-feira
     * 4 = quinta-feira
     * 5 = sexta-feira
     * 6 = sábado
     * 7 = domingo
     */
    $stmtHorarios = $pdo->prepare(
        'SELECT
            dia_semana,
            TIME_FORMAT(MIN(hora_inicio), "%H:%i") AS hora_inicio,
            TIME_FORMAT(MAX(hora_fim), "%H:%i") AS hora_fim
         FROM empresa_horarios
         WHERE empresa_id = :empresa_id
           AND ativo = 1
         GROUP BY dia_semana
         ORDER BY dia_semana ASC'
    );

    $stmtHorarios->execute([
        ':empresa_id' => (int) $empresa['id'],
    ]);

    $registros = $stmtHorarios->fetchAll(PDO::FETCH_ASSOC);

    $nomesDias = [
        1 => 'segunda-feira',
        2 => 'terça-feira',
        3 => 'quarta-feira',
        4 => 'quinta-feira',
        5 => 'sexta-feira',
        6 => 'sábado',
        7 => 'domingo',
    ];

    /*
     * Sempre devolvemos os sete dias.
     *
     * Dessa forma, o consumidor não precisa interpretar a ausência
     * de registros para descobrir se determinado dia está fechado.
     */
    $horarios = [];

    for ($dia = 1; $dia <= 7; $dia++) {
        $horarios[$dia] = [
            'dia_semana' => $dia,
            'dia' => $nomesDias[$dia],
            'aberto' => false,
            'inicio' => null,
            'fim' => null,
        ];
    }

    /*
     * Para o site público, o horário representa a janela comercial
     * de atendimento do estabelecimento. Caso existam vários períodos
     * internos no mesmo dia, usamos a primeira abertura e o último
     * fechamento, sem expor intervalos operacionais (como almoço).
     */
    foreach ($registros as $registro) {
        $dia = (int) $registro['dia_semana'];

        if (!isset($horarios[$dia])) {
            continue;
        }

        $horarios[$dia]['aberto'] = true;
        $horarios[$dia]['inicio'] = $registro['hora_inicio'];
        $horarios[$dia]['fim'] = $registro['hora_fim'];
    }

    responderJson(200, [
        'sucesso' => true,
        'empresa' => $empresa['slug'],
        'horarios' => array_values($horarios),
    ]);
} catch (Throwable $e) {
    error_log(
        'Erro na API pública de horários de atendimento: '
        . $e->getMessage()
    );

    responderJson(500, [
        'sucesso' => false,
        'erro' => 'Não foi possível consultar os horários de atendimento.',
    ]);
}