<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/auth.php';require_once __DIR__.'/../../includes/db.php';exigirAdministrador();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Método não permitido.');}
$csrf=(string)($_POST['csrf_token']??'');$cs=(string)($_SESSION['csrf_ausencias_colaboradores']??'');if(!$csrf||!$cs||!hash_equals($cs,$csrf)){http_response_code(403);exit('Token CSRF inválido.');}
$e=(int)$_SESSION['empresa_id'];$u=(int)$_SESSION['user_id'];$acao=(string)($_POST['acao']??'');$id=filter_input(INPUT_POST,'solicitacao_id',FILTER_VALIDATE_INT);$pdo=getDB();
function car(?int$id=null):never{header('Location: ../ausencias-colaboradores.php'.($id?'?detalhe='.$id:''));exit;}function caok(string$m,?int$id=null):never{$_SESSION['flash_success_ausencias_colaboradores']=$m;car($id);}function cafal(string$m,?int$id=null):never{$_SESSION['flash_error_ausencias_colaboradores']=$m;car($id);}
function cah(PDO$p,int$s,int$u,string$a,string$d=''):void{$q=$p->prepare('INSERT INTO colaborador_solicitacao_ausencia_historico(solicitacao_id,usuario_id,acao,detalhes)VALUES(:s,:u,:a,:d)');$q->execute([':s'=>$s,':u'=>$u,':a'=>$a,':d'=>$d?:null]);}
function casol(PDO$p,int$id,int$e,bool$lock=false):?array{$q=$p->prepare("SELECT * FROM colaborador_solicitacoes_ausencia WHERE id=:id AND empresa_id=:e LIMIT 1".($lock?' FOR UPDATE':''));$q->execute([':id'=>$id,':e'=>$e]);$r=$q->fetch(PDO::FETCH_ASSOC);return$r?:null;}
if(!is_int($id)||$id<=0)cafal('Solicitação inválida.');
if($acao==='comentar'){$c=trim((string)($_POST['comentario']??''));if(!$c)cafal('Informe o comentário.',$id);if(!casol($pdo,$id,$e))negarAcesso();$pdo->prepare('INSERT INTO colaborador_solicitacao_ausencia_comentarios(solicitacao_id,usuario_id,comentario)VALUES(:s,:u,:c)')->execute([':s'=>$id,':u'=>$u,':c'=>$c]);caok('Comentário interno adicionado.',$id);}
if($acao==='aprovar'||$acao==='rejeitar'){
    $obs=trim((string)($_POST['observacao_decisao']??''));
    $novo=$acao==='aprovar'?'aprovada':'rejeitada';
    $hist=$acao==='aprovar'?'APROVOU':'REJEITOU';

    $pdo->beginTransaction();
    try{
        $s=casol($pdo,$id,$e,true);
        if(!$s){$pdo->rollBack();negarAcesso();}
        if($s['status']!=='pendente'){$pdo->rollBack();cafal('Solicitação já analisada.',$id);}

        $pdo->prepare("UPDATE colaborador_solicitacoes_ausencia
                       SET status=:st,
                           analisado_por_usuario_id=:u,
                           analisado_em=NOW(),
                           observacao_decisao=:o
                       WHERE id=:id")
            ->execute([':st'=>$novo,':u'=>$u,':o'=>$obs?:null,':id'=>$id]);

        cah($pdo,$id,$u,$hist,$obs);
        $pdo->commit();

        if($acao==='aprovar'){
            try{
                require_once __DIR__.'/../../services/EmailService.php';

                $q=$pdo->prepare('SELECT nome_completo,email
                                  FROM administradores
                                  WHERE empresa_id=:e AND ativo=1
                                  LIMIT 1');
                $q->execute([':e'=>$e]);
                $adminEmpresa=$q->fetch(PDO::FETCH_ASSOC);

                $q=$pdo->prepare('SELECT nome_completo
                                  FROM administradores
                                  WHERE empresa_id=:e
                                    AND usuario_id=:u
                                    AND ativo=1
                                  LIMIT 1');
                $q->execute([':e'=>$e,':u'=>$u]);
                $aprovadorNome=(string)($q->fetchColumn()?:'Administrador');

                $q=$pdo->prepare('SELECT p.nome_completo
                                  FROM colaboradores c
                                  INNER JOIN pessoas p
                                          ON p.id=c.pessoa_id
                                         AND p.empresa_id=c.empresa_id
                                  WHERE c.id=:c
                                    AND c.empresa_id=:e
                                  LIMIT 1');
                $q->execute([':c'=>(int)$s['colaborador_id'],':e'=>$e]);
                $colaboradorNome=(string)($q->fetchColumn()?:'Colaborador');

                if($adminEmpresa && filter_var($adminEmpresa['email'],FILTER_VALIDATE_EMAIL)){
                    $periodo=(new DateTimeImmutable((string)$s['inicio']))->format('d/m/Y H:i')
                        .' – '.
                        (new DateTimeImmutable((string)$s['fim']))->format('d/m/Y H:i');
                    $aprovadoEm=date('d/m/Y H:i');

                    $html='<h2>Ausência de colaborador aprovada</h2>'
                        .'<p>Uma solicitação de ausência de colaborador foi aprovada.</p>'
                        .'<p><strong>Colaborador:</strong> '.htmlspecialchars($colaboradorNome,ENT_QUOTES,'UTF-8').'<br>'
                        .'<strong>Período:</strong> '.htmlspecialchars($periodo,ENT_QUOTES,'UTF-8').'<br>'
                        .'<strong>Aprovado por:</strong> '.htmlspecialchars($aprovadorNome,ENT_QUOTES,'UTF-8').'<br>'
                        .'<strong>Aprovação:</strong> '.htmlspecialchars($aprovadoEm,ENT_QUOTES,'UTF-8').'</p>'
                        .'<p>Consulte a área de ausências de colaboradores para detalhes e histórico.</p>';

                    (new EmailService())->enviar(
                        (string)$adminEmpresa['email'],
                        (string)$adminEmpresa['nome_completo'],
                        'Ausência de colaborador aprovada — '.$colaboradorNome,
                        $html
                    );
                }
            }catch(Throwable$x){
                error_log('E-mail ausência colaborador: '.$x->getMessage());
            }
        }

        caok($acao==='aprovar'
            ?'Ausência do colaborador aprovada.'
            :'Solicitação rejeitada.',$id);
    }catch(Throwable$x){
        if($pdo->inTransaction())$pdo->rollBack();
        throw$x;
    }
}
if($acao==='cancelar_aprovacao'){$m=trim((string)($_POST['motivo_cancelamento']??''));if(!$m)cafal('Informe o motivo.',$id);$pdo->beginTransaction();try{$s=casol($pdo,$id,$e,true);if(!$s){$pdo->rollBack();negarAcesso();}if($s['status']!=='aprovada'){$pdo->rollBack();cafal('Solicitação não aprovada.',$id);}$pdo->prepare("UPDATE colaborador_solicitacoes_ausencia SET status='cancelada' WHERE id=:id")->execute([':id'=>$id]);cah($pdo,$id,$u,'CANCELOU_APROVACAO',$m);$pdo->commit();caok('Aprovação cancelada; histórico preservado.',$id);}catch(Throwable$x){if($pdo->inTransaction())$pdo->rollBack();throw$x;}}
cafal('Ação inválida.',$id);
