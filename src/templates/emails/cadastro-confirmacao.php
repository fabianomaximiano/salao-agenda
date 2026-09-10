<?php

declare(strict_types=1);

/**
 * Template de confirmação de cadastro.
 *
 * Dados esperados:
 *
 * $dados = [
 *     'nome'           => 'Nome do administrador',
 *     'empresa'        => 'Nome da empresa',
 *     'email'          => 'email@empresa.com.br',
 *     'link_ativacao'  => 'https://...',
 * ];
 */

$nome = htmlspecialchars(
    (string) ($dados['nome'] ?? ''),
    ENT_QUOTES,
    'UTF-8'
);

$empresa = htmlspecialchars(
    (string) ($dados['empresa'] ?? ''),
    ENT_QUOTES,
    'UTF-8'
);

$email = htmlspecialchars(
    (string) ($dados['email'] ?? ''),
    ENT_QUOTES,
    'UTF-8'
);

$linkAtivacao = htmlspecialchars(
    (string) ($dados['link_ativacao'] ?? ''),
    ENT_QUOTES,
    'UTF-8'
);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Cadastro realizado - Salão Agenda</title>
</head>

<body style="
    margin: 0;
    padding: 0;
    background: #f5f5f5;
    font-family: Arial, Helvetica, sans-serif;
    color: #333333;
">

<table
    width="100%"
    cellpadding="0"
    cellspacing="0"
    border="0"
    style="background: #f5f5f5; padding: 30px 15px;"
>
    <tr>
        <td align="center">

            <table
                width="100%"
                cellpadding="0"
                cellspacing="0"
                border="0"
                style="
                    max-width: 600px;
                    background: #ffffff;
                    border-radius: 8px;
                    overflow: hidden;
                "
            >

                <tr>
                    <td style="
                        padding: 30px;
                        text-align: center;
                        border-bottom: 1px solid #eeeeee;
                    ">
                        <h1 style="
                            margin: 0;
                            font-size: 26px;
                            color: #222222;
                        ">
                            Salão Agenda
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 35px 30px;">

                        <h2 style="
                            margin-top: 0;
                            font-size: 22px;
                            color: #222222;
                        ">
                            Cadastro realizado com sucesso
                        </h2>

                        <p style="
                            font-size: 16px;
                            line-height: 1.6;
                        ">
                            Olá, <?= $nome ?>.
                        </p>

                        <p style="
                            font-size: 16px;
                            line-height: 1.6;
                        ">
                            O cadastro da empresa
                            <strong><?= $empresa ?></strong>
                            foi realizado no Salão Agenda.
                        </p>

                        <p style="
                            font-size: 16px;
                            line-height: 1.6;
                        ">
                            Seu acesso administrativo foi criado para o e-mail:
                        </p>

                        <p style="
                            font-size: 16px;
                            font-weight: bold;
                        ">
                            <?= $email ?>
                        </p>

                        <p style="
                            font-size: 16px;
                            line-height: 1.6;
                        ">
                            Para concluir a ativação da sua conta,
                            clique no botão abaixo e crie sua senha de acesso.
                        </p>

                        <div style="
                            text-align: center;
                            margin: 35px 0;
                        ">
                            <a
                                href="<?= $linkAtivacao ?>"
                                style="
                                    display: inline-block;
                                    padding: 14px 28px;
                                    background: #222222;
                                    color: #ffffff;
                                    text-decoration: none;
                                    border-radius: 5px;
                                    font-size: 16px;
                                    font-weight: bold;
                                "
                            >
                                Criar minha senha
                            </a>
                        </div>

                        <p style="
                            font-size: 14px;
                            line-height: 1.6;
                            color: #666666;
                        ">
                            Se o botão não funcionar, copie e cole este endereço
                            no seu navegador:
                        </p>

                        <p style="
                            font-size: 13px;
                            line-height: 1.6;
                            word-break: break-all;
                            color: #555555;
                        ">
                            <?= $linkAtivacao ?>
                        </p>

                        <p style="
                            margin-top: 30px;
                            font-size: 14px;
                            line-height: 1.6;
                            color: #777777;
                        ">
                            Se você não reconhece este cadastro,
                            pode ignorar esta mensagem.
                        </p>

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
                        Salão Agenda<br>
                        Sistema de agendamento e gestão
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>