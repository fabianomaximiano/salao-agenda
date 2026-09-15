<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/db.php';
exigirColaborador();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Método não permitido.');}
$csrf=(string)($_POST['csrf_token']??'');$cs=(string)($_SESSION['csrf_ausencias_colaborador']??'');
if(!$csrf||!$cs||!hash_equals($cs,$csrf)){http_response_code(403);exit('Token CSRF inválido.');}
$e=(int)($_SESSION['empresa_id']??0);$u=(int)($_SESSION['user_id']??0);$c=(int)($_SESSION['colaborador_id']??0);$acao=(string)($_POST['acao']??'');$pdo=getDB();
function acr(?int$id=null):never{header('Location: ../ausencias-colaborador.php'.($id?'?detalhe='.$id:''));exit;}
function acok(string$m,?int$id=null):never{$_SESSION['flash_success_ausencia_colaborador']=$m;acr($id);}
function acfal(string$m,?int$id=null):never{$_SESSION['flash_error_ausencia_colaborador']=$m;acr($id);}
function ach(PDO$p,int$s,int$u,string$a,string$d=''):void{$q=$p->prepare('INSERT INTO colaborador_solicitacao_ausencia_historico(solicitacao_id,usuario_id,acao,detalhes)VALUES(:s,:u,:a,:d)');$q->execute([':s'=>$s,':u'=>$u,':a'=>$a,':d'=>$d?:null]);}
function acsol(PDO$p,int$id,int$e,bool$lock=false):?array{$q=$p->prepare("SELECT * FROM colaborador_solicitacoes_ausencia WHERE id=:id AND empresa_id=:e LIMIT 1".($lock?' FOR UPDATE':''));$q->execute([':id'=>$id,':e'=>$e]);$r=$q->fetch(PDO::FETCH_ASSOC);return$r?:null;}
if($e<=0||$u<=0||$c<=0)negarAcesso();
if($acao==='solicitar'){
    $tipos=['pausa','almoco','folga','falta','atestado','ferias','compromisso','treinamento','emergencial','outro'];$t=(string)($_POST['tipo']??'');
    try{$i=new DateTimeImmutable((string)$_POST['inicio']);$f=new DateTimeImmutable((string)$_POST['fim']);}catch(Throwable$x){acfal('Período inválido.');}
    if(!in_array($t,$tipos,true)||$f<=$i)acfal('Período inválido.');
    $m=trim((string)($_POST['motivo']??''));$o=trim((string)($_POST['observacao']??''));
    $pdo->beginTransaction();
    try{$q=$pdo->prepare('INSERT INTO colaborador_solicitacoes_ausencia(empresa_id,colaborador_id,inicio,fim,tipo,motivo,observacao,solicitado_por_usuario_id)VALUES(:e,:c,:i,:f,:t,:m,:o,:u)');
        $q->execute([':e'=>$e,':c'=>$c,':i'=>$i->format('Y-m-d H:i:s'),':f'=>$f->format('Y-m-d H:i:s'),':t'=>$t,':m'=>$m?:null,':o'=>$o?:null,':u'=>$u]);
        $id=(int)$pdo->lastInsertId();ach($pdo,$id,$u,'SOLICITOU',$m);$pdo->commit();acok('Solicitação enviada para análise.',$id);
    }catch(Throwable$x){if($pdo->inTransaction())$pdo->rollBack();throw$x;}
}
$id=filter_input(INPUT_POST,'solicitacao_id',FILTER_VALIDATE_INT);if(!is_int($id)||$id<=0)acfal('Solicitação inválida.');
if($acao==='cancelar_pendente'){
    $pdo->beginTransaction();
    try{$s=acsol($pdo,$id,$e,true);if(!$s||(int)$s['colaborador_id']!==$c){$pdo->rollBack();negarAcesso();}
        if($s['status']!=='pendente'){$pdo->rollBack();acfal('Somente solicitação pendente pode ser cancelada.',$id);}
        $pdo->prepare("UPDATE colaborador_solicitacoes_ausencia SET status='cancelada' WHERE id=:id")->execute([':id'=>$id]);
        ach($pdo,$id,$u,'CANCELOU_SOLICITACAO');$pdo->commit();acok('Solicitação cancelada.',$id);
    }catch(Throwable$x){if($pdo->inTransaction())$pdo->rollBack();throw$x;}
}
acfal('Ação inválida.',$id);
