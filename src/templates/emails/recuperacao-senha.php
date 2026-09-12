<?php

declare(strict_types=1);

$nome = htmlspecialchars((string) ($dados['nome'] ?? 'Olá'), ENT_QUOTES, 'UTF-8');
$link = htmlspecialchars((string) ($dados['link'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Redefinir senha</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#222;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f5f5f5;padding:28px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;background:#fff;border:1px solid #e1e1e1;border-radius:10px;">
                <tr>
                    <td style="padding:32px;">
                        <h1 style="margin:0 0 20px;font-size:26px;">Redefina sua senha</h1>
                        <p style="line-height:1.6;">Olá, <strong><?= $nome ?></strong>.</p>
                        <p style="line-height:1.6;">Recebemos uma solicitação para redefinir a senha da sua conta no Salão Agenda.</p>
                        <p style="line-height:1.6;">O link abaixo é válido por <strong>30 minutos</strong> e pode ser usado apenas uma vez.</p>
                        <p style="margin:28px 0;">
                            <a href="<?= $link ?>" style="display:inline-block;background:#222;color:#fff;text-decoration:none;padding:14px 20px;border-radius:6px;font-weight:bold;">Criar nova senha</a>
                        </p>
                        <p style="line-height:1.6;color:#555;">Se você não solicitou essa alteração, ignore este e-mail. Sua senha atual continuará válida.</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
