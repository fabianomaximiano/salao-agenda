<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/db.php';
exigirAdministrador();

$empresaId=(int)$_SESSION['empresa_id'];
$pdo=getDB();
if(empty($_SESSION['csrf_cadastro_colaborador'])) $_SESSION['csrf_cadastro_colaborador']=bin2hex(random_bytes(32));
$csrfToken=$_SESSION['csrf_cadastro_colaborador'];
$flash=$_SESSION['flash_colaborador']??null; unset($_SESSION['flash_colaborador']);
$old=is_array($flash['old']??null)?$flash['old']:[];
$erros=is_array($flash['erros']??null)?$flash['erros']:[];
$editarId=filter_input(INPUT_GET,'editar',FILTER_VALIDATE_INT);
$c=null;
if($editarId){
    $stmt=$pdo->prepare(
        'SELECT c.*,p.nome_completo,p.cpf,p.data_nascimento,p.genero,p.email,
                tp.numero AS telefone,COALESCE(tp.whatsapp,0) whatsapp
         FROM colaboradores c
         INNER JOIN pessoas p ON p.id=c.pessoa_id AND p.empresa_id=c.empresa_id
         LEFT JOIN telefones_pessoa tp ON tp.id=(
             SELECT tp2.id FROM telefones_pessoa tp2 WHERE tp2.pessoa_id=p.id
             ORDER BY tp2.principal DESC,tp2.id ASC LIMIT 1
         )
         WHERE c.id=:id AND c.empresa_id=:empresa_id LIMIT 1'
    );
    $stmt->execute([':id'=>$editarId,':empresa_id'=>$empresaId]);
    $c=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$c){header('Location: colaboradores.php');exit;}
}
function vc(array $old,?array $c,string $campo,string $padrao=''):string{
    $v=array_key_exists($campo,$old)?$old[$campo]:($c[$campo]??$padrao);
    return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
}
$modo=$c!==null;
$temOld=$old!==[];
$ativo=$temOld?!empty($old['ativo']):(!$modo||(int)$c['ativo']===1);
$whatsapp=$temOld?!empty($old['whatsapp']):($modo&&(int)$c['whatsapp']===1);
$permissoes=[
'agenda'=>'Agenda','clientes'=>'Clientes','profissionais'=>'Profissionais','servicos'=>'Serviços',
'financeiro'=>'Financeiro','relatorios'=>'Relatórios','configuracoes'=>'Configurações'];
$pageTitle=$modo?'Editar colaborador':'Cadastrar colaborador';
$pageJs='cadastro-colaborador.js';
require __DIR__.'/partials/header.php'; require __DIR__.'/partials/sidebar.php'; require __DIR__.'/partials/navbar.php';
?>
<main class="app-content">
<div class="app-page-header"><h1><?=$modo?'Editar colaborador':'Cadastrar colaborador'?></h1><p>Dados administrativos e permissões de acesso.</p></div>
<?php if(!empty($flash['mensagem'])):?><div class="alert alert-<?=($flash['tipo']??'')==='success'?'success':'danger'?>"><?=htmlspecialchars((string)$flash['mensagem'],ENT_QUOTES,'UTF-8')?></div><?php endif;?>
<form action="api/colaboradores.php" method="post">
<input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrfToken,ENT_QUOTES,'UTF-8')?>">
<input type="hidden" name="acao" value="<?=$modo?'atualizar':'criar'?>">
<?php if($modo):?><input type="hidden" name="colaborador_id" value="<?=(int)$c['id']?>"><?php endif;?>
<div class="row"><div class="col-12 col-xl-8">
<div class="app-card mb-4"><div class="app-card-header"><h2>Dados pessoais</h2></div><div class="app-card-body">
<div class="form-group"><label>Nome completo <span class="text-danger">*</span></label><input class="form-control<?=isset($erros['nome_completo'])?' is-invalid':''?>" name="nome_completo" maxlength="160" required value="<?=vc($old,$c,'nome_completo')?>"><div class="invalid-feedback"><?=htmlspecialchars($erros['nome_completo']??'',ENT_QUOTES,'UTF-8')?></div></div>
<div class="form-row">
<div class="form-group col-md-6"><label for="cpf">CPF</label><input type="text" class="form-control<?=isset($erros['cpf'])?' is-invalid':''?>" id="cpf" name="cpf" maxlength="14" inputmode="numeric" autocomplete="off" placeholder="000.000.000-00" value="<?=vc($old,$c,'cpf')?>" <?=$modo?'disabled':''?>><div class="invalid-feedback"><?=htmlspecialchars($erros['cpf']??'',ENT_QUOTES,'UTF-8')?></div></div>
<div class="form-group col-md-6"><label>Data de nascimento</label><input type="date" class="form-control" name="data_nascimento" value="<?=vc($old,$c,'data_nascimento')?>" <?=$modo?'disabled':''?>></div>
</div>
<div class="form-row">
<div class="form-group col-md-6"><label>Gênero</label><?php $g=$temOld?(string)($old['genero']??'nao_informado'):(string)($c['genero']??'nao_informado');?><select class="custom-select" name="genero"><option value="nao_informado" <?=$g==='nao_informado'?'selected':''?>>Prefiro não informar</option><option value="feminino" <?=$g==='feminino'?'selected':''?>>Feminino</option><option value="masculino" <?=$g==='masculino'?'selected':''?>>Masculino</option><option value="nao_binario" <?=$g==='nao_binario'?'selected':''?>>Não binário</option></select></div>
<div class="form-group col-md-6"><label>E-mail corporativo <span class="text-danger">*</span></label><input type="email" class="form-control<?=isset($erros['email'])?' is-invalid':''?>" name="email" maxlength="190" required value="<?=vc($old,$c,'email')?>"><div class="invalid-feedback"><?=htmlspecialchars($erros['email']??'',ENT_QUOTES,'UTF-8')?></div></div>
</div>
<div class="form-row align-items-end"><div class="form-group col-md-8"><label for="telefone">Telefone</label><input type="tel" class="form-control" id="telefone" name="telefone" maxlength="15" inputmode="tel" autocomplete="tel" placeholder="(11) 99999-9999" value="<?=vc($old,$c,'telefone')?>"></div><div class="form-group col-md-4"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="whatsapp" name="whatsapp" value="1" <?=$whatsapp?'checked':''?>><label class="custom-control-label" for="whatsapp">É WhatsApp</label></div></div></div>
</div></div>
<div class="app-card mb-4"><div class="app-card-header"><h2>Função e permissões</h2></div><div class="app-card-body">
<div class="form-group"><label>Cargo / função</label><input class="form-control" name="cargo" maxlength="120" value="<?=vc($old,$c,'cargo')?>" placeholder="Ex.: Recepcionista, Gerente"></div>
<div class="row"><?php foreach($permissoes as $k=>$rotulo): $campo='pode_'.$k; $marcado=$temOld?!empty($old[$campo]):($modo?(int)$c[$campo]===1:in_array($k,['agenda','clientes','profissionais','servicos'],true));?>
<div class="col-md-6 mb-3"><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="<?=$campo?>" name="<?=$campo?>" value="1" <?=$marcado?'checked':''?>><label class="custom-control-label" for="<?=$campo?>"><?=$rotulo?></label></div></div>
<?php endforeach;?></div>
<div class="custom-control custom-switch mt-2"><input type="checkbox" class="custom-control-input" id="ativo" name="ativo" value="1" <?=$ativo?'checked':''?>><label class="custom-control-label" for="ativo">Colaborador ativo</label></div>
</div></div>
<div class="d-flex justify-content-between mb-4"><a href="colaboradores.php" class="btn btn-outline-secondary">Voltar</a><button class="btn btn-primary" type="submit"><?=$modo?'Salvar alterações':'Salvar colaborador'?></button></div>
</div>
<div class="col-12 col-xl-4">
<?php if($modo):?><div class="app-card mb-4"><div class="app-card-header"><h2>Acesso ao sistema</h2></div><div class="app-card-body">
<p><span class="badge badge-<?=(int)($c['usuario_id']??0)>0?'success':'secondary'?>"><?=(int)($c['usuario_id']??0)>0?'Conta vinculada':'Sem acesso'?></span></p>
<p class="text-muted">O convite será enviado ao e-mail corporativo informado.</p>
<button class="btn btn-outline-primary btn-block" form="formAcessoColaborador" type="submit"><?=(int)($c['usuario_id']??0)>0?'Reenviar convite':'Liberar acesso'?></button>
</div></div><?php endif;?>
</div></div>
</form>
<?php if($modo):?><form id="formAcessoColaborador" action="api/colaboradores.php" method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrfToken,ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="acao" value="liberar_acesso"><input type="hidden" name="colaborador_id" value="<?=(int)$c['id']?>"></form><?php endif;?>
</main>
<?php require __DIR__.'/partials/footer.php';?>
