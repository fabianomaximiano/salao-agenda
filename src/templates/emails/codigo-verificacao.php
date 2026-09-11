<?php

declare(strict_types=1);

/**
 * Variáveis esperadas:
 *
 * $nome
 * $empresa
 * $codigo
 * $validadeMinutos
 */

$nome = htmlspecialchars(
    (string) ($nome ?? ''),
    ENT_QUOTES,
    'UTF-8'
);

$empresa = htmlspecialchars(
    (string) ($empresa ?? ''),
    ENT_QUOTES,
    'UTF-8'
);

$codigo = htmlspecialchars(
    (string) ($codigo ?? ''),
    ENT_QUOTES,
    'UTF-8'
);

$validadeMinutos = (int) ($validadeMinutos ?? 10);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Código de verificação</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,sans-serif;color:#222;">

<table width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td align="center" style="padding:32px 16px;">

            <table
                width="100%"
                cellpadding="0"
                cellspacing="0"
                border="0"
                style="max-width:600px;background:#ffffff;border-radius:8px;"
            >
                <tr>
                    <td style="padding:32px;">

                        <h1
                            style="
                                margin:0 0 24px;
                                font-size:24px;
                                line-height:1.3;
                            "
                        >
                            Confirme seu e-mail
                        </h1>

                        <p
                            style="
                                margin:0 0 16px;
                                font-size:16px;
                                line-height:1.6;
                            "
                        >
                            Olá, <?= $nome ?>.
                        </p>

                        <p
                            style="
                                margin:0 0 24px;
                                font-size:16px;
                                line-height:1.6;
                            "
                        >
                            Recebemos uma solicitação de cadastro
                            administrativo para
                            <strong><?= $empresa ?></strong>.
                        </p>

                        <p
                            style="
                                margin:0 0 16px;
                                font-size:16px;
                                line-height:1.6;
                            "
                        >
                            Use o código abaixo para confirmar seu e-mail:
                        </p>

                        <div
                            style="
                                margin:24px 0;
                                padding:20px;
                                text-align:center;
                                background:#f2f2f2;
                                border-radius:8px;
                                font-size:32px;
                                font-weight:bold;
                                letter-spacing:8px;
                            "
                        >
                            <?= $codigo ?>
                        </div>

                        <p
                            style="
                                margin:0 0 16px;
                                font-size:14px;
                                line-height:1.6;
                                color:#555;
                            "
                        >
                            Este código expira em
                            <?= $validadeMinutos ?> minutos.
                        </p>

                        <p
                            style="
                                margin:0;
                                font-size:14px;
                                line-height:1.6;
                                color:#555;
                            "
                        >
                            Se você não solicitou este cadastro,
                            ignore este e-mail.
                        </p>

                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>

</body>
</html>