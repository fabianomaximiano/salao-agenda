<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/cliente-auth.php';

$clientId = (string) (
    $_ENV['GOOGLE_CLIENT_ID'] ??
    getenv('GOOGLE_CLIENT_ID') ?:
    ''
);

$clientSecret = (string) (
    $_ENV['GOOGLE_CLIENT_SECRET'] ??
    getenv('GOOGLE_CLIENT_SECRET') ?:
    ''
);

$redirectUri = (string) (
    $_ENV['GOOGLE_REDIRECT_URI'] ??
    getenv('GOOGLE_REDIRECT_URI') ?:
    'http://localhost:8096/callback.php'
);

$pdo = getDB();

$action = (string) ($_GET['action'] ?? '');

/**
 * Redireciona de volta ao login quando o OAuth falhar.
 *
 * Se houver empresa, preserva o slug.
 * O contexto de agendamento continua armazenado na sessão.
 */
function googleErro(string $slug = ''): never
{
    $url = 'login-cliente.php';

    if ($slug !== '') {
        $url .=
            '?empresa=' .
            rawurlencode($slug) .
            '&erro=google';
    } else {
        $url .= '?erro=google';
    }

    header('Location: ' . $url);
    exit;
}

if ($clientId === '' || $clientSecret === '') {
    error_log('Google OAuth não configurado.');

    googleErro(
        (string) ($_GET['empresa'] ?? '')
    );
}

/*
 * Início do OAuth.
 *
 * O WordPress/login pode trazer:
 *
 * empresa
 * servico_id
 * profissional_id
 *
 * Antes de sair do Salão Agenda, validamos novamente o
 * contexto contra a empresa resolvida pelo slug.
 */
if ($action === 'auth') {
    $slug = trim(
        (string) ($_GET['empresa'] ?? '')
    );

    $empresa = clienteEmpresaPorSlug(
        $pdo,
        $slug
    );

    if (!$empresa) {
        http_response_code(404);
        exit('Empresa não encontrada.');
    }

    $contextoAgendamento =
        clienteAgendamentoCapturarContexto(
            $pdo,
            $empresa,
            $_GET['servico_id'] ?? null,
            $_GET['profissional_id'] ?? null
        );

    $state = bin2hex(
        random_bytes(32)
    );

    /*
     * O state continua sendo a proteção CSRF do OAuth.
     *
     * Além dele, guardamos na sessão qual empresa iniciou
     * o fluxo e o contexto já validado naquele momento.
     */
    $_SESSION['google_oauth_cliente'] = [
        'state' => $state,
        'empresa_id' => (int) $empresa['id'],
        'empresa_slug' => (string) $empresa['slug'],
        'servico_id' =>
            $contextoAgendamento['servico_id'] ?? null,
        'profissional_id' =>
            $contextoAgendamento['profissional_id'] ?? null,
        'criado_em' => time(),
    ];

    $url =
        'https://accounts.google.com/o/oauth2/v2/auth?' .
        http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
            'prompt' => 'select_account',
            'state' => $state,
        ]);

    header('Location: ' . $url);
    exit;
}

/*
 * Google devolveu um erro/cancelamento.
 */
if (isset($_GET['error'])) {
    $slug = (string) (
        $_SESSION['google_oauth_cliente']['empresa_slug'] ??
        ''
    );

    unset(
        $_SESSION['google_oauth_cliente']
    );

    googleErro($slug);
}

/*
 * Callback precisa obrigatoriamente conter code + state.
 */
if (
    !isset(
        $_GET['code'],
        $_GET['state']
    )
) {
    googleErro();
}

$oauth =
    $_SESSION['google_oauth_cliente'] ??
    null;

/*
 * Validação do state e expiração do fluxo OAuth.
 */
if (
    !is_array($oauth) ||
    empty($oauth['state']) ||
    !hash_equals(
        (string) $oauth['state'],
        (string) $_GET['state']
    ) ||
    (int) ($oauth['criado_em'] ?? 0) <
        time() - 600
) {
    $slug = is_array($oauth)
        ? (string) (
            $oauth['empresa_slug'] ??
            ''
        )
        : '';

    unset(
        $_SESSION['google_oauth_cliente']
    );

    googleErro($slug);
}

$slug = (string) $oauth['empresa_slug'];

$empresa = clienteEmpresaPorSlug(
    $pdo,
    $slug
);

/*
 * Removemos o estado temporário específico do OAuth assim
 * que ele foi validado.
 *
 * agendamento_contexto permanece separado na sessão.
 */
unset(
    $_SESSION['google_oauth_cliente']
);

if (
    !$empresa ||
    (int) $empresa['id'] !==
        (int) $oauth['empresa_id']
) {
    googleErro($slug);
}

/*
 * Reconstituímos o contexto usando somente os IDs que haviam
 * sido validados antes do redirecionamento ao Google.
 *
 * Ainda assim, a função valida novamente contra o banco.
 * Isso cobre inclusive alterações ocorridas enquanto o usuário
 * estava no Google.
 */
$contextoAgendamento =
    clienteAgendamentoValidarContexto(
        $pdo,
        (int) $empresa['id'],
        $oauth['servico_id'] ?? null,
        $oauth['profissional_id'] ?? null
    );

clienteAgendamentoSalvarContexto(
    $contextoAgendamento
);

try {
    /*
     * Troca do authorization code pelo access token.
     */
    $ch = curl_init(
        'https://oauth2.googleapis.com/token'
    );

    curl_setopt_array(
        $ch,
        [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS =>
                http_build_query([
                    'code' =>
                        (string) $_GET['code'],

                    'client_id' =>
                        $clientId,

                    'client_secret' =>
                        $clientSecret,

                    'redirect_uri' =>
                        $redirectUri,

                    'grant_type' =>
                        'authorization_code',
                ]),

            CURLOPT_TIMEOUT => 15,
        ]
    );

    $resp = curl_exec($ch);

    $http = (int) curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    if (
        $resp === false ||
        $http < 200 ||
        $http >= 300
    ) {
        throw new RuntimeException(
            'Falha token Google.'
        );
    }

    $token = json_decode(
        (string) $resp,
        true
    );

    if (empty($token['access_token'])) {
        throw new RuntimeException(
            'Token ausente.'
        );
    }

    /*
     * Consulta dos dados básicos da conta Google.
     */
    $ch = curl_init(
        'https://www.googleapis.com/oauth2/v2/userinfo'
    );

    curl_setopt_array(
        $ch,
        [
            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' .
                $token['access_token'],
            ],

            CURLOPT_TIMEOUT => 15,
        ]
    );

    $resp = curl_exec($ch);

    $http = (int) curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    if (
        $resp === false ||
        $http < 200 ||
        $http >= 300
    ) {
        throw new RuntimeException(
            'Falha perfil Google.'
        );
    }

    $google = json_decode(
        (string) $resp,
        true
    );

    $googleId = trim(
        (string) ($google['id'] ?? '')
    );

    $email = mb_strtolower(
        trim(
            (string) ($google['email'] ?? '')
        )
    );

    $nome = trim(
        (string) ($google['name'] ?? '')
    );

    $foto = trim(
        (string) ($google['picture'] ?? '')
    );

    if (
        $googleId === '' ||
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        ) ||
        !(bool) (
            $google['verified_email'] ??
            false
        )
    ) {
        throw new RuntimeException(
            'Google sem e-mail verificado.'
        );
    }

    /*
     * Procura conta global já existente.
     *
     * Prioridade:
     * 1. mesmo google_id;
     * 2. mesmo e-mail.
     */
    $stmt = $pdo->prepare(
        'SELECT
            id,
            email,
            google_id,
            ativo
         FROM usuarios
         WHERE google_id = :google_id
            OR email = :email
         ORDER BY
            CASE
                WHEN google_id = :google_id_ordem
                    THEN 0
                ELSE 1
            END
         LIMIT 1'
    );

    $stmt->execute([
        ':google_id' => $googleId,
        ':email' => $email,
        ':google_id_ordem' => $googleId,
    ]);

    $usuario = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    if ($usuario) {
        if ((int) $usuario['ativo'] !== 1) {
            throw new RuntimeException(
                'Conta desativada.'
            );
        }

        if (
            !empty($usuario['google_id']) &&
            (string) $usuario['google_id'] !==
                $googleId
        ) {
            throw new RuntimeException(
                'E-mail ligado a outro Google.'
            );
        }

        $usuarioId =
            (int) $usuario['id'];

        /*
         * Conta já existia pelo e-mail, mas ainda não tinha
         * google_id associado.
         */
        if (empty($usuario['google_id'])) {
            $stmt = $pdo->prepare(
                'UPDATE usuarios
                 SET
                    google_id = :google_id,
                    foto_url = COALESCE(
                        :foto,
                        foto_url
                    )
                 WHERE id = :id'
            );

            $stmt->execute([
                ':google_id' => $googleId,
                ':foto' => $foto ?: null,
                ':id' => $usuarioId,
            ]);
        }

        /*
         * Verifica se esta identidade global já possui vínculo
         * como cliente da empresa atual.
         */
        $cliente = clienteBuscarVinculo(
            $pdo,
            $usuarioId,
            (int) $empresa['id']
        );

        if ($cliente) {
            if (
                (int) $cliente['cliente_ativo'] !== 1 ||
                (int) $cliente['pessoa_ativa'] !== 1
            ) {
                throw new RuntimeException(
                    'Cliente desativado.'
                );
            }

            clienteIniciarSessao(
                $pdo,
                [
                    'id' => $usuarioId,
                    'email' => $email,
                ],
                $cliente,
                $empresa
            );

            /*
             * Antes:
             *     sempre dashboard-cliente.php
             *
             * Agora:
             *     sem contexto -> dashboard
             *     com contexto -> agendar.php
             */
            header(
                'Location: ' .
                clienteAgendamentoDestinoAposAutenticacao(
                    (int) $empresa['id']
                )
            );

            exit;
        }

        /*
         * Conta global encontrada, mas ainda sem vínculo de
         * cliente com esta empresa.
         */
        $_SESSION['cliente_cadastro_pendente'] = [
            'origem' => 'google',
            'usuario_id' => $usuarioId,
            'email' => $email,
            'nome' => $nome,
            'google_id' => $googleId,
            'foto_url' => $foto ?: null,
            'empresa_id' => (int) $empresa['id'],
            'empresa_slug' => $slug,
        ];
    } else {
        /*
         * Ainda não existe conta global.
         *
         * completar-cadastro-cliente.php criará a identidade
         * e o vínculo necessários.
         */
        $_SESSION['cliente_cadastro_pendente'] = [
            'origem' => 'google',
            'usuario_id' => 0,
            'email' => $email,
            'nome' => $nome,
            'google_id' => $googleId,
            'foto_url' => $foto ?: null,
            'empresa_id' => (int) $empresa['id'],
            'empresa_slug' => $slug,
        ];
    }

    /*
     * O contexto não precisa aparecer novamente na URL.
     * Ele permanece validado em agendamento_contexto.
     */
    header(
        'Location: completar-cadastro-cliente.php?empresa=' .
        rawurlencode($slug)
    );

    exit;
} catch (Throwable $e) {
    error_log(
        'Erro OAuth cliente: ' .
        $e->getMessage()
    );

    googleErro($slug);
}