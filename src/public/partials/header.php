<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    require_once __DIR__ . '/../../includes/session.php';
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    if (
        (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
    ) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

$pageTitle = $pageTitle ?? 'Painel';
$pageCss = $pageCss ?? null;
$brandPrimary = '#343aef';
$brandSecondary = '#1f2430';
if (!empty($_SESSION['empresa_id'])) {
    try {
        require_once __DIR__ . '/../../includes/db.php';
        $stmtBrand = getDB()->prepare('SELECT cor_primaria, cor_secundaria FROM empresa_identidade_visual WHERE empresa_id = :empresa_id LIMIT 1');
        $stmtBrand->execute([':empresa_id' => (int) $_SESSION['empresa_id']]);
        $brand = $stmtBrand->fetch(PDO::FETCH_ASSOC) ?: [];
        if (isset($brand['cor_primaria']) && is_string($brand['cor_primaria']) && preg_match('/^#[0-9A-Fa-f]{6}$/', $brand['cor_primaria'])) $brandPrimary = strtoupper($brand['cor_primaria']);
        if (isset($brand['cor_secundaria']) && is_string($brand['cor_secundaria']) && preg_match('/^#[0-9A-Fa-f]{6}$/', $brand['cor_secundaria'])) $brandSecondary = strtoupper($brand['cor_secundaria']);
    } catch (Throwable $e) {}
}
function appBrandRgb(string $hex): array { return [hexdec(substr($hex,1,2)),hexdec(substr($hex,3,2)),hexdec(substr($hex,5,2))]; }
function appBrandDarken(string $hex): string { [$r,$g,$b]=appBrandRgb($hex); return sprintf('#%02X%02X%02X',(int)round($r*.82),(int)round($g*.82),(int)round($b*.82)); }
function appBrandContrast(string $hex): string { [$r,$g,$b]=appBrandRgb($hex); return ((.299*$r+.587*$g+.114*$b)/255) > .62 ? '#212529' : '#FFFFFF'; }
$brandPrimaryDark = appBrandDarken($brandPrimary);
$brandPrimaryRgb = implode(', ', appBrandRgb($brandPrimary));
$brandOnPrimary = appBrandContrast($brandPrimary);


?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, shrink-to-fit=no"
    >

    <title>
        <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>
        | Agenda
    </title>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/app.css?v=20260928-1"
    >
    <style>
        :root {
            --app-primary: <?= htmlspecialchars($brandPrimary, ENT_QUOTES, 'UTF-8') ?>;
            --app-primary-dark: <?= htmlspecialchars($brandPrimaryDark, ENT_QUOTES, 'UTF-8') ?>;
            --app-secondary: <?= htmlspecialchars($brandSecondary, ENT_QUOTES, 'UTF-8') ?>;
            --app-sidebar: <?= htmlspecialchars($brandSecondary, ENT_QUOTES, 'UTF-8') ?>;
            --app-on-primary: <?= htmlspecialchars($brandOnPrimary, ENT_QUOTES, 'UTF-8') ?>;
            --app-primary-rgb: <?= htmlspecialchars($brandPrimaryRgb, ENT_QUOTES, 'UTF-8') ?>;
        }
    </style>

    <?php if ($pageCss): ?>

        <link
            rel="stylesheet"
            href="assets/css/<?= htmlspecialchars(
                $pageCss,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

    <?php endif; ?>

</head>

<body>

<div class="app-wrapper">