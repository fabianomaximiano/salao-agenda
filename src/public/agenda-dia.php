<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
exigirLogin();

$contexto=(string)($_SESSION['contexto']??'');
$admin=$contexto==='administrador';
$prof=$contexto==='profissional';
$colab=$contexto==='colaborador';
if($colab){ exigirAcesso('agenda'); }
elseif(!$admin&&!$prof){ http_response_code(403); exit('Acesso negado.'); }

$empresaId=(int)($_SESSION['empresa_id']??0);
$profSessao=$prof?(int)($_SESSION['profissional_id']??0):0;
if($empresaId<=0||($prof&&$profSessao<=0)){http_response_code(403);exit('Acesso negado.');}
$pdo=getDB();
if (empty($_SESSION['csrf_agendamentos']) || !is_string($_SESSION['csrf_agendamentos'])) {
    $_SESSION['csrf_agendamentos'] = bin2hex(random_bytes(32));
}
$csrfAgendamentos = (string) $_SESSION['csrf_agendamentos'];
$tz=new DateTimeZone('America/Sao_Paulo');
$hoje=new DateTimeImmutable('today',$tz);
$data=(string)($_GET['data']??$hoje->format('Y-m-d'));
$d=DateTimeImmutable::createFromFormat('!Y-m-d',$data,$tz);
if(!$d||$d->format('Y-m-d')!==$data){$d=$hoje;$data=$d->format('Y-m-d');}
$diaSemana=(int)$d->format('N');
$filtro=$prof?$profSessao:(int)($_GET['profissional']??0);

$q=$pdo->prepare('SELECT pr.id,p.nome_completo FROM profissionais pr INNER JOIN pessoas p ON p.id=pr.pessoa_id AND p.empresa_id=pr.empresa_id WHERE pr.empresa_id=:e AND pr.ativo=1 AND p.ativo=1 ORDER BY p.nome_completo');
$q->execute([':e'=>$empresaId]); $todos=$q->fetchAll(PDO::FETCH_ASSOC);
$ids=array_map(fn($x)=>(int)$x['id'],$todos);
if($prof&&!in_array($profSessao,$ids,true)){http_response_code(403);exit('Acesso negado.');}
if(!$prof&&$filtro>0&&!in_array($filtro,$ids,true))$filtro=0;
$profissionais=$filtro>0?array_values(array_filter($todos,fn($x)=>(int)$x['id']===$filtro)):$todos;

$q=$pdo->prepare('SELECT hora_inicio,hora_fim FROM empresa_horarios WHERE empresa_id=:e AND dia_semana=:d AND ativo=1 ORDER BY hora_inicio');
$q->execute([':e'=>$empresaId,':d'=>$diaSemana]); $empresaPeriodos=$q->fetchAll(PDO::FETCH_ASSOC);
$q=$pdo->prepare('SELECT id,tipo,descricao FROM empresa_excecoes WHERE empresa_id=:e AND data_excecao=:d AND ativo=1 LIMIT 1');
$q->execute([':e'=>$empresaId,':d'=>$data]); $exc=$q->fetch(PDO::FETCH_ASSOC);
if($exc&&$exc['tipo']==='fechado')$empresaPeriodos=[];
elseif($exc&&$exc['tipo']==='horario_especial'){
 $q=$pdo->prepare('SELECT hora_inicio,hora_fim FROM empresa_excecao_periodos WHERE empresa_excecao_id=:i ORDER BY hora_inicio');
 $q->execute([':i'=>(int)$exc['id']]);$empresaPeriodos=$q->fetchAll(PDO::FETCH_ASSOC);
}

$hor=[];$ags=[];$bloqs=[];
if($profissionais){
 $pids=array_map(fn($x)=>(int)$x['id'],$profissionais);$ph=implode(',',array_fill(0,count($pids),'?'));
 $q=$pdo->prepare("SELECT profissional_id,hora_inicio,hora_fim FROM profissional_horarios WHERE profissional_id IN ($ph) AND dia_semana=? AND ativo=1");
 $q->execute([...$pids,$diaSemana]);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $x)$hor[(int)$x['profissional_id']][]=$x;
 $q=$pdo->prepare("SELECT profissional_id,inicio,fim,motivo FROM profissional_bloqueios WHERE profissional_id IN ($ph) AND inicio<=? AND fim>=?");
 $q->execute([...$pids,$data.' 23:59:59',$data.' 00:00:00']);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $x){$x['ini']=new DateTimeImmutable($x['inicio'],$tz);$x['fimdt']=new DateTimeImmutable($x['fim'],$tz);$bloqs[(int)$x['profissional_id']][]=$x;}
 $q=$pdo->prepare("SELECT a.id,a.inicio,a.fim,a.status AS status_geral,ags.id AS agendamento_servico_id,ags.status,ags.profissional_id,s.nome servico,pc.nome_completo cliente FROM agendamentos a INNER JOIN clientes c ON c.id=a.cliente_id AND c.empresa_id=a.empresa_id INNER JOIN pessoas pc ON pc.id=c.pessoa_id AND pc.empresa_id=a.empresa_id INNER JOIN agendamento_servicos ags ON ags.agendamento_id=a.id AND ags.ordem=1 INNER JOIN servicos s ON s.id=ags.servico_id AND s.empresa_id=a.empresa_id WHERE a.empresa_id=? AND a.inicio>=? AND a.inicio<=? AND ags.profissional_id IN ($ph) AND a.status NOT IN ('cancelado','nao_compareceu') ORDER BY a.inicio");
 $q->execute([$empresaId,$data.' 00:00:00',$data.' 23:59:59',...$pids]);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $x){$x['ini']=new DateTimeImmutable($x['inicio'],$tz);$x['fimdt']=new DateTimeImmutable($x['fim'],$tz);$ags[(int)$x['profissional_id']][]=$x;}
}
function mins(string $h):int{[$a,$b]=array_map('intval',explode(':',substr($h,0,5)));return $a*60+$b;}
function aberto(int $m,array $ps,string $a='hora_inicio',string $b='hora_fim'):bool{foreach($ps as $p)if($m>=mins($p[$a])&&$m<mins($p[$b]))return true;return false;}
$ini=8*60;$fim=18*60;if($empresaPeriodos){$ini=min(array_map(fn($x)=>mins($x['hora_inicio']),$empresaPeriodos));$fim=max(array_map(fn($x)=>mins($x['hora_fim']),$empresaPeriodos));}
$slots=[];for($m=$ini;$m<$fim;$m+=15)$slots[]=$m;
$pageTitle=$prof?'Minha agenda do dia':'Agenda do dia';$pageCss='agenda-dia.css?v=20260915-2';
require __DIR__.'/partials/header.php';require __DIR__.'/partials/sidebar.php';require __DIR__.'/partials/navbar.php';
?>
<main class="app-content">
<div class="app-page-header agenda-dia-head"><div><h1><?= $prof?'Minha agenda':'Agenda do dia' ?></h1><p><?= $d->format('d/m/Y') ?> — visão operacional.</p></div><div class="agenda-dia-actions"><a class="btn btn-outline-secondary" href="agenda.php?mes=<?= $d->format('Y-m') ?>">Visão mensal</a><a class="btn btn-outline-primary" href="agendamentos.php?data_inicio=<?= $data ?>&amp;data_fim=<?= $data ?>">Agendamentos</a></div></div>
<section class="app-card mb-4"><div class="app-card-body agenda-dia-toolbar"><div><a class="btn btn-outline-secondary" href="?data=<?= $d->modify('-1 day')->format('Y-m-d') ?>">‹</a> <a class="btn btn-outline-primary" href="?data=<?= $hoje->format('Y-m-d') ?>">Hoje</a> <a class="btn btn-outline-secondary" href="?data=<?= $d->modify('+1 day')->format('Y-m-d') ?>">›</a></div>
<?php if(!$prof):?><form method="get"><input type="hidden" name="data" value="<?= $data ?>"><label>Profissional</label><select class="form-control" name="profissional" onchange="this.form.submit()"><option value="">Todos</option><?php foreach($todos as $p):?><option value="<?= (int)$p['id'] ?>" <?= (int)$p['id']===$filtro?'selected':'' ?>><?= htmlspecialchars($p['nome_completo']) ?></option><?php endforeach;?></select></form><?php endif;?></div></section>
<?php if(!$empresaPeriodos):?><div class="alert alert-secondary">Empresa fechada nesta data.</div><?php endif;?>
<section class="app-card agenda-dia-desktop"><div class="agenda-dia-scroll"><div class="agenda-dia-grid" style="--cols:<?= count($profissionais) ?>">
<div class="agenda-dia-corner">Horário</div><?php foreach($profissionais as $p):?><div class="agenda-dia-prof"><?= htmlspecialchars($p['nome_completo']) ?></div><?php endforeach;?>
<?php foreach($slots as $m):$hora=sprintf('%02d:%02d',intdiv($m,60),$m%60);$dt=$d->setTime(intdiv($m,60),$m%60);?>
<div class="agenda-dia-hora"><?= $hora ?></div>
<?php foreach($profissionais as $p):$pid=(int)$p['id'];$ok=aberto($m,$empresaPeriodos)&&aberto($m,$hor[$pid]??[]);$ag=null;$bl=null;foreach($ags[$pid]??[] as $x)if($dt>=$x['ini']&&$dt<$x['fimdt']){$ag=$x;break;}foreach($bloqs[$pid]??[] as $x)if($dt>=$x['ini']&&$dt<$x['fimdt']){$bl=$x;break;}?>
<div class="agenda-dia-slot <?= !$ok?'fora':'' ?> <?= $ag?'ocupado':'' ?> <?= $bl?'bloqueado':'' ?>">
<?php if($ag&&$dt==$ag['ini']):
$duracaoMinutos=max(1,(int)(($ag['fimdt']->getTimestamp()-$ag['ini']->getTimestamp())/60));
$alturaAgendamento=($duracaoMinutos/15)*44;
?><div class="agenda-dia-ag agenda-dia-ag--<?= htmlspecialchars($ag['status'], ENT_QUOTES, 'UTF-8') ?>" style="height:<?= number_format($alturaAgendamento-4,2,'.','') ?>px"><strong><?= htmlspecialchars($ag['cliente'], ENT_QUOTES, 'UTF-8') ?></strong><span><?= htmlspecialchars($ag['servico'], ENT_QUOTES, 'UTF-8') ?></span><small><?= $ag['ini']->format('H:i') ?>–<?= $ag['fimdt']->format('H:i') ?></small><?php if($prof && in_array((string)$ag['status'],['confirmado','em_atendimento'],true)): $novoStatus=$ag['status']==='confirmado'?'em_atendimento':'concluido'; ?><form action="api/agendamentos.php" method="post" class="mt-1"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfAgendamentos, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="acao" value="alterar_status"><input type="hidden" name="agendamento_id" value="<?= (int)$ag['id'] ?>"><input type="hidden" name="agendamento_servico_id" value="<?= (int)$ag['agendamento_servico_id'] ?>"><input type="hidden" name="status" value="<?= $novoStatus ?>"><input type="hidden" name="retorno_data" value="<?= htmlspecialchars($data, ENT_QUOTES, 'UTF-8') ?>"><button type="submit" class="btn btn-sm btn-light"><?= $novoStatus==='em_atendimento'?'▶ Iniciar':'✓ Finalizar' ?></button></form><?php elseif(!$prof): ?><a class="btn btn-sm btn-light mt-1" href="agendamentos.php?data_inicio=<?= $data ?>&amp;data_fim=<?= $data ?>">Detalhes</a><?php else: ?><small><?= htmlspecialchars(match((string)$ag['status']){'agendado'=>'Agendado','concluido'=>'Concluído','cancelado'=>'Cancelado','nao_compareceu'=>'Não compareceu',default=>ucfirst(str_replace('_',' ',(string)$ag['status']))},ENT_QUOTES,'UTF-8') ?></small><?php endif; ?></div>
<?php elseif($bl):?><span class="agenda-dia-block"><?= htmlspecialchars($bl['motivo']?:'Bloqueado') ?></span><?php elseif(!$ok):?><span class="agenda-dia-off">—</span><?php endif;?></div>
<?php endforeach;endforeach;?></div></div></section>
<div class="agenda-dia-mobile"><?php foreach($profissionais as $p):$pid=(int)$p['id'];?><section class="app-card mb-3"><div class="app-card-header"><strong><?= htmlspecialchars($p['nome_completo']) ?></strong></div><div class="app-card-body"><?php if(empty($ags[$pid])):?><span class="text-muted">Nenhum atendimento.</span><?php else:foreach($ags[$pid] as $a):?><div class="agenda-dia-mobile-item"><strong><?= $a['ini']->format('H:i') ?>–<?= $a['fimdt']->format('H:i') ?></strong><span><?= htmlspecialchars($a['cliente'], ENT_QUOTES, 'UTF-8') ?></span><small><?= htmlspecialchars($a['servico'], ENT_QUOTES, 'UTF-8') ?></small><?php if($prof && in_array((string)$a['status'],['confirmado','em_atendimento'],true)): $novoStatus=$a['status']==='confirmado'?'em_atendimento':'concluido'; ?><form action="api/agendamentos.php" method="post" class="mt-2"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfAgendamentos, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="acao" value="alterar_status"><input type="hidden" name="agendamento_id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="agendamento_servico_id" value="<?= (int)$a['agendamento_servico_id'] ?>"><input type="hidden" name="status" value="<?= $novoStatus ?>"><input type="hidden" name="retorno_data" value="<?= htmlspecialchars($data, ENT_QUOTES, 'UTF-8') ?>"><button type="submit" class="btn btn-sm btn-primary"><?= $novoStatus==='em_atendimento'?'▶ Iniciar atendimento':'✓ Finalizar atendimento' ?></button></form><?php elseif(!$prof): ?><a class="btn btn-sm btn-outline-primary mt-2" href="agendamentos.php?data_inicio=<?= $data ?>&amp;data_fim=<?= $data ?>">Ver detalhes</a><?php else: ?><small class="mt-1"><?= htmlspecialchars(match((string)$a['status']){'agendado'=>'Agendado','concluido'=>'Concluído','cancelado'=>'Cancelado','nao_compareceu'=>'Não compareceu',default=>ucfirst(str_replace('_',' ',(string)$a['status']))},ENT_QUOTES,'UTF-8') ?></small><?php endif; ?></div><?php endforeach;endif;?></div></section><?php endforeach;?></div>
</main><?php require __DIR__.'/partials/footer.php';?>
