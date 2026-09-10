<?php

declare(strict_types=1);

/**
 * Template interno de controle de novos cadastros.
 *
 * Dados esperados:
 *
 * $dados = [
 *     'empresa_id'       => 10,
 *     'empresa'          => 'Empresa Exemplo',
 *     'documento'        => '00.000.000/0001-00',
 *     'email_empresa'    => 'contato@empresa.com.br',
 *     'telefone_empresa' => '(11) 0000-0000',
 *     'whatsapp_empresa' => '(11) 90000-0000',
 *     'cidade'           => 'São Paulo',
 *     'estado'           => 'SP',
 *
 *     'admin_nome'       => 'Fulano da Silva',
 *     'admin_cpf'        => '000.000.000-00',
 *     'admin_email'      => 'fulano@empresa.com.br',
 *     'admin_telefone'   => '(11) 90000-0000',
 *
 *     'data_cadastro'    => '10/09/2026 21:30',
 * ];
 */

function emailCampo(array $dados, string $campo): string
{
    $valor = trim((string) ($dados[$campo] ?? ''));

    if ($valor === '') {
        return 'Não informado';
    }

    return htmlspecialchars(
        $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Novo cadastro - Salão Agenda</title>
</head>

<body style="
    margin: 0;
    padding: 0;
    background: #f5f5f5;
    font-family: Arial, Helvetica, sans-serif;
    color: #333333;
">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #f5f5f5; padding: 30px 15px;">
    <tr>
        <td align="center">

            <table
                width="100%"
                cellpadding="0"
                cellspacing="0"
                border="0"
                style="
                    max-width: 650px;
                    background: #ffffff;
                    border-radius: 8px;
                "
            >

                <tr>
                    <td style="padding: 30px;">

                        <h1 style="
                            margin-top: 0;
                            font-size: 24px;
                        ">
                            Novo cadastro no Salão Agenda
                        </h1>

                        <p style="
                            font-size: 15px;
                            color: #666666;
                        ">
                            Uma nova empresa foi cadastrada na plataforma.
                        </p>

                        <h2 style="
                            margin-top: 30px;
                            font-size: 18px;
                            border-bottom: 1px solid #dddddd;
                            padding-bottom: 10px;
                        ">
                            Empresa
                        </h2>

                        <table
                            width="100%"
                            cellpadding="7"
                            cellspacing="0"
                            border="0"
                            style="font-size: 14px;"
                        >
                            <tr>
                                <td><strong>ID:</strong></td>
                                <td><?= emailCampo($dados, 'empresa_id') ?></td>
                            </tr>

                            <tr>
                                <td><strong>Empresa:</strong></td>
                                <td><?= emailCampo($dados, 'empresa') ?></td>
                            </tr>

                            <tr>
                                <td><strong>CPF/CNPJ:</strong></td>
                                <td><?= emailCampo($dados, 'documento') ?></td>
                            </tr>

                            <tr>
                                <td><strong>E-mail:</strong></td>
                                <td><?= emailCampo($dados, 'email_empresa') ?></td>
                            </tr>

                            <tr>
                                <td><strong>Telefone:</strong></td>
                                <td><?= emailCampo($dados, 'telefone_empresa') ?></td>
                            </tr>

                            <tr>
                                <td><strong>WhatsApp:</strong></td>
                                <td><?= emailCampo($dados, 'whatsapp_empresa') ?></td>
                            </tr>

                            <tr>
                                <td><strong>Localização:</strong></td>
                                <td>
                                    <?= emailCampo($dados, 'cidade') ?>
                                    /
                                    <?= emailCampo($dados, 'estado') ?>
                                </td>
                            </tr>
                        </table>

                        <h2 style="
                            margin-top: 30px;
                            font-size: 18px;
                            border-bottom: 1px solid #dddddd;
                            padding-bottom: 10px;
                        ">
                            Administrador
                        </h2>

                        <table
                            width="100%"
                            cellpadding="7"
                            cellspacing="0"
                            border="0"
                            style="font-size: 14px;"
                        >

                            <tr>
                                <td><strong>Nome:</strong></td>
                                <td><?= emailCampo($dados, 'admin_nome') ?></td>
                            </tr>

                            <tr>
                                <td><strong>CPF:</strong></td>
                                <td><?= emailCampo($dados, 'admin_cpf') ?></td>
                            </tr>

                            <tr>
                                <td><strong>E-mail:</strong></td>
                                <td><?= emailCampo($dados, 'admin_email') ?></td>
                            </tr>

                            <tr>
                                <td><strong>Telefone:</strong></td>
                                <td><?= emailCampo($dados, 'admin_telefone') ?></td>
                            </tr>

                        </table>

                        <h2 style="
                            margin-top: 30px;
                            font-size: 18px;
                            border-bottom: 1px solid #dddddd;
                            padding-bottom: 10px;
                        ">
                            Controle
                        </h2>

                        <table
                            width="100%"
                            cellpadding="7"
                            cellspacing="0"
                            border="0"
                            style="font-size: 14px;"
                        >

                            <tr>
                                <td><strong>Data do cadastro:</strong></td>
                                <td><?= emailCampo($dados, 'data_cadastro') ?></td>
                            </tr>

                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    Aguardando ativação do administrador
                                </td>
                            </tr>

                        </table>

                    </td>
                </tr>

                <tr>
                    <td style="
                        padding: 20px 30px;
                        text-align: center;
                        background: #fafafa;
                        font-size: 12px;
                        color: #888888;
                    ">
                        Controle interno — Salão Agenda
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>