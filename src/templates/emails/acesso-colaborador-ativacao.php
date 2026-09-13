<?php
/** @var string $nome */
/** @var string $empresa */
/** @var string $linkAtivacao */
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Crie sua senha</title></head>
<body style="font-family:Arial,Helvetica,sans-serif;color:#222;line-height:1.6;">
<h1 style="font-size:24px;">Crie sua senha</h1>
<p>Olá, <?=htmlspecialchars($nome,ENT_QUOTES,'UTF-8')?>.</p>
<p>Seu e-mail foi confirmado para o acesso de colaborador da empresa <strong><?=htmlspecialchars($empresa,ENT_QUOTES,'UTF-8')?></strong>.</p>
<p>Use o link abaixo para definir sua senha. O link é temporário e de uso único.</p>
<p><a href="<?=htmlspecialchars($linkAtivacao,ENT_QUOTES,'UTF-8')?>" style="display:inline-block;padding:12px 18px;background:#222;color:#fff;text-decoration:none;border-radius:6px;">Criar minha senha</a></p>
<p>Se você não reconhece esta solicitação, ignore esta mensagem.</p></body></html>