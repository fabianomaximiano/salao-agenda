<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

exigirAdministrador();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método não permitido.');
}

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();

$acao = trim((string) ($_POST['acao'] ?? 'criar'));
$csrfRecebido = (string) ($_POST['csrf_token'] ?? '');

if ($acao === 'alternar_status') {
    $csrfSessao = (string) ($_SESSION['csrf_servicos'] ?? '');
} else {
    $csrfSessao = (string) ($_SESSION['csrf_cadastro_servico'] ?? '');
}

if ($csrfSessao === '' || $csrfRecebido === '' || !hash_equals($csrfSessao, $csrfRecebido)) {
    http_response_code(403);
    exit('Token CSRF inválido.');
}

function redirecionarCadastro(?int $servicoId = null): never
{
    $destino = '../cadastro-servico.php';

    if ($servicoId !== null && $servicoId > 0) {
        $destino .= '?editar=' . $servicoId;
    }

    header('Location: ' . $destino);
    exit;
}

function redirecionarLista(): never
{
    header('Location: ../servicos.php');
    exit;
}

function definirFlashFormulario(
    string $tipo,
    string $mensagem,
    array $erros = [],
    array $old = []
): void {
    $_SESSION['flash_servico'] = [
        'tipo' => $tipo,
        'mensagem' => $mensagem,
        'erros' => $erros,
        'old' => $old,
    ];
}

function definirFlashLista(string $tipo, string $mensagem): void
{
    $_SESSION['flash_lista_servicos'] = [
        'tipo' => $tipo,
        'mensagem' => $mensagem,
    ];
}

function normalizarPreco(string $valor): ?string
{
    $valor = trim($valor);

    if ($valor === '') {
        return null;
    }

    $valor = preg_replace('/[^\d,.\-]/u', '', $valor) ?? '';

    if ($valor === '' || $valor === '-') {
        return null;
    }

    if (str_contains($valor, ',') && str_contains($valor, '.')) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    } elseif (str_contains($valor, ',')) {
        $valor = str_replace(',', '.', $valor);
    }

    if (!is_numeric($valor)) {
        return null;
    }

    $numero = (float) $valor;

    if ($numero < 0 || $numero > 99999999.99) {
        return null;
    }

    return number_format($numero, 2, '.', '');
}

if ($acao === 'alternar_status') {
    $servicoId = filter_input(INPUT_POST, 'servico_id', FILTER_VALIDATE_INT);

    if (!$servicoId) {
        definirFlashLista('danger', 'Serviço inválido.');
        redirecionarLista();
    }

    $stmt = $pdo->prepare(
        'SELECT id, ativo
         FROM servicos
         WHERE id = :id
           AND empresa_id = :empresa_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $servicoId,
        ':empresa_id' => $empresaId,
    ]);

    $servico = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$servico) {
        definirFlashLista('danger', 'Serviço não encontrado.');
        redirecionarLista();
    }

    $novoStatus = (int) $servico['ativo'] === 1 ? 0 : 1;

    $stmtUpdate = $pdo->prepare(
        'UPDATE servicos
         SET ativo = :ativo
         WHERE id = :id
           AND empresa_id = :empresa_id'
    );
    $stmtUpdate->execute([
        ':ativo' => $novoStatus,
        ':id' => $servicoId,
        ':empresa_id' => $empresaId,
    ]);

    $_SESSION['csrf_servicos'] = bin2hex(random_bytes(32));

    definirFlashLista(
        'success',
        $novoStatus === 1
            ? 'Serviço ativado com sucesso.'
            : 'Serviço desativado com sucesso.'
    );

    redirecionarLista();
}

if ($acao === 'adicionar_sugeridos') {
    $idsRecebidos = $_POST['servicos_base_ids'] ?? [];

    if (!is_array($idsRecebidos)) {
        definirFlashFormulario('danger', 'Seleção de serviços inválida.');
        redirecionarCadastro();
    }

    $ids = [];

    foreach ($idsRecebidos as $idRaw) {
        $id = filter_var($idRaw, FILTER_VALIDATE_INT);

        if ($id !== false && $id > 0) {
            $ids[$id] = $id;
        }
    }

    $ids = array_values($ids);

    if (!$ids) {
        definirFlashFormulario('danger', 'Selecione pelo menos um serviço sugerido.');
        redirecionarCadastro();
    }

    if (count($ids) > 50) {
        definirFlashFormulario('danger', 'Selecione no máximo 50 serviços por vez.');
        redirecionarCadastro();
    }

    $placeholders = [];
    $params = [
        ':empresa_id' => $empresaId,
    ];

    foreach ($ids as $indice => $id) {
        $chave = ':id_' . $indice;
        $placeholders[] = $chave;
        $params[$chave] = $id;
    }

    $sql =
        'SELECT DISTINCT
            sb.id,
            sb.nome,
            sb.duracao_sugerida
         FROM servicos_base sb
         INNER JOIN empresa_segmentos es
            ON es.segmento_id = sb.segmento_id
           AND es.empresa_id = :empresa_id
         INNER JOIN segmentos seg
            ON seg.id = sb.segmento_id
           AND seg.ativo = 1
         WHERE sb.ativo = 1
           AND sb.id IN (' . implode(', ', $placeholders) . ')
         ORDER BY sb.id ASC';

    $stmtSugestoes = $pdo->prepare($sql);
    $stmtSugestoes->execute($params);
    $sugestoes = $stmtSugestoes->fetchAll(PDO::FETCH_ASSOC);

    if (!$sugestoes) {
        definirFlashFormulario(
            'danger',
            'Nenhum dos serviços selecionados pertence ao ramo desta empresa.'
        );
        redirecionarCadastro();
    }

    $stmtExiste = $pdo->prepare(
        'SELECT id
         FROM servicos
         WHERE empresa_id = :empresa_id
           AND nome = :nome
         LIMIT 1'
    );

    $stmtInsert = $pdo->prepare(
        'INSERT INTO servicos (
            empresa_id,
            categoria_id,
            nome,
            descricao,
            duracao_minutos,
            intervalo_minutos,
            preco,
            permite_agendamento_online,
            ativo
        ) VALUES (
            :empresa_id,
            NULL,
            :nome,
            NULL,
            :duracao_minutos,
            0,
            0.00,
            0,
            1
        )'
    );

    $adicionados = 0;
    $ignorados = 0;

    $pdo->beginTransaction();

    try {
        foreach ($sugestoes as $sugestao) {
            $stmtExiste->execute([
                ':empresa_id' => $empresaId,
                ':nome' => $sugestao['nome'],
            ]);

            if ($stmtExiste->fetchColumn()) {
                $ignorados++;
                continue;
            }

            $stmtInsert->execute([
                ':empresa_id' => $empresaId,
                ':nome' => $sugestao['nome'],
                ':duracao_minutos' => (int) $sugestao['duracao_sugerida'],
            ]);

            $adicionados++;
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    $_SESSION['csrf_cadastro_servico'] = bin2hex(random_bytes(32));

    if ($adicionados === 0) {
        definirFlashFormulario(
            'danger',
            'Os serviços selecionados já estavam cadastrados.'
        );
        redirecionarCadastro();
    }

    $mensagem = $adicionados === 1
        ? '1 serviço sugerido foi adicionado.'
        : $adicionados . ' serviços sugeridos foram adicionados.';

    if ($ignorados > 0) {
        $mensagem .= ' ' . $ignorados . ' já existia(m) e foi(ram) ignorado(s).';
    }

    $mensagem .= ' Revise preço, duração e demais configurações antes de liberar o agendamento online.';

    definirFlashFormulario('success', $mensagem);
    redirecionarCadastro();
}

if (!in_array($acao, ['criar', 'atualizar'], true)) {
    http_response_code(400);
    exit('Ação inválida.');
}

$servicoId = null;
$servicoAtual = null;

if ($acao === 'atualizar') {
    $servicoId = filter_input(INPUT_POST, 'servico_id', FILTER_VALIDATE_INT);

    if (!$servicoId) {
        definirFlashLista('danger', 'Serviço inválido.');
        redirecionarLista();
    }

    $stmtServicoAtual = $pdo->prepare(
        'SELECT
            id,
            categoria_id,
            ativo
         FROM servicos
         WHERE id = :id
           AND empresa_id = :empresa_id
         LIMIT 1'
    );
    $stmtServicoAtual->execute([
        ':id' => $servicoId,
        ':empresa_id' => $empresaId,
    ]);

    $servicoAtual = $stmtServicoAtual->fetch(PDO::FETCH_ASSOC);

    if (!$servicoAtual) {
        definirFlashLista('danger', 'Serviço não encontrado.');
        redirecionarLista();
    }
}

$nome = trim((string) ($_POST['nome'] ?? ''));
$categoriaIdRaw = trim((string) ($_POST['categoria_id'] ?? ''));
$descricao = trim((string) ($_POST['descricao'] ?? ''));
$duracaoRaw = trim((string) ($_POST['duracao_minutos'] ?? ''));
$intervaloRaw = trim((string) ($_POST['intervalo_minutos'] ?? '0'));
$precoRaw = trim((string) ($_POST['preco'] ?? ''));
$permiteAgendamentoOnline = isset($_POST['permite_agendamento_online']) ? 1 : 0;

$old = [
    'nome' => $nome,
    'categoria_id' => $categoriaIdRaw,
    'descricao' => $descricao,
    'duracao_minutos' => $duracaoRaw,
    'intervalo_minutos' => $intervaloRaw,
    'preco' => $precoRaw,
    'permite_agendamento_online' => $permiteAgendamentoOnline,
];

$erros = [];

if ($nome === '') {
    $erros['nome'] = 'Informe o nome do serviço.';
} elseif (mb_strlen($nome) > 150) {
    $erros['nome'] = 'O nome do serviço deve ter no máximo 150 caracteres.';
}

if (mb_strlen($descricao) > 3000) {
    $erros['descricao'] = 'A descrição deve ter no máximo 3000 caracteres.';
}

$duracao = filter_var($duracaoRaw, FILTER_VALIDATE_INT);
if ($duracao === false || $duracao < 5 || $duracao > 1440) {
    $erros['duracao_minutos'] = 'Informe uma duração entre 5 e 1440 minutos.';
}

$intervalo = filter_var(
    $intervaloRaw === '' ? '0' : $intervaloRaw,
    FILTER_VALIDATE_INT
);
if ($intervalo === false || $intervalo < 0 || $intervalo > 240) {
    $erros['intervalo_minutos'] = 'Informe um intervalo entre 0 e 240 minutos.';
}

$categoriaId = null;

if ($categoriaIdRaw !== '') {
    $categoriaId = filter_var($categoriaIdRaw, FILTER_VALIDATE_INT);

    if ($categoriaId === false || $categoriaId <= 0) {
        $erros['categoria_id'] = 'Categoria inválida.';
    } else {
        $stmtCategoria = $pdo->prepare(
            'SELECT id, ativo
             FROM categorias_servicos
             WHERE id = :id
               AND empresa_id = :empresa_id
             LIMIT 1'
        );
        $stmtCategoria->execute([
            ':id' => $categoriaId,
            ':empresa_id' => $empresaId,
        ]);

        $categoria = $stmtCategoria->fetch(PDO::FETCH_ASSOC);

        if (!$categoria) {
            $erros['categoria_id'] = 'A categoria selecionada não pertence a esta empresa.';
        } elseif ((int) $categoria['ativo'] !== 1) {
            $categoriaAtualId = $servicoAtual !== null && $servicoAtual['categoria_id'] !== null
                ? (int) $servicoAtual['categoria_id']
                : null;

            if ($acao !== 'atualizar' || $categoriaAtualId !== (int) $categoriaId) {
                $erros['categoria_id'] = 'A categoria selecionada está inativa.';
            }
        }
    }
}

$preco = normalizarPreco($precoRaw);
if ($preco === null) {
    $erros['preco'] = 'Informe um preço válido.';
}

if (!$erros) {
    $sqlDuplicado =
        'SELECT id
         FROM servicos
         WHERE empresa_id = :empresa_id
           AND nome = :nome';

    $paramsDuplicado = [
        ':empresa_id' => $empresaId,
        ':nome' => $nome,
    ];

    if ($acao === 'atualizar') {
        $sqlDuplicado .= ' AND id <> :id';
        $paramsDuplicado[':id'] = $servicoId;
    }

    $sqlDuplicado .= ' LIMIT 1';

    $stmtDuplicado = $pdo->prepare($sqlDuplicado);
    $stmtDuplicado->execute($paramsDuplicado);

    if ($stmtDuplicado->fetchColumn()) {
        $erros['nome'] = 'Já existe um serviço com esse nome.';
    }
}

if ($erros) {
    definirFlashFormulario(
        'danger',
        'Revise os campos destacados.',
        $erros,
        $old
    );

    redirecionarCadastro($acao === 'atualizar' ? $servicoId : null);
}

if ($acao === 'criar') {
    $stmt = $pdo->prepare(
        'INSERT INTO servicos (
            empresa_id,
            categoria_id,
            nome,
            descricao,
            duracao_minutos,
            intervalo_minutos,
            preco,
            permite_agendamento_online,
            ativo
        ) VALUES (
            :empresa_id,
            :categoria_id,
            :nome,
            :descricao,
            :duracao_minutos,
            :intervalo_minutos,
            :preco,
            :permite_agendamento_online,
            1
        )'
    );

    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':categoria_id' => $categoriaId,
        ':nome' => $nome,
        ':descricao' => $descricao !== '' ? $descricao : null,
        ':duracao_minutos' => $duracao,
        ':intervalo_minutos' => $intervalo,
        ':preco' => $preco,
        ':permite_agendamento_online' => $permiteAgendamentoOnline,
    ]);

    $_SESSION['csrf_cadastro_servico'] = bin2hex(random_bytes(32));

    definirFlashFormulario(
        'success',
        'Serviço cadastrado com sucesso.'
    );

    redirecionarCadastro();
}

$stmt = $pdo->prepare(
    'UPDATE servicos
     SET categoria_id = :categoria_id,
         nome = :nome,
         descricao = :descricao,
         duracao_minutos = :duracao_minutos,
         intervalo_minutos = :intervalo_minutos,
         preco = :preco,
         permite_agendamento_online = :permite_agendamento_online
     WHERE id = :id
       AND empresa_id = :empresa_id'
);

$stmt->execute([
    ':categoria_id' => $categoriaId,
    ':nome' => $nome,
    ':descricao' => $descricao !== '' ? $descricao : null,
    ':duracao_minutos' => $duracao,
    ':intervalo_minutos' => $intervalo,
    ':preco' => $preco,
    ':permite_agendamento_online' => $permiteAgendamentoOnline,
    ':id' => $servicoId,
    ':empresa_id' => $empresaId,
]);

$_SESSION['csrf_cadastro_servico'] = bin2hex(random_bytes(32));

definirFlashLista(
    'success',
    'Serviço atualizado com sucesso.'
);

redirecionarLista();
