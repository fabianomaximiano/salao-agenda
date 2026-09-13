<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/db.php';
require_once __DIR__.'/../../services/CodigoVerificacaoService.php';
require_once __DIR__.'/../../services/TokenAtivacaoService.php';
require_once __DIR__.'/../../services/EmailService.php';
exigirAdministrador();

if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Método não permitido.');}
$empresaId=(int)$_SESSION['empresa_id']; $pdo=getDB();
$acao=trim((string)($_POST['acao']??'criar'));
$csrf=(string)($_POST['csrf_token']??'');
$csrfSessao=$acao==='alternar_status'?(string)($_SESSION['csrf_colaboradores']??''):(string)($_SESSION['csrf_cadastro_colaborador']??'');
if($csrf===''||$csrfSessao===''||!hash_equals($csrfSessao,$csrf)){http_response_code(403);exit('Token CSRF inválido.');}

function lista(string $tipo,string $msg):never{$_SESSION['flash_lista_colaboradores']=['tipo'=>$tipo,'mensagem'=>$msg];header('Location: ../colaboradores.php');exit;}
function cadastro(string $tipo,string $msg,?int $id=null,array $erros=[],array $old=[]):never{$_SESSION['flash_colaborador']=['tipo'=>$tipo,'mensagem'=>$msg,'erros'=>$erros,'old'=>$old];$u='../cadastro-colaborador.php'.($id?'?editar='.$id:'');header('Location: '.$u);exit;}
function cpfOk(string $cpf):bool{$cpf=preg_replace('/\D+/','',$cpf)??'';if($cpf==='')return true;if(strlen($cpf)!==11||preg_match('/^(\d)\1{10}$/',$cpf))return false;for($t=9;$t<11;$t++){$s=0;for($i=0;$i<$t;$i++)$s+=(int)$cpf[$i]*(($t+1)-$i);if((int)$cpf[$t]!==((10*$s)%11)%10)return false;}return true;}
function cpfFmt(string $cpf):?string{$d=preg_replace('/\D+/','',$cpf)??'';return $d===''?null:substr($d,0,3).'.'.substr($d,3,3).'.'.substr($d,6,3).'-'.substr($d,9,2);}

if($acao==='alternar_status'){
 $id=filter_input(INPUT_POST,'colaborador_id',FILTER_VALIDATE_INT); if(!$id)lista('danger','Colaborador inválido.');
 $s=$pdo->prepare('SELECT id,ativo FROM colaboradores WHERE id=:id AND empresa_id=:empresa_id LIMIT 1');$s->execute([':id'=>$id,':empresa_id'=>$empresaId]);$c=$s->fetch(PDO::FETCH_ASSOC);if(!$c)lista('danger','Colaborador não encontrado.');
 $novo=(int)$c['ativo']===1?0:1;$u=$pdo->prepare('UPDATE colaboradores SET ativo=:ativo WHERE id=:id AND empresa_id=:empresa_id');$u->execute([':ativo'=>$novo,':id'=>$id,':empresa_id'=>$empresaId]);
 $_SESSION['csrf_colaboradores']=bin2hex(random_bytes(32));lista('success',$novo?'Colaborador ativado com sucesso.':'Colaborador desativado com sucesso.');
}

if($acao==='liberar_acesso'){
 $id=filter_input(INPUT_POST,'colaborador_id',FILTER_VALIDATE_INT);if(!$id)lista('danger','Colaborador inválido.');
 try{
  $pdo->beginTransaction();
  $s=$pdo->prepare('SELECT c.id,c.usuario_id,c.ativo AS colaborador_ativo,c.pessoa_id,p.nome_completo,p.email,p.ativo AS pessoa_ativa,e.nome_fantasia,e.ativo AS empresa_ativa FROM colaboradores c INNER JOIN pessoas p ON p.id=c.pessoa_id AND p.empresa_id=c.empresa_id INNER JOIN empresas e ON e.id=c.empresa_id WHERE c.id=:id AND c.empresa_id=:empresa_id LIMIT 1 FOR UPDATE');
  $s->execute([':id'=>$id,':empresa_id'=>$empresaId]);$c=$s->fetch(PDO::FETCH_ASSOC);if(!$c)throw new RuntimeException('Colaborador não encontrado.');
  if((int)$c['colaborador_ativo']!==1||(int)$c['pessoa_ativa']!==1||(int)$c['empresa_ativa']!==1)throw new RuntimeException('O colaborador e a empresa precisam estar ativos para liberar o acesso.');
  $email=mb_strtolower(trim((string)$c['email']));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Informe um e-mail corporativo válido antes de liberar o acesso.');
  $uid=(int)($c['usuario_id']??0);$novo=false;
  if($uid<=0){
   $q=$pdo->prepare('SELECT id FROM usuarios WHERE email=:email LIMIT 1 FOR UPDATE');$q->execute([':email'=>$email]);if($q->fetchColumn())throw new RuntimeException('Este e-mail já está vinculado a outra conta de acesso.');
   $q=$pdo->prepare('INSERT INTO usuarios(email,senha_hash,google_id,foto_url,ativo) VALUES(:email,NULL,NULL,NULL,0)');$q->execute([':email'=>$email]);$uid=(int)$pdo->lastInsertId();$novo=true;
   $q=$pdo->prepare('UPDATE colaboradores SET usuario_id=:usuario_id WHERE id=:id AND empresa_id=:empresa_id');$q->execute([':usuario_id'=>$uid,':id'=>$id,':empresa_id'=>$empresaId]);
  }else{
   $q=$pdo->prepare('SELECT email,senha_hash,ativo FROM usuarios WHERE id=:id LIMIT 1 FOR UPDATE');$q->execute([':id'=>$uid]);$u=$q->fetch(PDO::FETCH_ASSOC);if(!$u)throw new RuntimeException('A conta vinculada não foi encontrada.');
   if(mb_strtolower((string)$u['email'])!==$email)throw new RuntimeException('O e-mail corporativo está vinculado à conta de acesso e não pode ser alterado.');
   if((int)$u['ativo']===1&&!empty($u['senha_hash']))throw new RuntimeException('O acesso deste colaborador já está ativo.');
  }
  $pdo->commit();
  $cs=new CodigoVerificacaoService();$codigo=$novo?$cs->gerar($pdo,$uid):$cs->reenviar($pdo,$uid);
  $ts=new TokenAtivacaoService();$tokenReferencia=$ts->gerar($pdo,$uid);
  $appUrl=rtrim((string)(getenv('APP_URL')?:'http://localhost:8096'),'/');$linkConfirmacao=$appUrl.'/confirmar-codigo.php?token='.urlencode($tokenReferencia);
  $nome=(string)$c['nome_completo'];$empresa=(string)$c['nome_fantasia'];$validadeMinutos=10;
  ob_start();require __DIR__.'/../../templates/emails/acesso-colaborador-codigo.php';$html=(string)ob_get_clean();
  (new EmailService())->enviar($email,$nome,'Ative seu acesso de colaborador - Salão Agenda',$html,"Olá, {$nome}.\n\nA empresa {$empresa} liberou seu acesso de colaborador.\n\nCódigo: {$codigo}\n\n{$linkConfirmacao}\n\nO código expira em 10 minutos.");
  $_SESSION['csrf_cadastro_colaborador']=bin2hex(random_bytes(32));
  cadastro('success',$novo?'Acesso liberado. Enviamos o convite para o colaborador.':'Novo convite enviado para o colaborador.',$id);
 }catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();cadastro('danger',$e->getMessage(),$id);}
 catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Erro colaborador acesso: '.$e->getMessage());cadastro('danger','Não foi possível liberar o acesso agora.',$id);}
}

if(!in_array($acao,['criar','atualizar'],true)){http_response_code(400);exit('Ação inválida.');}
$id=null;$atual=null;
if($acao==='atualizar'){
 $id=filter_input(INPUT_POST,'colaborador_id',FILTER_VALIDATE_INT);if(!$id)lista('danger','Colaborador inválido.');
 $s=$pdo->prepare('SELECT c.id,c.pessoa_id,c.usuario_id,p.cpf AS cpf_atual,p.data_nascimento AS data_nascimento_atual FROM colaboradores c INNER JOIN pessoas p ON p.id=c.pessoa_id AND p.empresa_id=c.empresa_id WHERE c.id=:id AND c.empresa_id=:empresa_id LIMIT 1');$s->execute([':id'=>$id,':empresa_id'=>$empresaId]);$atual=$s->fetch(PDO::FETCH_ASSOC);if(!$atual)lista('danger','Colaborador não encontrado.');
}
$nome=trim((string)($_POST['nome_completo']??''));$cpfRaw=$acao==='atualizar'?(string)($atual['cpf_atual']??''):trim((string)($_POST['cpf']??''));$data=$acao==='atualizar'?(string)($atual['data_nascimento_atual']??''):trim((string)($_POST['data_nascimento']??''));$genero=trim((string)($_POST['genero']??'nao_informado'));$email=mb_strtolower(trim((string)($_POST['email']??'')));$telefone=trim((string)($_POST['telefone']??''));$whatsapp=isset($_POST['whatsapp'])?1:0;$cargo=trim((string)($_POST['cargo']??''));$ativo=isset($_POST['ativo'])?1:0;
$perms=[];foreach(['agenda','clientes','profissionais','servicos','financeiro','relatorios','configuracoes'] as $p)$perms['pode_'.$p]=isset($_POST['pode_'.$p])?1:0;
$old=array_merge(compact('nome','cpfRaw','data','genero','email','telefone','whatsapp','cargo','ativo'),$perms);$old['nome_completo']=$nome;$old['cpf']=$cpfRaw;$old['data_nascimento']=$data;
$erros=[];if($nome===''||mb_strlen($nome)>160)$erros['nome_completo']='Informe um nome válido.';if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>190)$erros['email']='Informe um e-mail válido.';if(!cpfOk($cpfRaw))$erros['cpf']='Informe um CPF válido.';$cpf=cpfFmt($cpfRaw);
if($acao==='atualizar'&&!empty($atual['usuario_id'])){$q=$pdo->prepare('SELECT email FROM usuarios WHERE id=:id');$q->execute([':id'=>(int)$atual['usuario_id']]);if(mb_strtolower((string)$q->fetchColumn())!==$email)$erros['email']='O e-mail corporativo está vinculado à conta de acesso e não pode ser alterado.';}
if($erros)cadastro('danger','Revise os campos destacados.',$id,$erros,$old);
try{
 $pdo->beginTransaction();
 if($acao==='criar'){
  $q=$pdo->prepare('SELECT id FROM usuarios WHERE email=:email LIMIT 1 FOR UPDATE');
  $q->execute([':email'=>$email]);

  if($q->fetchColumn()){
   throw new RuntimeException('Este e-mail já está vinculado a outra conta de acesso.');
  }

  $q=$pdo->prepare('INSERT INTO usuarios(email,senha_hash,google_id,foto_url,ativo) VALUES(:email,NULL,NULL,NULL,0)');
  $q->execute([':email'=>$email]);
  $usuarioId=(int)$pdo->lastInsertId();

  $q=$pdo->prepare('INSERT INTO pessoas(empresa_id,nome_completo,cpf,data_nascimento,genero,email,observacoes,ativo) VALUES(:empresa_id,:nome,:cpf,:data,:genero,:email,NULL,:ativo)');
  $q->execute([':empresa_id'=>$empresaId,':nome'=>$nome,':cpf'=>$cpf,':data'=>$data!==''?$data:null,':genero'=>$genero,':email'=>$email,':ativo'=>$ativo]);
  $pid=(int)$pdo->lastInsertId();

  $q=$pdo->prepare('INSERT INTO colaboradores(empresa_id,pessoa_id,usuario_id,cargo,pode_agenda,pode_clientes,pode_profissionais,pode_servicos,pode_financeiro,pode_relatorios,pode_configuracoes,ativo) VALUES(:empresa_id,:pessoa_id,:usuario_id,:cargo,:pode_agenda,:pode_clientes,:pode_profissionais,:pode_servicos,:pode_financeiro,:pode_relatorios,:pode_configuracoes,:ativo)');
  $q->execute(array_merge(
      [':empresa_id'=>$empresaId,':pessoa_id'=>$pid,':usuario_id'=>$usuarioId,':cargo'=>$cargo!==''?$cargo:null,':ativo'=>$ativo],
      array_combine(array_map(fn($k)=>':'.$k,array_keys($perms)),array_values($perms))
  ));
 }else{
  $pid=(int)$atual['pessoa_id'];$q=$pdo->prepare('UPDATE pessoas SET nome_completo=:nome,genero=:genero,email=:email,ativo=:ativo WHERE id=:id AND empresa_id=:empresa_id');$q->execute([':nome'=>$nome,':genero'=>$genero,':email'=>$email,':ativo'=>$ativo,':id'=>$pid,':empresa_id'=>$empresaId]);
  $sets='cargo=:cargo,'.implode(',',array_map(fn($k)=>$k.'=:'.$k,array_keys($perms))).',ativo=:ativo';$q=$pdo->prepare('UPDATE colaboradores SET '.$sets.' WHERE id=:id AND empresa_id=:empresa_id');$q->execute(array_merge([':cargo'=>$cargo!==''?$cargo:null,':ativo'=>$ativo,':id'=>$id,':empresa_id'=>$empresaId],array_combine(array_map(fn($k)=>':'.$k,array_keys($perms)),array_values($perms))));
 }
 $q=$pdo->prepare('SELECT id FROM telefones_pessoa WHERE pessoa_id=:pessoa_id ORDER BY principal DESC,id ASC LIMIT 1');$q->execute([':pessoa_id'=>$pid]);$tid=$q->fetchColumn();
 if($telefone!==''){if($tid){$q=$pdo->prepare('UPDATE telefones_pessoa SET numero=:numero,tipo="celular",whatsapp=:whatsapp,principal=1 WHERE id=:id');$q->execute([':numero'=>$telefone,':whatsapp'=>$whatsapp,':id'=>$tid]);}else{$q=$pdo->prepare('INSERT INTO telefones_pessoa(pessoa_id,numero,tipo,whatsapp,principal) VALUES(:pessoa_id,:numero,"celular",:whatsapp,1)');$q->execute([':pessoa_id'=>$pid,':numero'=>$telefone,':whatsapp'=>$whatsapp]);}}elseif($tid){$q=$pdo->prepare('DELETE FROM telefones_pessoa WHERE id=:id');$q->execute([':id'=>$tid]);}
 $pdo->commit();$_SESSION['csrf_cadastro_colaborador']=bin2hex(random_bytes(32));lista('success',$acao==='criar'?'Colaborador cadastrado com sucesso.':'Colaborador atualizado com sucesso.');
}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();cadastro('danger',$e->getMessage(),$id,[],$old);}
catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Erro salvar colaborador: '.$e->getMessage());cadastro('danger','Não foi possível salvar o colaborador.',$id,[],$old);}
