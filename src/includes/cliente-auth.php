<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function clienteEmpresaPorSlug(PDO $pdo, string $slug): ?array
{
    if ($slug === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, nome_fantasia, slug, logo_url
         FROM empresas
         WHERE slug = :slug
           AND ativo = 1
         LIMIT 1'
    );

    $stmt->execute([
        ':slug' => $slug,
    ]);

    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    return $empresa ?: null;
}

function clienteSomenteDigitos(string $valor): string
{
    return preg_replace('/\D+/', '', $valor) ?? '';
}

function clienteDataValida(string $data): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $data);

    return $dt instanceof DateTime && $dt->format('Y-m-d') === $data;
}

function clienteCpfFormatado(string $valor): string
{
    $d = clienteSomenteDigitos($valor);

    return strlen($d) === 11
        ? substr($d, 0, 3) . '.' .
          substr($d, 3, 3) . '.' .
          substr($d, 6, 3) . '-' .
          substr($d, 9, 2)
        : $valor;
}

function clienteIdentidadePorUsuario(PDO $pdo, int $usuarioId, bool $travar = false): ?array
{
    $sql =
        'SELECT id, usuario_id, cpf, email
         FROM clientes_identidades
         WHERE usuario_id = :usuario_id
         LIMIT 1' .
        ($travar ? ' FOR UPDATE' : '');

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':usuario_id' => $usuarioId,
    ]);

    $identidade = $stmt->fetch(PDO::FETCH_ASSOC);

    return $identidade ?: null;
}

function clienteBuscarVinculo(PDO $pdo, int $usuarioId, int $empresaId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT
            c.id AS cliente_id,
            c.pessoa_id,
            c.ativo AS cliente_ativo,
            p.nome_completo,
            p.ativo AS pessoa_ativa
         FROM clientes c
         INNER JOIN pessoas p
            ON p.id = c.pessoa_id
           AND p.empresa_id = c.empresa_id
         INNER JOIN clientes_identidades ci
            ON ci.id = c.cliente_identidade_id
         WHERE ci.usuario_id = :usuario_id
           AND c.empresa_id = :empresa_id
         LIMIT 1'
    );

    $stmt->execute([
        ':usuario_id' => $usuarioId,
        ':empresa_id' => $empresaId,
    ]);

    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    return $cliente ?: null;
}

/**
 * Converte um valor externo em um ID inteiro positivo.
 *
 * IDs inválidos retornam null e nunca são usados diretamente
 * em consultas do contexto de agendamento.
 */
function clienteAgendamentoNormalizarId(mixed $valor): ?int
{
    if ($valor === null || $valor === '') {
        return null;
    }

    if (is_int($valor)) {
        return $valor > 0 ? $valor : null;
    }

    if (!is_string($valor) || !ctype_digit($valor)) {
        return null;
    }

    $id = (int) $valor;

    return $id > 0 ? $id : null;
}

/**
 * Verifica se o serviço pode participar do fluxo de
 * autoagendamento da empresa informada.
 *
 * Além do próprio serviço estar ativo e disponível online,
 * precisa existir ao menos um profissional ativo da mesma
 * empresa vinculado a ele.
 */
function clienteAgendamentoServicoValido(
    PDO $pdo,
    int $empresaId,
    int $servicoId
): bool {
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM servicos s
         INNER JOIN profissional_servicos ps
            ON ps.servico_id = s.id
           AND ps.ativo = 1
         INNER JOIN profissionais pr
            ON pr.id = ps.profissional_id
           AND pr.empresa_id = s.empresa_id
           AND pr.ativo = 1
         INNER JOIN pessoas p
            ON p.id = pr.pessoa_id
           AND p.empresa_id = pr.empresa_id
           AND p.ativo = 1
         WHERE s.id = :servico_id
           AND s.empresa_id = :empresa_id
           AND s.ativo = 1
           AND s.permite_agendamento_online = 1
         LIMIT 1'
    );

    $stmt->execute([
        ':servico_id' => $servicoId,
        ':empresa_id' => $empresaId,
    ]);

    return (bool) $stmt->fetchColumn();
}

/**
 * Verifica se o profissional está ativo e pertence
 * à empresa resolvida pelo slug.
 *
 * Também exige pessoa ativa e ao menos um serviço ativo
 * disponível para agendamento online.
 */
function clienteAgendamentoProfissionalValido(
    PDO $pdo,
    int $empresaId,
    int $profissionalId
): bool {
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM profissionais pr
         INNER JOIN pessoas p
            ON p.id = pr.pessoa_id
           AND p.empresa_id = pr.empresa_id
           AND p.ativo = 1
         INNER JOIN profissional_servicos ps
            ON ps.profissional_id = pr.id
           AND ps.ativo = 1
         INNER JOIN servicos s
            ON s.id = ps.servico_id
           AND s.empresa_id = pr.empresa_id
           AND s.ativo = 1
           AND s.permite_agendamento_online = 1
         WHERE pr.id = :profissional_id
           AND pr.empresa_id = :empresa_id
           AND pr.ativo = 1
         LIMIT 1'
    );

    $stmt->execute([
        ':profissional_id' => $profissionalId,
        ':empresa_id' => $empresaId,
    ]);

    return (bool) $stmt->fetchColumn();
}

/**
 * Valida a combinação serviço + profissional.
 *
 * Os dois registros precisam pertencer à mesma empresa,
 * estar ativos e possuir vínculo ativo em profissional_servicos.
 */
function clienteAgendamentoCombinacaoValida(
    PDO $pdo,
    int $empresaId,
    int $servicoId,
    int $profissionalId
): bool {
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM profissional_servicos ps
         INNER JOIN profissionais pr
            ON pr.id = ps.profissional_id
           AND pr.ativo = 1
         INNER JOIN pessoas p
            ON p.id = pr.pessoa_id
           AND p.empresa_id = pr.empresa_id
           AND p.ativo = 1
         INNER JOIN servicos s
            ON s.id = ps.servico_id
           AND s.empresa_id = pr.empresa_id
           AND s.ativo = 1
           AND s.permite_agendamento_online = 1
         WHERE pr.empresa_id = :empresa_id
           AND ps.servico_id = :servico_id
           AND ps.profissional_id = :profissional_id
           AND ps.ativo = 1
         LIMIT 1'
    );

    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':servico_id' => $servicoId,
        ':profissional_id' => $profissionalId,
    ]);

    return (bool) $stmt->fetchColumn();
}

/**
 * Recebe contexto externo de agendamento e devolve somente
 * os IDs que forem válidos dentro da empresa informada.
 *
 * Regras:
 *
 * - serviço inválido é ignorado;
 * - profissional inválido é ignorado;
 * - nenhum ID de outra empresa é aceito;
 * - quando serviço e profissional são válidos individualmente,
 *   mas incompatíveis entre si, o serviço é preservado e o
 *   profissional é descartado.
 */
function clienteAgendamentoValidarContexto(
    PDO $pdo,
    int $empresaId,
    mixed $servicoRecebido = null,
    mixed $profissionalRecebido = null
): array {
    $servicoId = clienteAgendamentoNormalizarId($servicoRecebido);
    $profissionalId = clienteAgendamentoNormalizarId($profissionalRecebido);

    if (
        $servicoId !== null &&
        !clienteAgendamentoServicoValido($pdo, $empresaId, $servicoId)
    ) {
        $servicoId = null;
    }

    if (
        $profissionalId !== null &&
        !clienteAgendamentoProfissionalValido($pdo, $empresaId, $profissionalId)
    ) {
        $profissionalId = null;
    }

    if (
        $servicoId !== null &&
        $profissionalId !== null &&
        !clienteAgendamentoCombinacaoValida(
            $pdo,
            $empresaId,
            $servicoId,
            $profissionalId
        )
    ) {
        /*
         * O fluxo atual do agendador começa pelo serviço.
         * Mantemos o serviço válido e descartamos somente
         * o profissional incompatível.
         */
        $profissionalId = null;
    }

    return [
        'empresa_id' => $empresaId,
        'servico_id' => $servicoId,
        'profissional_id' => $profissionalId,
    ];
}

/**
 * Salva na sessão somente um contexto previamente validado.
 *
 * Se não houver serviço nem profissional válidos, não existe
 * contexto especial de agendamento e qualquer contexto antigo
 * da mesma entrada é removido.
 */
function clienteAgendamentoSalvarContexto(array $contexto): void
{
    $empresaId = (int) ($contexto['empresa_id'] ?? 0);

    $servicoId = clienteAgendamentoNormalizarId(
        $contexto['servico_id'] ?? null
    );

    $profissionalId = clienteAgendamentoNormalizarId(
        $contexto['profissional_id'] ?? null
    );

    if (
        $empresaId <= 0 ||
        ($servicoId === null && $profissionalId === null)
    ) {
        unset($_SESSION['agendamento_contexto']);
        return;
    }

    $_SESSION['agendamento_contexto'] = [
        'empresa_id' => $empresaId,
        'servico_id' => $servicoId,
        'profissional_id' => $profissionalId,
    ];
}

/**
 * Valida o contexto recebido e, se houver ao menos uma
 * escolha válida, armazena na sessão.
 *
 * Esta função será usada pelos pontos de entrada HTTP,
 * principalmente login-cliente.php.
 */
function clienteAgendamentoCapturarContexto(
    PDO $pdo,
    array $empresa,
    mixed $servicoRecebido = null,
    mixed $profissionalRecebido = null
): array {
    $empresaId = (int) ($empresa['id'] ?? 0);

    if ($empresaId <= 0) {
        unset($_SESSION['agendamento_contexto']);

        return [
            'empresa_id' => 0,
            'servico_id' => null,
            'profissional_id' => null,
        ];
    }

    $contexto = clienteAgendamentoValidarContexto(
        $pdo,
        $empresaId,
        $servicoRecebido,
        $profissionalRecebido
    );

    clienteAgendamentoSalvarContexto($contexto);

    return $contexto;
}

/**
 * Recupera o contexto somente quando ele pertence à empresa
 * da sessão/fluxo atual.
 *
 * Isso evita reutilizar acidentalmente contexto deixado por
 * outra empresa.
 */
function clienteAgendamentoObterContexto(int $empresaId): ?array
{
    $contexto = $_SESSION['agendamento_contexto'] ?? null;

    if (!is_array($contexto)) {
        return null;
    }

    if ((int) ($contexto['empresa_id'] ?? 0) !== $empresaId) {
        unset($_SESSION['agendamento_contexto']);
        return null;
    }

    $servicoId = clienteAgendamentoNormalizarId(
        $contexto['servico_id'] ?? null
    );

    $profissionalId = clienteAgendamentoNormalizarId(
        $contexto['profissional_id'] ?? null
    );

    if ($servicoId === null && $profissionalId === null) {
        unset($_SESSION['agendamento_contexto']);
        return null;
    }

    return [
        'empresa_id' => $empresaId,
        'servico_id' => $servicoId,
        'profissional_id' => $profissionalId,
    ];
}

/**
 * Informa se existe contexto de agendamento válido armazenado
 * para a empresa atual.
 */
function clienteAgendamentoTemContexto(int $empresaId): bool
{
    return clienteAgendamentoObterContexto($empresaId) !== null;
}

/**
 * Define o destino pós-autenticação.
 *
 * Sem contexto:
 * dashboard-cliente.php
 *
 * Com contexto:
 * agendar.php
 */
function clienteAgendamentoDestinoAposAutenticacao(int $empresaId): string
{
    return clienteAgendamentoTemContexto($empresaId)
        ? 'agendar.php'
        : 'dashboard-cliente.php';
}

/**
 * Remove explicitamente o contexto de agendamento.
 *
 * Não deve ser chamado durante o login, pois o contexto precisa
 * sobreviver à autenticação, cadastro e redirects.
 */
function clienteAgendamentoLimparContexto(): void
{
    unset($_SESSION['agendamento_contexto']);
}

function clienteIniciarSessao(
    PDO $pdo,
    array $usuario,
    array $cliente,
    array $empresa
): void {
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $usuario['id'];
    $_SESSION['user_email'] = (string) $usuario['email'];
    $_SESSION['user_name'] = (string) $cliente['nome_completo'];

    $_SESSION['empresa_id'] = (int) $empresa['id'];
    $_SESSION['empresa_nome'] = (string) $empresa['nome_fantasia'];
    $_SESSION['cliente_id'] = (int) $cliente['cliente_id'];
    $_SESSION['pessoa_id'] = (int) $cliente['pessoa_id'];
    $_SESSION['contexto'] = 'cliente';

    /*
     * agendamento_contexto NÃO é removido aqui.
     *
     * Ele precisa sobreviver ao session_regenerate_id(true)
     * e ao processo de autenticação para ser consumido
     * posteriormente por agendar.php.
     */
    unset(
        $_SESSION['administrador_id'],
        $_SESSION['profissional_id'],
        $_SESSION['colaborador_id'],
        $_SESSION['permissoes_colaborador'],
        $_SESSION['cliente_cadastro_pendente']
    );

    $stmt = $pdo->prepare(
        'UPDATE usuarios
         SET ultimo_acesso_em = NOW()
         WHERE id = :id'
    );

    $stmt->execute([
        ':id' => (int) $usuario['id'],
    ]);
}

function clienteSalvarPerfil(
    PDO $pdo,
    array $empresa,
    int $usuarioId,
    string $email,
    array $dados
): array {
    $empresaId = (int) $empresa['id'];
    $cpf = clienteCpfFormatado((string) $dados['cpf']);
    $email = mb_strtolower(trim($email));

    $identidade = clienteIdentidadePorUsuario(
        $pdo,
        $usuarioId,
        true
    );

    $stmt = $pdo->prepare(
        'SELECT id, usuario_id, email
         FROM clientes_identidades
         WHERE cpf = :cpf
         LIMIT 1
         FOR UPDATE'
    );

    $stmt->execute([
        ':cpf' => $cpf,
    ]);

    $porCpf = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        $identidade &&
        $porCpf &&
        (int) $identidade['id'] !== (int) $porCpf['id']
    ) {
        throw new RuntimeException(
            'O CPF informado pertence a outra conta de cliente.'
        );
    }

    if (
        $identidade &&
        (string) $identidade['cpf'] !== $cpf
    ) {
        throw new RuntimeException(
            'O CPF informado não corresponde à sua conta.'
        );
    }

    if (!$identidade && $porCpf) {
        if (
            !empty($porCpf['usuario_id']) &&
            (int) $porCpf['usuario_id'] !== $usuarioId
        ) {
            throw new RuntimeException(
                'Este CPF já está vinculado a outra conta de acesso.'
            );
        }

        if (
            !empty($porCpf['email']) &&
            mb_strtolower((string) $porCpf['email']) !== $email
        ) {
            throw new RuntimeException(
                'Este CPF já está cadastrado com outro e-mail.'
            );
        }

        $stmt = $pdo->prepare(
            'UPDATE clientes_identidades
             SET usuario_id = :usuario_id,
                 email = :email
             WHERE id = :id'
        );

        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':email' => $email,
            ':id' => (int) $porCpf['id'],
        ]);

        $identidade = [
            'id' => (int) $porCpf['id'],
            'usuario_id' => $usuarioId,
            'cpf' => $cpf,
            'email' => $email,
        ];
    }

    if (!$identidade) {
        $stmt = $pdo->prepare(
            'INSERT INTO clientes_identidades
                (usuario_id, cpf, email)
             VALUES
                (:usuario_id, :cpf, :email)'
        );

        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':cpf' => $cpf,
            ':email' => $email,
        ]);

        $identidade = [
            'id' => (int) $pdo->lastInsertId(),
            'usuario_id' => $usuarioId,
            'cpf' => $cpf,
            'email' => $email,
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM clientes
         WHERE empresa_id = :empresa_id
           AND cliente_identidade_id = :cliente_identidade_id
         LIMIT 1
         FOR UPDATE'
    );

    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':cliente_identidade_id' => (int) $identidade['id'],
    ]);

    if ($stmt->fetch()) {
        throw new RuntimeException(
            'Seu cadastro de cliente já existe neste estabelecimento.'
        );
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM pessoas
         WHERE empresa_id = :empresa_id
           AND cpf = :cpf
         LIMIT 1
         FOR UPDATE'
    );

    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':cpf' => $cpf,
    ]);

    $pessoa = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pessoa) {
        $pessoaId = (int) $pessoa['id'];

        $stmt = $pdo->prepare(
            'SELECT
                id,
                usuario_id,
                cliente_identidade_id
             FROM clientes
             WHERE empresa_id = :empresa_id
               AND pessoa_id = :pessoa_id
             LIMIT 1
             FOR UPDATE'
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':pessoa_id' => $pessoaId,
        ]);

        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cliente) {
            throw new RuntimeException(
                'Já existe uma pessoa com este CPF, mas sem cadastro de cliente. Procure o estabelecimento.'
            );
        }

        if (
            !empty($cliente['cliente_identidade_id']) &&
            (int) $cliente['cliente_identidade_id'] !==
                (int) $identidade['id']
        ) {
            throw new RuntimeException(
                'Este CPF já está vinculado a outra identidade.'
            );
        }

        $stmt = $pdo->prepare(
            'UPDATE clientes
             SET usuario_id = :usuario_id,
                 cliente_identidade_id = :cliente_identidade_id,
                 ativo = 1
             WHERE id = :id'
        );

        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':cliente_identidade_id' => (int) $identidade['id'],
            ':id' => (int) $cliente['id'],
        ]);

        $clienteId = (int) $cliente['id'];
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO pessoas
                (
                    empresa_id,
                    nome_completo,
                    cpf,
                    data_nascimento,
                    genero,
                    email,
                    ativo
                )
             VALUES
                (
                    :empresa_id,
                    :nome_completo,
                    :cpf,
                    :data_nascimento,
                    :genero,
                    :email,
                    1
                )'
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':nome_completo' => $dados['nome_completo'],
            ':cpf' => $cpf,
            ':data_nascimento' => $dados['data_nascimento'],
            ':genero' => $dados['genero'],
            ':email' => $email,
        ]);

        $pessoaId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO clientes
                (
                    empresa_id,
                    cliente_identidade_id,
                    pessoa_id,
                    usuario_id,
                    ativo
                )
             VALUES
                (
                    :empresa_id,
                    :cliente_identidade_id,
                    :pessoa_id,
                    :usuario_id,
                    1
                )'
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':cliente_identidade_id' => (int) $identidade['id'],
            ':pessoa_id' => $pessoaId,
            ':usuario_id' => $usuarioId,
        ]);

        $clienteId = (int) $pdo->lastInsertId();
    }

    $stmt = $pdo->prepare(
        'UPDATE pessoas
         SET nome_completo = :nome_completo,
             data_nascimento = :data_nascimento,
             genero = :genero,
             email = :email,
             ativo = 1
         WHERE id = :pessoa_id
           AND empresa_id = :empresa_id'
    );

    $stmt->execute([
        ':nome_completo' => $dados['nome_completo'],
        ':data_nascimento' => $dados['data_nascimento'],
        ':genero' => $dados['genero'],
        ':email' => $email,
        ':pessoa_id' => $pessoaId,
        ':empresa_id' => $empresaId,
    ]);

    $stmt = $pdo->prepare(
        'SELECT id
         FROM telefones_pessoa
         WHERE pessoa_id = :pessoa_id
           AND principal = 1
         LIMIT 1
         FOR UPDATE'
    );

    $stmt->execute([
        ':pessoa_id' => $pessoaId,
    ]);

    $telefoneId = $stmt->fetchColumn();

    if ($telefoneId) {
        $stmt = $pdo->prepare(
            'UPDATE telefones_pessoa
             SET numero = :numero,
                 tipo = "celular",
                 whatsapp = 1,
                 principal = 1
             WHERE id = :id'
        );

        $stmt->execute([
            ':numero' => $dados['celular'],
            ':id' => $telefoneId,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO telefones_pessoa
                (
                    pessoa_id,
                    numero,
                    tipo,
                    whatsapp,
                    principal
                )
             VALUES
                (
                    :pessoa_id,
                    :numero,
                    "celular",
                    1,
                    1
                )'
        );

        $stmt->execute([
            ':pessoa_id' => $pessoaId,
            ':numero' => $dados['celular'],
        ]);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO enderecos_pessoa
            (
                pessoa_id,
                cep,
                logradouro,
                numero,
                complemento,
                bairro,
                cidade,
                estado
            )
         VALUES
            (
                :pessoa_id,
                :cep,
                :logradouro,
                :numero,
                :complemento,
                :bairro,
                :cidade,
                :estado
            )
         ON DUPLICATE KEY UPDATE
            cep = VALUES(cep),
            logradouro = VALUES(logradouro),
            numero = VALUES(numero),
            complemento = VALUES(complemento),
            bairro = VALUES(bairro),
            cidade = VALUES(cidade),
            estado = VALUES(estado)'
    );

    $stmt->execute([
        ':pessoa_id' => $pessoaId,
        ':cep' => $dados['cep'] ?: null,
        ':logradouro' => $dados['logradouro'] ?: null,
        ':numero' => $dados['numero'] ?: null,
        ':complemento' => $dados['complemento'] ?: null,
        ':bairro' => $dados['bairro'] ?: null,
        ':cidade' => $dados['cidade'] ?: null,
        ':estado' => $dados['estado'] ?: null,
    ]);

    return [
        'cliente_id' => $clienteId,
        'pessoa_id' => $pessoaId,
        'nome_completo' => $dados['nome_completo'],
    ];
}