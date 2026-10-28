<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../services/AutoAgendamentoService.php';

header('Content-Type: application/json; charset=utf-8');

function responderJson(array $dados, int $status = 200): never
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

    responderJson([
        'sucesso' => false,
        'mensagem' => 'Método não permitido.',
    ], 405);
}

exigirApiPublicaAutenticada();

$slug = trim((string) ($_GET['slug'] ?? $_GET['empresa'] ?? ''));
$servicoId = filter_var(
    $_GET['servico_id'] ?? null,
    FILTER_VALIDATE_INT
);
$profissionalId = filter_var(
    $_GET['profissional_id'] ?? null,
    FILTER_VALIDATE_INT
);
$data = trim((string) ($_GET['data'] ?? ''));

if (
    $slug === ''
    || !is_int($servicoId)
    || $servicoId <= 0
    || !is_int($profissionalId)
    || $profissionalId <= 0
    || $data === ''
) {
    responderJson([
        'sucesso' => false,
        'mensagem' => 'Parâmetros inválidos.',
    ], 422);
}

$dataObj = DateTimeImmutable::createFromFormat('!Y-m-d', $data);

if (
    !$dataObj
    || $dataObj->format('Y-m-d') !== $data
) {
    responderJson([
        'sucesso' => false,
        'mensagem' => 'Data inválida.',
    ], 422);
}

try {
    $pdo = getDB();

    /*
     * O slug é o identificador público da empresa.
     * O empresa_id permanece exclusivamente no contexto interno.
     */
    $stmt = $pdo->prepare(
        'SELECT id, slug, timezone
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
        responderJson([
            'sucesso' => false,
            'mensagem' => 'Empresa não encontrada.',
        ], 404);
    }

    $empresaId = (int) $empresa['id'];

    /*
     * Confirma que o serviço e o profissional pertencem à empresa
     * e que o serviço está disponível para agendamento online.
     */
    $autoAgendamentoService = new AutoAgendamentoService();

    $servico = $autoAgendamentoService->buscarDadosServico(
        $pdo,
        $empresaId,
        $servicoId,
        $profissionalId
    );

    if (!$servico) {
        responderJson([
            'sucesso' => false,
            'mensagem' => 'Serviço ou profissional indisponível.',
        ], 404);
    }

    /*
     * Usa exatamente o mesmo motor de disponibilidade utilizado
     * pelo fluxo autenticado de autoagendamento.
     */
    $horarios = $autoAgendamentoService->buscarHorariosDisponiveis(
        $pdo,
        $empresaId,
        $servicoId,
        $profissionalId,
        $data
    );

    responderJson([
        'sucesso' => true,
        'empresa' => (string) $empresa['slug'],
        'servico_id' => $servicoId,
        'profissional_id' => $profissionalId,
        'data' => $data,
        'horarios' => array_map(
            static fn(array $horario): array => [
                'hora' => (string) $horario['hora'],
            ],
            $horarios
        ),
    ]);
} catch (Throwable $e) {
    error_log(
        'Erro na API pública de horários: ' . $e->getMessage()
    );

    responderJson([
        'sucesso' => false,
        'mensagem' => 'Não foi possível consultar os horários disponíveis.',
    ], 500);
}