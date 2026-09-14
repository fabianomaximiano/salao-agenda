<?php

declare(strict_types=1);
session_start();
require_once __DIR__.'/../includes/cliente-auth.php';
$pdo=getDB(); $slug=trim((string)($_GET['empresa']??$_POST['empresa']??'')); $empresa=clienteEmpresaPorSlug($pdo,$slug);
if(!$empresa){http_response_code(404);exit('Empresa não encontrada ou indisponível.');}
if(isset($_SESSION['user_id']) && ($_SESSION['contexto']??'')==='cliente' && (int)($_SESSION['empresa_id']??0)===(int)$empresa['id']){header('Location: dashboard-cliente.php');exit;}
$erro=(isset($_GET['erro'])&&$_GET['erro']==='google')?'Não foi possível entrar com o Google.':null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 $email=mb_strtolower(trim((string)($_POST['email']??''))); $senha=(string)($_POST['senha']??'');
 if($email===''||$senha==='')$erro='Informe seu e-mail e sua senha.';
 elseif(!filter_var($email,FILTER_VALIDATE_EMAIL))$erro='Informe um e-mail válido.';
 else try{
  $stmt=$pdo->prepare('SELECT id,email,senha_hash,ativo FROM usuarios WHERE email=:email LIMIT 1');$stmt->execute([':email'=>$email]);$u=$stmt->fetch(PDO::FETCH_ASSOC);
  if(!$u || (int)$u['ativo']!==1 || empty($u['senha_hash']) || !password_verify($senha,(string)$u['senha_hash'])){$erro='E-mail ou senha inválidos.';}
  else{
   $c=clienteBuscarVinculo($pdo,(int)$u['id'],(int)$empresa['id']);
   if($c){if((int)$c['cliente_ativo']!==1||(int)$c['pessoa_ativa']!==1)$erro='Este cadastro de cliente está desativado.';else{clienteIniciarSessao($pdo,$u,$c,$empresa);header('Location: dashboard-cliente.php');exit;}}
   else{$_SESSION['cliente_cadastro_pendente']=['origem'=>'senha','usuario_id'=>(int)$u['id'],'email'=>(string)$u['email'],'nome'=>'','google_id'=>null,'foto_url'=>null,'empresa_id'=>(int)$empresa['id'],'empresa_slug'=>(string)$empresa['slug']];header('Location: completar-cadastro-cliente.php?empresa='.rawurlencode((string)$empresa['slug']));exit;}
  }
 }catch(Throwable $e){error_log('Erro login cliente: '.$e->getMessage());$erro='Não foi possível realizar o login agora.';}
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no"><title>Entrar | <?=htmlspecialchars((string)$empresa['nome_fantasia'],ENT_QUOTES,'UTF-8')?></title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"><link rel="stylesheet" href="assets/css/login-cliente.css"></head><body>
<main class="cliente-auth-page">
<div class="container cliente-auth-container">
<div class="row justify-content-center"><div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
<section class="cliente-auth-card">
<header class="cliente-auth-header text-center">
<div class="cliente-auth-brand"><?=htmlspecialchars((string)$empresa['nome_fantasia'],ENT_QUOTES,'UTF-8')?></div>
<div class="cliente-auth-tagline">Beleza que cuida de você</div>
<h1>Olá, que bom ter você aqui</h1>
<p>Entre para cuidar dos seus agendamentos.</p>
</header>

<?php if($erro):?><div class="alert alert-danger"><?=htmlspecialchars($erro,ENT_QUOTES,'UTF-8')?></div><?php endif;?>

<a class="btn-google" href="callback.php?action=auth&amp;empresa=<?=rawurlencode((string)$empresa['slug'])?>">
<span class="google-logo" aria-hidden="true">
<svg viewBox="0 0 24 24" focusable="false"><path fill="#4285F4" d="M21.6 12.23c0-.71-.06-1.39-.18-2.05H12v3.87h5.38a4.6 4.6 0 0 1-2 3.02v2.51h3.24c1.9-1.75 2.98-4.33 2.98-7.35z"/><path fill="#34A853" d="M12 22c2.7 0 4.97-.9 6.63-2.42l-3.24-2.51c-.9.6-2.05.96-3.39.96-2.61 0-4.82-1.76-5.61-4.13H3.04v2.59A10 10 0 0 0 12 22z"/><path fill="#FBBC05" d="M6.39 13.9A6 6 0 0 1 6.08 12c0-.66.11-1.3.31-1.9V7.51H3.04A10 10 0 0 0 2 12c0 1.61.38 3.14 1.04 4.49l3.35-2.59z"/><path fill="#EA4335" d="M12 5.97c1.47 0 2.79.51 3.83 1.5l2.87-2.88A9.63 9.63 0 0 0 12 2a10 10 0 0 0-8.96 5.51l3.35 2.59C7.18 7.73 9.39 5.97 12 5.97z"/></svg>
</span>
<span class="google-divider" aria-hidden="true"></span>
<span class="google-label">Continuar com o Google</span>
<span class="google-arrow" aria-hidden="true">→</span>
</a>

<div class="cliente-auth-separator"><span>ou</span></div>

<form id="loginClienteForm" method="post" action="login-cliente.php" novalidate>
<input type="hidden" name="empresa" value="<?=htmlspecialchars((string)$empresa['slug'],ENT_QUOTES,'UTF-8')?>">

<div class="form-group">
<label for="email">E-mail</label>
<div class="input-shell">
<span class="input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 6h16v12H4zM4 7l8 6 8-6"/></svg></span>
<input type="email" class="form-control" id="email" name="email" autocomplete="email" placeholder="Seu e-mail" required>
</div>
</div>

<div class="form-group">
<label for="senha">Senha</label>
<div class="input-shell">
<span class="input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span>
<input type="password" class="form-control senha-control" id="senha" name="senha" autocomplete="current-password" placeholder="Sua senha" required>
<button type="button" class="senha-toggle" id="senhaToggle" aria-label="Mostrar senha" aria-pressed="false"><span aria-hidden="true">◉</span></button>
</div>
</div>

<div class="cliente-auth-recovery"><a href="esqueci-senha.php">Esqueci minha senha</a></div>
<button class="btn btn-primary btn-lg btn-block cliente-auth-submit" type="submit"><span>Entrar</span><span aria-hidden="true">→</span></button>
</form>

<div class="cliente-auth-footer text-center"><span>Ainda não tem conta?</span> <a href="cadastro-cliente.php?empresa=<?=rawurlencode((string)$empresa['slug'])?>">Criar minha conta</a></div>
</section>

<div class="cliente-benefits" aria-label="Benefícios">
<div class="cliente-benefit"><span class="benefit-icon" aria-hidden="true">▣</span><span><strong>Agende online</strong><small>de forma rápida</small></span></div>
<div class="cliente-benefit"><span class="benefit-icon" aria-hidden="true">◷</span><span><strong>Mais tempo</strong><small>para você</small></span></div>
<div class="cliente-benefit"><span class="benefit-icon" aria-hidden="true">♡</span><span><strong>Seu bem-estar</strong><small>em primeiro lugar</small></span></div>
</div>
</div></div>
</div>
</main>
<script src="assets/js/login-cliente.js"></script></body></html>
