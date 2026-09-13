<?php
/** @var string $nome */
/** @var string $empresa */
/** @var string $codigo */
/** @var int $validadeMinutos */
/** @var string $linkConfirmacao */
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Ative seu acesso</title></head>
<body style="font-family:Arial,Helvetica,sans-serif;color:#222;line-height:1.6;">
<h1 style="font-size:24px;">Ative seu acesso de colaborador</h1>
<p>Olá, <?=htmlspecialchars($nome,ENT_QUOTES,'UTF-8')?>.</p>
<p>A empresa <strong><?=htmlspecialchars($empresa,ENT_QUOTES,'UTF-8')?></strong> liberou seu acesso de colaborador ao Salão Agenda.</p>
<p>Seu código de verificação é:</p><p style="font-size:32px;font-weight:700;letter-spacing:6px;"><?=htmlspecialchars($codigo,ENT_QUOTES,'UTF-8')?></p>
<p>O código é válido por <?=(int)$validadeMinutos?> minutos.</p>
<p><a href="<?=htmlspecialchars($linkConfirmacao,ENT_QUOTES,'UTF-8')?>" style="display:inline-block;padding:12px 18px;background:#222;color:#fff;text-decoration:none;border-radius:6px;">Confirmar meu e-mail</a></p>
<p>Se você não reconhece este convite, ignore esta mensagem.</p></body></html>