<?php
declare(strict_types=1);
$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"></head><body style="font-family:Arial,sans-serif;line-height:1.5;color:#222">
<h2>Ausência de profissional aprovada</h2>
<p>Uma solicitação de ausência foi aprovada.</p>
<p><strong>Profissional:</strong> <?=$e($profissionalNome)?><br>
<strong>Período:</strong> <?=$e($periodo)?><br>
<strong>Aprovado por:</strong> <?=$e($aprovadorNome)?><br>
<strong>Aprovação:</strong> <?=$e($aprovadoEm)?></p>
<p>Consulte a área de ausências no Salão Agenda para ver os detalhes e o histórico.</p>
</body></html>
