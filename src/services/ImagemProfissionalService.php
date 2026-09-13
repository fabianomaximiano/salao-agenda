<?php

declare(strict_types=1);

final class ImagemProfissionalService
{
    private const LIMITE_BYTES = 5 * 1024 * 1024;
    private const MAX_PIXELS = 40000000;
    private const TAMANHOS = [
        'p' => [160, 78],
        'm' => [320, 80],
        'g' => [500, 83],
    ];

    public static function processarUpload(array $upload, int $empresaId): array
    {
        $erro = isset($upload['error']) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE;

        if ($erro === UPLOAD_ERR_NO_FILE) {
            return [];
        }

        if ($erro !== UPLOAD_ERR_OK) {
            $mensagens = [
                UPLOAD_ERR_INI_SIZE => 'A foto excede o limite permitido pelo servidor.',
                UPLOAD_ERR_FORM_SIZE => 'A foto excede o limite permitido pelo formulário.',
                UPLOAD_ERR_PARTIAL => 'O upload da foto foi interrompido. Tente novamente.',
                UPLOAD_ERR_NO_TMP_DIR => 'O servidor está sem diretório temporário para uploads.',
                UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu gravar a foto.',
                UPLOAD_ERR_EXTENSION => 'O upload da foto foi bloqueado por uma extensão do servidor.',
            ];
            throw new RuntimeException($mensagens[$erro] ?? 'Falha ao enviar a foto.');
        }

        $temporario = isset($upload['tmp_name']) && is_string($upload['tmp_name']) ? $upload['tmp_name'] : '';
        $tamanho = isset($upload['size']) ? (int) $upload['size'] : 0;

        if ($temporario === '' || !is_uploaded_file($temporario)) {
            throw new RuntimeException('O arquivo enviado não é um upload válido.');
        }
        if ($tamanho <= 0) {
            throw new RuntimeException('A foto enviada está vazia.');
        }
        if ($tamanho > self::LIMITE_BYTES) {
            throw new RuntimeException('A foto deve ter no máximo 5 MB.');
        }
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            throw new RuntimeException('O servidor não possui suporte GD/WebP habilitado.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($temporario);
        $permitidos = ['image/jpeg', 'image/png', 'image/webp'];

        if (!is_string($mime) || !in_array($mime, $permitidos, true)) {
            throw new RuntimeException('Envie uma foto JPG, PNG ou WebP válida.');
        }

        $dimensoes = @getimagesize($temporario);
        if ($dimensoes === false) {
            throw new RuntimeException('O arquivo enviado não pôde ser reconhecido como imagem.');
        }

        $largura = (int) ($dimensoes[0] ?? 0);
        $altura = (int) ($dimensoes[1] ?? 0);
        if ($largura <= 0 || $altura <= 0 || ($largura * $altura) > self::MAX_PIXELS) {
            throw new RuntimeException('A foto possui dimensões muito grandes para processamento seguro.');
        }

        $origem = self::criarImagemOrigem($temporario, $mime);
        if (!$origem instanceof GdImage) {
            throw new RuntimeException('Não foi possível processar a foto enviada.');
        }

        try {
            if ($mime === 'image/jpeg') {
                $origem = self::corrigirOrientacaoJpeg($origem, $temporario);
                $largura = imagesx($origem);
                $altura = imagesy($origem);
            }

            $diretorioRelativo = '/uploads/empresas/' . $empresaId . '/profissionais';
            $diretorioFisico = dirname(__DIR__) . '/public' . $diretorioRelativo;

            if (!is_dir($diretorioFisico) && !mkdir($diretorioFisico, 0775, true) && !is_dir($diretorioFisico)) {
                throw new RuntimeException('Não foi possível criar a pasta para armazenar a foto.');
            }

            $base = 'profissional-' . bin2hex(random_bytes(12));
            $arquivos = [];

            try {
                foreach (self::TAMANHOS as $sufixo => [$lado, $qualidade]) {
                    $nome = $base . '-' . $sufixo . '.webp';
                    $fisico = $diretorioFisico . DIRECTORY_SEPARATOR . $nome;
                    self::gerarQuadrada($origem, $largura, $altura, $lado, $qualidade, $fisico);
                    $arquivos[$sufixo] = [
                        'url' => $diretorioRelativo . '/' . $nome,
                        'fisico' => $fisico,
                    ];
                }
            } catch (Throwable $e) {
                self::excluirArquivosGerados($arquivos);
                throw $e;
            }

            return $arquivos;
        } finally {
            imagedestroy($origem);
        }
    }

    public static function excluirPorUrl(?string $fotoUrl, int $empresaId): void
    {
        if (!is_string($fotoUrl) || $fotoUrl === '') {
            return;
        }

        $prefixo = '/uploads/empresas/' . $empresaId . '/profissionais/';
        if (!str_starts_with($fotoUrl, $prefixo)) {
            return;
        }

        $nome = basename($fotoUrl);
        if (!preg_match('/^(profissional-[a-f0-9]{24})-[pmg]\.webp$/', $nome, $matches)) {
            return;
        }

        $diretorio = dirname(__DIR__) . '/public' . $prefixo;
        foreach (array_keys(self::TAMANHOS) as $sufixo) {
            $arquivo = $diretorio . $matches[1] . '-' . $sufixo . '.webp';
            if (is_file($arquivo)) {
                @unlink($arquivo);
            }
        }
    }

    public static function excluirArquivosGerados(array $arquivos): void
    {
        foreach ($arquivos as $arquivo) {
            $fisico = is_array($arquivo) ? ($arquivo['fisico'] ?? null) : null;
            if (is_string($fisico) && is_file($fisico)) {
                @unlink($fisico);
            }
        }
    }

    public static function urls(?string $fotoUrl): array
    {
        if (!is_string($fotoUrl) || $fotoUrl === '') {
            return ['p' => null, 'm' => null, 'g' => null];
        }

        if (!preg_match('/^(.*)-[pmg]\.webp$/', $fotoUrl, $matches)) {
            return ['p' => $fotoUrl, 'm' => $fotoUrl, 'g' => $fotoUrl];
        }

        return [
            'p' => $matches[1] . '-p.webp',
            'm' => $matches[1] . '-m.webp',
            'g' => $matches[1] . '-g.webp',
        ];
    }

    public static function iniciais(string $nome): string
    {
        $partes = preg_split('/\s+/u', trim($nome)) ?: [];
        $partes = array_values(array_filter($partes, static fn(string $parte): bool => $parte !== ''));
        if (!$partes) {
            return '?';
        }

        preg_match('/^./u', $partes[0], $primeiroMatch);
        $primeira = $primeiroMatch[0] ?? '?';
        $ultima = '';
        if (count($partes) > 1) {
            preg_match('/^./u', $partes[count($partes) - 1], $ultimoMatch);
            $ultima = $ultimoMatch[0] ?? '';
        }
        return strtoupper($primeira . $ultima);
    }

    private static function criarImagemOrigem(string $arquivo, string $mime): GdImage|false
    {
        return match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($arquivo),
            'image/png' => imagecreatefrompng($arquivo),
            'image/webp' => imagecreatefromwebp($arquivo),
            default => false,
        };
    }

    private static function gerarQuadrada(GdImage $origem, int $largura, int $altura, int $lado, int $qualidade, string $destino): void
    {
        $recorte = min($largura, $altura);
        $origemX = (int) floor(($largura - $recorte) / 2);
        $origemY = (int) floor(($altura - $recorte) / 2);
        $saida = imagecreatetruecolor($lado, $lado);

        if (!$saida instanceof GdImage) {
            throw new RuntimeException('Não foi possível preparar a foto do profissional.');
        }

        try {
            imagealphablending($saida, false);
            imagesavealpha($saida, true);
            $transparente = imagecolorallocatealpha($saida, 0, 0, 0, 127);
            imagefilledrectangle($saida, 0, 0, $lado, $lado, $transparente);

            if (!imagecopyresampled($saida, $origem, 0, 0, $origemX, $origemY, $lado, $lado, $recorte, $recorte)) {
                throw new RuntimeException('Não foi possível redimensionar a foto do profissional.');
            }
            if (!imagewebp($saida, $destino, $qualidade)) {
                throw new RuntimeException('Não foi possível salvar a foto em WebP.');
            }
        } finally {
            imagedestroy($saida);
        }
    }

    private static function corrigirOrientacaoJpeg(GdImage $imagem, string $arquivo): GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $imagem;
        }

        $exif = @exif_read_data($arquivo);
        $orientacao = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $rotacionada = match ($orientacao) {
            3 => imagerotate($imagem, 180, 0),
            6 => imagerotate($imagem, -90, 0),
            8 => imagerotate($imagem, 90, 0),
            default => false,
        };

        if ($rotacionada instanceof GdImage) {
            imagedestroy($imagem);
            return $rotacionada;
        }

        return $imagem;
    }
}
