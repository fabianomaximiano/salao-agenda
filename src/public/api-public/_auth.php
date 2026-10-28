<?php

declare(strict_types=1);

/**
 * Autenticação servidor -> servidor da API pública.
 *
 * A chave deve existir somente no ambiente do Salão Agenda:
 * SALAO_AGENDA_API_KEY=<segredo>
 *
 * Consumidores enviam:
 * Authorization: Bearer <segredo>
 */
function exigirApiPublicaAutenticada(): void
{
    $chaveEsperada = trim((string) (
        getenv('SALAO_AGENDA_API_KEY')
        ?: ($_ENV['SALAO_AGENDA_API_KEY'] ?? '')
        ?: ($_SERVER['SALAO_AGENDA_API_KEY'] ?? '')
    ));

    if ($chaveEsperada === '') {
        error_log('API pública indisponível: SALAO_AGENDA_API_KEY não configurada.');
        responderApiPublicaAuth(503, 'Serviço temporariamente indisponível.');
    }

    $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));

    if ($authorization === '' && function_exists('getallheaders')) {
        $headers = getallheaders();

        if (is_array($headers)) {
            foreach ($headers as $nome => $valor) {
                if (strcasecmp((string) $nome, 'Authorization') === 0) {
                    $authorization = trim((string) $valor);
                    break;
                }
            }
        }
    }

    if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
        responderApiPublicaAuth(401, 'Não autorizado.');
    }

    $chaveRecebida = trim((string) $matches[1]);

    if ($chaveRecebida === '' || !hash_equals($chaveEsperada, $chaveRecebida)) {
        responderApiPublicaAuth(401, 'Não autorizado.');
    }
}

function responderApiPublicaAuth(int $status, string $mensagem): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    echo json_encode(
        [
            'sucesso' => false,
            'erro' => $mensagem,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}
