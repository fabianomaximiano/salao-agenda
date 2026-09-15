<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/db.php';
exigirLogin();
$ctx=(string)($_SESSION['contexto']??''); $admin=$ctx==='administrador'; $prof=$ctx==='profissional';
if(!$admin&&!$prof) negarAcesso();
$empresa=(int)$_SESSION['empresa_id']; $profId=$prof?(int)($_SESSION['profissional_id']??0):0; $pdo=getDB();
if(empty($_SESSION['csrf_ausencias_profissionais'])) $_SESSION['csrf_ausencias_profissionais']=bin2hex(random_bytes(32));
$csrf=(string)$_SESSION['csrf_ausencias_profissionais'];
$ok=(string)($_SESSION['flash_success']??''); $erro=(string)($_SESSION['flash_error']??''); unset($_SESSION['flash_success'],$_SESSION['flash_error']);
$sql="SELECT s.*, pe.nome_completo profissional_nome, COALESCE(adm.nome_completo, ua.email) analisador_nome
FROM profissional_solicitacoes_ausencia s
INNER JOIN profissionais pr ON pr.id=s.profissional_id AND pr.empresa_id=s.empresa_id
INNER JOIN pessoas pe ON pe.id=pr.pessoa_id AND pe.empresa_id=s.empresa_id
LEFT JOIN usuarios ua ON ua.id=s.analisado_por_usuario_id
LEFT JOIN administradores adm ON adm.usuario_id=s.analisado_por_usuario_id AND adm.empresa_id=s.empresa_id
WHERE s.empresa_id=:e".($prof?" AND s.profissional_id=:p":"")."
ORDER BY FIELD(s.status,'pendente','aprovada','rejeitada','cancelada'),s.inicio";
$st=$pdo->prepare($sql); $par=[':e'=>$empresa]; if($prof)$par[':p']=$profId; $st->execute($par); $lista=$st->fetchAll(PDO::FETCH_ASSOC);
$id=filter_input(INPUT_GET,'detalhe',FILTER_VALIDATE_INT); $det=null;$hist=[];$coment=[];
if(is_int($id)){
    foreach($lista as $x){
        if((int)$x['id']===$id){
            $det=$x;
            break;
        }
    }

    if($det){
        $sqlUsuarioNome = "COALESCE(
            adm.nome_completo,
            pp.nome_completo,
            pc.nome_completo,
            pcl.nome_completo,
            u.email
        )";

        $joinsUsuario = "
            INNER JOIN usuarios u ON u.id=%s
            LEFT JOIN administradores adm ON adm.usuario_id=u.id AND adm.empresa_id=:empresa_id
            LEFT JOIN profissionais pusuario ON pusuario.usuario_id=u.id AND pusuario.empresa_id=:empresa_id
            LEFT JOIN pessoas pp ON pp.id=pusuario.pessoa_id AND pp.empresa_id=:empresa_id
            LEFT JOIN colaboradores col ON col.usuario_id=u.id AND col.empresa_id=:empresa_id
            LEFT JOIN pessoas pc ON pc.id=col.pessoa_id AND pc.empresa_id=:empresa_id
            LEFT JOIN clientes cli ON cli.usuario_id=u.id AND cli.empresa_id=:empresa_id
            LEFT JOIN pessoas pcl ON pcl.id=cli.pessoa_id AND pcl.empresa_id=:empresa_id
        ";

        $sqlHist = "SELECT h.*, {$sqlUsuarioNome} usuario_nome
                    FROM profissional_solicitacao_ausencia_historico h"
                    . sprintf($joinsUsuario, 'h.usuario_id') .
                    " WHERE h.solicitacao_id=:id
                      ORDER BY h.criado_em,h.id";
        $st=$pdo->prepare($sqlHist);
        $st->execute([':id'=>$id, ':empresa_id'=>$empresa]);
        $hist=$st->fetchAll(PDO::FETCH_ASSOC);

        if($admin){
            $sqlComent = "SELECT c.*, {$sqlUsuarioNome} usuario_nome
                          FROM profissional_solicitacao_ausencia_comentarios c"
                          . sprintf($joinsUsuario, 'c.usuario_id') .
                          " WHERE c.solicitacao_id=:id
                            ORDER BY c.criado_em,c.id";
            $st=$pdo->prepare($sqlComent);
            $st->execute([':id'=>$id, ':empresa_id'=>$empresa]);
            $coment=$st->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}
$tipos=['pausa'=>'Pausa','almoco'=>'Almoço','folga'=>'Folga','falta'=>'Falta','atestado'=>'Atestado','ferias'=>'Férias','compromisso'=>'Compromisso','treinamento'=>'Treinamento','emergencial'=>'Ausência emergencial','outro'=>'Outro'];
function af(string $d):string{return(new DateTimeImmutable($d))->format('d/m/Y H:i');}
function asr(string $s):string{return match($s){'pendente'=>'Pendente','aprovada'=>'Aprovada','rejeitada'=>'Rejeitada','cancelada'=>'Cancelada',default=>$s};}
$pageTitle=$prof?'Minhas ausências':'Ausências profissionais';$pageCss='ausencias-profissionais.css?v=20260915-1';
require __DIR__.'/partials/header.php';require __DIR__.'/partials/sidebar.php';require __DIR__.'/partials/navbar.php';?>
<main class="app-content"><div class="app-page-header"><h1><?=htmlspecialchars($pageTitle,ENT_QUOTES,'UTF-8')?></h1><p><?=$prof?'Solicite uma ausência e acompanhe a decisão da administração.':'Analise solicitações e mantenha o histórico das decisões.'?></p></div>
<?php if($ok):?><div class="alert alert-success"><?=htmlspecialchars($ok,ENT_QUOTES,'UTF-8')?></div><?php endif;if($erro):?><div class="alert alert-danger"><?=htmlspecialchars($erro,ENT_QUOTES,'UTF-8')?></div><?php endif;?>
<?php if($prof):?><section class="app-card mb-4"><div class="app-card-header"><h2>Solicitar ausência</h2></div><div class="app-card-body"><form method="post" action="api/ausencias-profissionais.php"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf,ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="acao" value="solicitar"><div class="form-row"><div class="form-group col-md-4"><label>Tipo</label><select name="tipo" class="form-control"><?php foreach($tipos as $v=>$r):?><option value="<?=$v?>"><?=$r?></option><?php endforeach;?></select></div><div class="form-group col-md-4"><label>Início</label><input type="datetime-local" name="inicio" class="form-control" required></div><div class="form-group col-md-4"><label>Fim</label><input type="datetime-local" name="fim" class="form-control" required></div></div><div class="form-group"><label>Motivo</label><input name="motivo" maxlength="160" class="form-control"></div><div class="form-group"><label>Observação</label><textarea name="observacao" class="form-control" rows="3"></textarea></div><button class="btn btn-primary">Enviar solicitação</button></form></div></section><?php endif;?>
<section class="app-card mb-4"><div class="app-card-header"><h2>Solicitações</h2></div><div class="app-card-body p-0"><?php if(!$lista):?><div class="app-empty-state"><p>Nenhuma solicitação encontrada.</p></div><?php else:?><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><?php if($admin):?><th>Profissional</th><?php endif;?><th>Período</th><th>Tipo</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($lista as $s):?><tr><?php if($admin):?><td><?=htmlspecialchars((string)$s['profissional_nome'],ENT_QUOTES,'UTF-8')?></td><?php endif;?><td class="ausencia-periodo"><?=af((string)$s['inicio'])?> – <?=af((string)$s['fim'])?></td><td><?=htmlspecialchars($tipos[(string)$s['tipo']]??(string)$s['tipo'],ENT_QUOTES,'UTF-8')?></td><td class="ausencia-status ausencia-status--<?=$s['status']?>"><?=asr((string)$s['status'])?></td><td><a class="btn btn-sm btn-outline-primary" href="?detalhe=<?=(int)$s['id']?>">Detalhes</a></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div></section>
<?php if($det):?><section class="app-card"><div class="app-card-header"><h2>Detalhes #<?=(int)$det['id']?></h2></div><div class="app-card-body"><p><strong>Profissional:</strong> <?=htmlspecialchars((string)$det['profissional_nome'],ENT_QUOTES,'UTF-8')?><br><strong>Período:</strong> <?=af((string)$det['inicio'])?> – <?=af((string)$det['fim'])?><br><strong>Status:</strong> <?=asr((string)$det['status'])?><br><strong>Motivo:</strong> <?=htmlspecialchars((string)($det['motivo']?:'—'),ENT_QUOTES,'UTF-8')?></p>
<?php if($prof&&$det['status']==='pendente'):?><form method="post" action="api/ausencias-profissionais.php"><input type="hidden" name="csrf_token" value="<?=$csrf?>"><input type="hidden" name="acao" value="cancelar_pendente"><input type="hidden" name="solicitacao_id" value="<?=(int)$det['id']?>"><button class="btn btn-outline-danger">Cancelar solicitação</button></form><?php endif;?>
<?php if($admin&&$det['status']==='pendente'):?><form method="post" action="api/ausencias-profissionais.php"><input type="hidden" name="csrf_token" value="<?=$csrf?>"><input type="hidden" name="solicitacao_id" value="<?=(int)$det['id']?>"><div class="form-group"><label>Observação da decisão</label><textarea name="observacao_decisao" class="form-control"></textarea></div><button name="acao" value="aprovar" class="btn btn-success">Aprovar</button> <button name="acao" value="rejeitar" class="btn btn-outline-danger">Rejeitar</button></form><?php elseif($admin&&$det['status']==='aprovada'):?><form method="post" action="api/ausencias-profissionais.php"><input type="hidden" name="csrf_token" value="<?=$csrf?>"><input type="hidden" name="acao" value="cancelar_aprovacao"><input type="hidden" name="solicitacao_id" value="<?=(int)$det['id']?>"><div class="form-group"><label>Motivo para cancelar a aprovação</label><textarea name="motivo_cancelamento" class="form-control" required></textarea></div><button class="btn btn-outline-danger">Cancelar aprovação</button></form><?php endif;?>
<h3 class="h5 mt-4">Histórico</h3><ul class="ausencia-timeline"><?php foreach($hist as $h):?><li><strong><?=htmlspecialchars(str_replace('_',' ',(string)$h['acao']),ENT_QUOTES,'UTF-8')?></strong><small><?=htmlspecialchars((string)$h['usuario_nome'].' • '.af((string)$h['criado_em']),ENT_QUOTES,'UTF-8')?></small><?php if($h['detalhes']):?><div><?=nl2br(htmlspecialchars((string)$h['detalhes'],ENT_QUOTES,'UTF-8'))?></div><?php endif;?></li><?php endforeach;?></ul>
<?php if($admin):?><h3 class="h5 mt-4">Comentários internos</h3><?php foreach($coment as $c):?><div class="ausencia-comment"><strong><?=htmlspecialchars((string)$c['usuario_nome'],ENT_QUOTES,'UTF-8')?></strong> <small><?=af((string)$c['criado_em'])?></small><div><?=nl2br(htmlspecialchars((string)$c['comentario'],ENT_QUOTES,'UTF-8'))?></div></div><?php endforeach;?><form method="post" action="api/ausencias-profissionais.php" class="mt-3"><input type="hidden" name="csrf_token" value="<?=$csrf?>"><input type="hidden" name="acao" value="comentar"><input type="hidden" name="solicitacao_id" value="<?=(int)$det['id']?>"><textarea name="comentario" class="form-control mb-2" required></textarea><button class="btn btn-outline-primary">Adicionar comentário</button></form><?php endif;?></div></section><?php endif;?></main><?php require __DIR__.'/partials/footer.php';?>
