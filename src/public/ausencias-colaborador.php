<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/db.php';
exigirColaborador();

$empresa=(int)($_SESSION['empresa_id']??0);
$colaborador=(int)($_SESSION['colaborador_id']??0);
$usuario=(int)($_SESSION['user_id']??0);
if($empresa<=0||$colaborador<=0||$usuario<=0){header('Location: logout.php');exit;}

$pdo=getDB();
if(empty($_SESSION['csrf_ausencias_colaborador'])) $_SESSION['csrf_ausencias_colaborador']=bin2hex(random_bytes(32));
$csrf=(string)$_SESSION['csrf_ausencias_colaborador'];
$ok=(string)($_SESSION['flash_success_ausencia_colaborador']??'');
$erro=(string)($_SESSION['flash_error_ausencia_colaborador']??'');
unset($_SESSION['flash_success_ausencia_colaborador'],$_SESSION['flash_error_ausencia_colaborador']);

$sql="SELECT s.*,p.nome_completo colaborador_nome,
             COALESCE(adm.nome_completo,ua.email) analisador_nome
      FROM colaborador_solicitacoes_ausencia s
      INNER JOIN colaboradores c ON c.id=s.colaborador_id AND c.empresa_id=s.empresa_id
      INNER JOIN pessoas p ON p.id=c.pessoa_id AND p.empresa_id=s.empresa_id
      LEFT JOIN usuarios ua ON ua.id=s.analisado_por_usuario_id
      LEFT JOIN administradores adm ON adm.usuario_id=s.analisado_por_usuario_id AND adm.empresa_id=s.empresa_id
      WHERE s.empresa_id=:e AND s.colaborador_id=:c
      ORDER BY FIELD(s.status,'pendente','aprovada','rejeitada','cancelada'),s.inicio";
$st=$pdo->prepare($sql);$st->execute([':e'=>$empresa,':c'=>$colaborador]);$lista=$st->fetchAll(PDO::FETCH_ASSOC);

$id=filter_input(INPUT_GET,'detalhe',FILTER_VALIDATE_INT);$det=null;$hist=[];
if(is_int($id)){
    foreach($lista as $x) if((int)$x['id']===$id){$det=$x;break;}
    if($det){
        $st=$pdo->prepare("SELECT h.*,COALESCE(adm.nome_completo,pc.nome_completo,u.email) usuario_nome
          FROM colaborador_solicitacao_ausencia_historico h
          INNER JOIN usuarios u ON u.id=h.usuario_id
          LEFT JOIN administradores adm ON adm.usuario_id=u.id AND adm.empresa_id=:e
          LEFT JOIN colaboradores col ON col.usuario_id=u.id AND col.empresa_id=:e
          LEFT JOIN pessoas pc ON pc.id=col.pessoa_id AND pc.empresa_id=:e
          WHERE h.solicitacao_id=:id ORDER BY h.criado_em,h.id");
        $st->execute([':e'=>$empresa,':id'=>$id]);$hist=$st->fetchAll(PDO::FETCH_ASSOC);
    }
}
$tipos=['pausa'=>'Pausa','almoco'=>'Almoço','folga'=>'Folga','falta'=>'Falta','atestado'=>'Atestado','ferias'=>'Férias','compromisso'=>'Compromisso','treinamento'=>'Treinamento','emergencial'=>'Ausência emergencial','outro'=>'Outro'];
function acf(string $d):string{return(new DateTimeImmutable($d))->format('d/m/Y H:i');}
function acs(string $s):string{return match($s){'pendente'=>'Pendente','aprovada'=>'Aprovada','rejeitada'=>'Rejeitada','cancelada'=>'Cancelada',default=>$s};}
$pageTitle='Minhas ausências';$pageCss='ausencias-profissionais.css?v=20260915-1';
require __DIR__.'/partials/header.php';require __DIR__.'/partials/sidebar.php';require __DIR__.'/partials/navbar.php';?>
<main class="app-content"><div class="app-page-header"><h1>Minhas ausências</h1><p>Solicite uma ausência e acompanhe a decisão da administração.</p></div>
<?php if($ok):?><div class="alert alert-success"><?=htmlspecialchars($ok,ENT_QUOTES,'UTF-8')?></div><?php endif;if($erro):?><div class="alert alert-danger"><?=htmlspecialchars($erro,ENT_QUOTES,'UTF-8')?></div><?php endif;?>
<section class="app-card mb-4"><div class="app-card-header"><h2>Solicitar ausência</h2></div><div class="app-card-body"><form method="post" action="api/ausencias-colaborador.php"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf,ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="acao" value="solicitar"><div class="form-row"><div class="form-group col-md-4"><label>Tipo</label><select name="tipo" class="form-control"><?php foreach($tipos as $v=>$r):?><option value="<?=$v?>"><?=$r?></option><?php endforeach;?></select></div><div class="form-group col-md-4"><label>Início</label><input type="datetime-local" name="inicio" class="form-control" required></div><div class="form-group col-md-4"><label>Fim</label><input type="datetime-local" name="fim" class="form-control" required></div></div><div class="form-group"><label>Motivo</label><input name="motivo" maxlength="160" class="form-control"></div><div class="form-group"><label>Observação</label><textarea name="observacao" class="form-control" rows="3"></textarea></div><button class="btn btn-primary">Enviar solicitação</button></form></div></section>
<section class="app-card mb-4"><div class="app-card-header"><h2>Solicitações</h2></div><div class="app-card-body p-0"><?php if(!$lista):?><div class="app-empty-state"><p>Nenhuma solicitação encontrada.</p></div><?php else:?><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Período</th><th>Tipo</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($lista as $s):?><tr><td class="ausencia-periodo"><?=acf((string)$s['inicio'])?> – <?=acf((string)$s['fim'])?></td><td><?=htmlspecialchars($tipos[(string)$s['tipo']]??(string)$s['tipo'],ENT_QUOTES,'UTF-8')?></td><td class="ausencia-status ausencia-status--<?=$s['status']?>"><?=acs((string)$s['status'])?></td><td><a class="btn btn-sm btn-outline-primary" href="?detalhe=<?=(int)$s['id']?>">Detalhes</a></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div></section>
<?php if($det):?><section class="app-card"><div class="app-card-header"><h2>Detalhes #<?=(int)$det['id']?></h2></div><div class="app-card-body"><p><strong>Colaborador:</strong> <?=htmlspecialchars((string)$det['colaborador_nome'],ENT_QUOTES,'UTF-8')?><br><strong>Período:</strong> <?=acf((string)$det['inicio'])?> – <?=acf((string)$det['fim'])?><br><strong>Status:</strong> <?=acs((string)$det['status'])?><br><strong>Motivo:</strong> <?=htmlspecialchars((string)($det['motivo']?:'—'),ENT_QUOTES,'UTF-8')?></p>
<?php if($det['status']==='pendente'):?><form method="post" action="api/ausencias-colaborador.php"><input type="hidden" name="csrf_token" value="<?=$csrf?>"><input type="hidden" name="acao" value="cancelar_pendente"><input type="hidden" name="solicitacao_id" value="<?=(int)$det['id']?>"><button class="btn btn-outline-danger">Cancelar solicitação</button></form><?php endif;?>
<h3 class="h5 mt-4">Histórico</h3><ul class="ausencia-timeline"><?php foreach($hist as $h):?><li><strong><?=htmlspecialchars(str_replace('_',' ',(string)$h['acao']),ENT_QUOTES,'UTF-8')?></strong><small><?=htmlspecialchars((string)$h['usuario_nome'].' • '.acf((string)$h['criado_em']),ENT_QUOTES,'UTF-8')?></small><?php if($h['detalhes']):?><div><?=nl2br(htmlspecialchars((string)$h['detalhes'],ENT_QUOTES,'UTF-8'))?></div><?php endif;?></li><?php endforeach;?></ul></div></section><?php endif;?></main><?php require __DIR__.'/partials/footer.php';?>
