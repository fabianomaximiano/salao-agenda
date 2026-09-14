<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/db.php';

exigirAdministrador();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

$csrfRecebido = (string) ($_POST['csrf_token'] ?? '');
$csrfSessao = (string) ($_SESSION['csrf_dados_empresa'] ?? '');

if (
    $csrfRecebido === ''
    || $csrfSessao === ''
    || !hash_equals($csrfSessao, $csrfRecebido)
) {
    http_response_code(403);

    $_SESSION['dados_empresa_erro'] =
        'Sua sessão expirou ou a solicitação é inválida. Tente novamente.';

    header('Location: ../dados-empresa.php');
    exit;
}

$empresaId = (int) ($_SESSION['empresa_id'] ?? 0);

if ($empresaId <= 0) {
    http_response_code(403);
    exit('Empresa inválida.');
}

$nullSeVazio = static function (mixed $valor): ?string {
    $valor = trim((string) ($valor ?? ''));

    return $valor === ''
        ? null
        : $valor;
};

$nomeFantasia = trim((string) ($_POST['nome_fantasia'] ?? ''));
$segmentoId = (int) ($_POST['segmento_id'] ?? 0);
$razaoSocial = $nullSeVazio($_POST['razao_social'] ?? null);
$email = $nullSeVazio($_POST['email_empresa'] ?? null);
$telefone = $nullSeVazio($_POST['telefone_empresa'] ?? null);
$whatsapp = $nullSeVazio($_POST['whatsapp_empresa'] ?? null);
$cep = preg_replace('/\D+/', '', (string) ($_POST['cep'] ?? '')) ?? '';
$logradouro = trim((string) ($_POST['logradouro'] ?? ''));
$numero = trim((string) ($_POST['numero'] ?? ''));
$complemento = $nullSeVazio($_POST['complemento'] ?? null);
$bairro = trim((string) ($_POST['bairro'] ?? ''));
$cidade = trim((string) ($_POST['cidade'] ?? ''));
$estado = strtoupper(trim((string) ($_POST['estado'] ?? '')));

try {
    if ($nomeFantasia === '') {
        throw new RuntimeException(
            'Informe o nome fantasia.'
        );
    }

    if ($segmentoId <= 0) {
        throw new RuntimeException(
            'Selecione um segmento válido.'
        );
    }

    if ($email !== null) {
        $email = strtolower($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(
                'E-mail da empresa inválido.'
            );
        }
    }

    if (strlen($cep) !== 8) {
        throw new RuntimeException(
            'CEP inválido.'
        );
    }

    if (
        $logradouro === ''
        || $numero === ''
        || $bairro === ''
        || $cidade === ''
        || strlen($estado) !== 2
    ) {
        throw new RuntimeException(
            'Preencha corretamente o endereço da empresa.'
        );
    }

    $pdo = getDB();

    $stmt = $pdo->prepare(
        '
        SELECT id
        FROM segmentos
        WHERE id = :id
          AND ativo = 1
        LIMIT 1
        '
    );

    $stmt->execute([
        ':id' => $segmentoId,
    ]);

    if (!$stmt->fetchColumn()) {
        throw new RuntimeException(
            'Segmento não encontrado ou inativo.'
        );
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        '
        UPDATE empresas
        SET
            nome_fantasia = :nome_fantasia,
            razao_social = :razao_social,
            email = :email,
            telefone = :telefone,
            whatsapp = :whatsapp,
            cep = :cep,
            logradouro = :logradouro,
            numero = :numero,
            complemento = :complemento,
            bairro = :bairro,
            cidade = :cidade,
            estado = :estado
        WHERE id = :empresa_id
        '
    );

    $stmt->execute([
        ':nome_fantasia' => $nomeFantasia,
        ':razao_social' => $razaoSocial,
        ':email' => $email,
        ':telefone' => $telefone,
        ':whatsapp' => $whatsapp,
        ':cep' => $cep,
        ':logradouro' => $logradouro,
        ':numero' => $numero,
        ':complemento' => $complemento,
        ':bairro' => $bairro,
        ':cidade' => $cidade,
        ':estado' => $estado,
        ':empresa_id' => $empresaId,
    ]);

    /*
     * empresa_segmentos não possui coluna id.
     * A chave primária é composta por empresa_id + segmento_id.
     *
     * Como a tela administra um segmento atual da empresa,
     * primeiro verificamos se já existe vínculo para a empresa.
     */
    $stmt = $pdo->prepare(
        '
        SELECT segmento_id
        FROM empresa_segmentos
        WHERE empresa_id = :empresa_id
        LIMIT 1
        '
    );

    $stmt->execute([
        ':empresa_id' => $empresaId,
    ]);

    $segmentoAtual = $stmt->fetchColumn();

    if ($segmentoAtual !== false) {
        if ((int) $segmentoAtual !== $segmentoId) {
            $stmt = $pdo->prepare(
                '
                UPDATE empresa_segmentos
                SET segmento_id = :segmento_id
                WHERE empresa_id = :empresa_id
                  AND segmento_id = :segmento_atual
                '
            );

            $stmt->execute([
                ':segmento_id' => $segmentoId,
                ':empresa_id' => $empresaId,
                ':segmento_atual' => (int) $segmentoAtual,
            ]);
        }
    } else {
        $stmt = $pdo->prepare(
            '
            INSERT INTO empresa_segmentos (
                empresa_id,
                segmento_id
            )
            VALUES (
                :empresa_id,
                :segmento_id
            )
            '
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':segmento_id' => $segmentoId,
        ]);
    }

    $pdo->commit();

    $_SESSION['empresa_nome'] = $nomeFantasia;
    $_SESSION['dados_empresa_sucesso'] =
        'Dados da empresa atualizados com sucesso.';

    header('Location: ../dados-empresa.php');
    exit;
} catch (RuntimeException $e) {
    if (
        isset($pdo)
        && $pdo instanceof PDO
        && $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    $_SESSION['dados_empresa_erro'] =
        $e->getMessage();

    header('Location: ../dados-empresa.php');
    exit;
} catch (Throwable $e) {
    if (
        isset($pdo)
        && $pdo instanceof PDO
        && $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    error_log(
        'Erro ao atualizar dados da empresa: '
        . $e->getMessage()
    );

    $_SESSION['dados_empresa_erro'] =
        'Não foi possível atualizar os dados da empresa. Tente novamente.';

    header('Location: ../dados-empresa.php');
    exit;
}
