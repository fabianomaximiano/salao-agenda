<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

exigirAdministrador();

function redirecionarIdentidadeVisual(): never
{
    header('Location: ../identidade-visual.php');
    exit;
}

function falharIdentidadeVisual(string $mensagem): never
{
    $_SESSION['identidade_visual_erro'] = $mensagem;
    redirecionarIdentidadeVisual();
}

function normalizarCor(?string $valor): ?string
{
    if ($valor === null) {
        return null;
    }

    $valor = trim($valor);

    if ($valor === '') {
        return null;
    }

    $valor = strtoupper($valor);

    if (!preg_match('/^#[0-9A-F]{6}$/', $valor)) {
        throw new InvalidArgumentException('Informe as cores no formato hexadecimal #RRGGBB.');
    }

    return $valor;
}

function criarImagemOrigem(string $arquivo, string $mime): GdImage|false
{
    return match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($arquivo),
        'image/png' => imagecreatefrompng($arquivo),
        'image/webp' => imagecreatefromwebp($arquivo),
        default => false,
    };
}

function gerarLogoWebp(
    string $arquivoTemporario,
    string $mime,
    string $destino
): void {
    $imagem = criarImagemOrigem($arquivoTemporario, $mime);

    if (!$imagem instanceof GdImage) {
        throw new RuntimeException('Não foi possível processar a imagem enviada.');
    }

    try {
        $largura = imagesx($imagem);
        $altura = imagesy($imagem);

        if ($largura <= 0 || $altura <= 0) {
            throw new RuntimeException('A imagem enviada possui dimensões inválidas.');
        }

        $limite = 1200;
        $escala = min(1, $limite / max($largura, $altura));

        $novaLargura = max(1, (int) round($largura * $escala));
        $novaAltura = max(1, (int) round($altura * $escala));

        $saida = imagecreatetruecolor($novaLargura, $novaAltura);

        if (!$saida instanceof GdImage) {
            throw new RuntimeException('Não foi possível preparar a imagem da logo.');
        }

        try {
            imagealphablending($saida, false);
            imagesavealpha($saida, true);

            $transparente = imagecolorallocatealpha($saida, 0, 0, 0, 127);
            imagefilledrectangle(
                $saida,
                0,
                0,
                $novaLargura,
                $novaAltura,
                $transparente
            );

            if (!imagecopyresampled(
                $saida,
                $imagem,
                0,
                0,
                0,
                0,
                $novaLargura,
                $novaAltura,
                $largura,
                $altura
            )) {
                throw new RuntimeException('Não foi possível redimensionar a imagem da logo.');
            }

            if (!imagewebp($saida, $destino, 82)) {
                throw new RuntimeException('Não foi possível salvar a logo em WebP.');
            }
        } finally {
            imagedestroy($saida);
        }
    } finally {
        imagedestroy($imagem);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método não permitido.');
}

$csrfSessao = $_SESSION['csrf_identidade_visual'] ?? '';
$csrfRecebido = $_POST['csrf_token'] ?? '';

if (
    !is_string($csrfSessao)
    || $csrfSessao === ''
    || !is_string($csrfRecebido)
    || !hash_equals($csrfSessao, $csrfRecebido)
) {
    falharIdentidadeVisual('Sessão expirada ou requisição inválida. Atualize a página e tente novamente.');
}

$empresaId = (int) ($_SESSION['empresa_id'] ?? 0);

if ($empresaId <= 0) {
    falharIdentidadeVisual('Não foi possível identificar a empresa da sessão.');
}

try {
    $corPrimaria = normalizarCor(
        isset($_POST['cor_primaria']) && is_string($_POST['cor_primaria'])
            ? $_POST['cor_primaria']
            : null
    );

    $corSecundaria = normalizarCor(
        isset($_POST['cor_secundaria']) && is_string($_POST['cor_secundaria'])
            ? $_POST['cor_secundaria']
            : null
    );
} catch (InvalidArgumentException $e) {
    falharIdentidadeVisual($e->getMessage());
}

$pdo = getDB();

$stmt = $pdo->prepare(
    'SELECT logo_arquivo
     FROM empresa_identidade_visual
     WHERE empresa_id = :empresa_id
     LIMIT 1'
);
$stmt->execute([':empresa_id' => $empresaId]);

$registroAtual = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$logoAtual = isset($registroAtual['logo_arquivo']) && is_string($registroAtual['logo_arquivo'])
    ? $registroAtual['logo_arquivo']
    : null;

$novaLogoUrl = null;
$novoArquivoFisico = null;

$upload = $_FILES['logo'] ?? null;

if (is_array($upload)) {
    $erroUpload = isset($upload['error']) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE;

    if ($erroUpload !== UPLOAD_ERR_NO_FILE) {
        if ($erroUpload !== UPLOAD_ERR_OK) {
            $mensagensUpload = [
                UPLOAD_ERR_INI_SIZE => 'A logo excede o limite permitido pelo servidor.',
                UPLOAD_ERR_FORM_SIZE => 'A logo excede o limite permitido pelo formulário.',
                UPLOAD_ERR_PARTIAL => 'O upload da logo foi interrompido. Tente novamente.',
                UPLOAD_ERR_NO_TMP_DIR => 'O servidor está sem diretório temporário para uploads.',
                UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu gravar a logo.',
                UPLOAD_ERR_EXTENSION => 'O upload da logo foi bloqueado por uma extensão do servidor.',
            ];

            falharIdentidadeVisual(
                $mensagensUpload[$erroUpload] ?? 'Falha ao enviar a logo.'
            );
        }

        $tamanho = isset($upload['size']) ? (int) $upload['size'] : 0;
        $temporario = isset($upload['tmp_name']) && is_string($upload['tmp_name'])
            ? $upload['tmp_name']
            : '';

        if ($temporario === '' || !is_uploaded_file($temporario)) {
            falharIdentidadeVisual('O arquivo enviado não é um upload válido.');
        }

        if ($tamanho <= 0) {
            falharIdentidadeVisual('A logo enviada está vazia.');
        }

        if ($tamanho > 8 * 1024 * 1024) {
            falharIdentidadeVisual('A logo deve ter no máximo 8 MB.');
        }

        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            falharIdentidadeVisual('O servidor não possui suporte GD/WebP habilitado.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($temporario);

        $mimesPermitidos = [
            'image/jpeg',
            'image/png',
            'image/webp',
        ];

        if (!is_string($mime) || !in_array($mime, $mimesPermitidos, true)) {
            falharIdentidadeVisual('Envie uma logo JPG, PNG ou WebP válida.');
        }

        $dimensoes = @getimagesize($temporario);

        if ($dimensoes === false) {
            falharIdentidadeVisual('O arquivo enviado não pôde ser reconhecido como imagem.');
        }

        $diretorioRelativo = '/uploads/empresas/' . $empresaId . '/identidade';
        $diretorioFisico = dirname(__DIR__) . $diretorioRelativo;

        if (!is_dir($diretorioFisico) && !mkdir($diretorioFisico, 0775, true) && !is_dir($diretorioFisico)) {
            falharIdentidadeVisual('Não foi possível criar a pasta para armazenar a logo.');
        }

        $nomeArquivo = 'logo-' . bin2hex(random_bytes(12)) . '.webp';
        $novoArquivoFisico = $diretorioFisico . DIRECTORY_SEPARATOR . $nomeArquivo;
        $novaLogoUrl = $diretorioRelativo . '/' . $nomeArquivo;

        try {
            gerarLogoWebp($temporario, $mime, $novoArquivoFisico);
        } catch (Throwable $e) {
            if (is_string($novoArquivoFisico) && is_file($novoArquivoFisico)) {
                @unlink($novoArquivoFisico);
            }

            falharIdentidadeVisual($e->getMessage());
        }
    }
}

$logoParaSalvar = $novaLogoUrl ?? $logoAtual;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO empresa_identidade_visual (
            empresa_id,
            logo_arquivo,
            cor_primaria,
            cor_secundaria
        ) VALUES (
            :empresa_id,
            :logo_arquivo,
            :cor_primaria,
            :cor_secundaria
        )
        ON DUPLICATE KEY UPDATE
            logo_arquivo = VALUES(logo_arquivo),
            cor_primaria = VALUES(cor_primaria),
            cor_secundaria = VALUES(cor_secundaria)'
    );

    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':logo_arquivo' => $logoParaSalvar,
        ':cor_primaria' => $corPrimaria,
        ':cor_secundaria' => $corSecundaria,
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (is_string($novoArquivoFisico) && is_file($novoArquivoFisico)) {
        @unlink($novoArquivoFisico);
    }

    error_log('Erro ao salvar identidade visual: ' . $e->getMessage());
    falharIdentidadeVisual('Não foi possível salvar a identidade visual. Tente novamente.');
}

if (
    $novaLogoUrl !== null
    && is_string($logoAtual)
    && $logoAtual !== ''
    && $logoAtual !== $novaLogoUrl
) {
    $prefixoPermitido = '/uploads/empresas/' . $empresaId . '/identidade/';

    if (str_starts_with($logoAtual, $prefixoPermitido)) {
        $arquivoAntigo = dirname(__DIR__) . $logoAtual;

        if (is_file($arquivoAntigo)) {
            @unlink($arquivoAntigo);
        }
    }
}

$_SESSION['csrf_identidade_visual'] = bin2hex(random_bytes(32));
$_SESSION['identidade_visual_sucesso'] = 'Identidade visual salva com sucesso.';

redirecionarIdentidadeVisual();
