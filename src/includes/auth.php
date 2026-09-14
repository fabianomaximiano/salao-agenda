<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function usuarioLogado(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['empresa_id'], $_SESSION['contexto']);
}

function exigirLogin(): void
{
    if (!usuarioLogado()) {
        header('Location: login.php');
        exit;
    }
}

function negarAcesso(): void
{
    http_response_code(403);
    $scriptAtual = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
    if ($scriptAtual === 'acesso-negado.php') {
        exit('Acesso negado.');
    }
    header('Location: acesso-negado.php');
    exit;
}

function exigirAdministrador(): void
{
    exigirLogin();
    if (($_SESSION['contexto'] ?? '') !== 'administrador') negarAcesso();
}

function exigirColaborador(): void
{
    exigirLogin();
    if (($_SESSION['contexto'] ?? '') !== 'colaborador' || empty($_SESSION['colaborador_id']) || empty($_SESSION['pessoa_id'])) negarAcesso();
}

function permissoesColaboradorAtuais(): array
{
    static $cache = null;
    $vazias = ['agenda'=>false,'clientes'=>false,'profissionais'=>false,'servicos'=>false,'financeiro'=>false,'relatorios'=>false,'configuracoes'=>false];
    if (($_SESSION['contexto'] ?? '') !== 'colaborador') return $vazias;
    if ($cache !== null) return $cache;
    $colaboradorId=(int)($_SESSION['colaborador_id']??0); $empresaId=(int)($_SESSION['empresa_id']??0); $usuarioId=(int)($_SESSION['user_id']??0);
    if ($colaboradorId<=0 || $empresaId<=0 || $usuarioId<=0) return $cache=$vazias;
    require_once __DIR__.'/db.php'; $pdo=getDB();
    $stmt=$pdo->prepare('SELECT pode_agenda,pode_clientes,pode_profissionais,pode_servicos,pode_financeiro,pode_relatorios,pode_configuracoes FROM colaboradores WHERE id=:colaborador_id AND empresa_id=:empresa_id AND usuario_id=:usuario_id AND ativo=1 LIMIT 1');
    $stmt->execute([':colaborador_id'=>$colaboradorId,':empresa_id'=>$empresaId,':usuario_id'=>$usuarioId]);
    $r=$stmt->fetch(PDO::FETCH_ASSOC); if(!$r) return $cache=$vazias;
    $cache=['agenda'=>(int)$r['pode_agenda']===1,'clientes'=>(int)$r['pode_clientes']===1,'profissionais'=>(int)$r['pode_profissionais']===1,'servicos'=>(int)$r['pode_servicos']===1,'financeiro'=>(int)$r['pode_financeiro']===1,'relatorios'=>(int)$r['pode_relatorios']===1,'configuracoes'=>(int)$r['pode_configuracoes']===1];
    $_SESSION['permissoes_colaborador']=$cache; return $cache;
}

function colaboradorPode(string $permissao): bool
{
    $p=permissoesColaboradorAtuais(); return array_key_exists($permissao,$p) && $p[$permissao]===true;
}

function exigirPermissaoColaborador(string $permissao): void
{
    exigirColaborador(); if(!colaboradorPode($permissao)) negarAcesso();
}

function exigirAcesso(string $permissao): void
{
    exigirLogin(); $contexto=(string)($_SESSION['contexto']??'');
    if($contexto==='administrador') return;
    if($contexto==='colaborador' && colaboradorPode($permissao)) return;
    negarAcesso();
}

function exigirProfissional(): void
{
    exigirLogin();
    if (($_SESSION['contexto'] ?? '') !== 'profissional' || empty($_SESSION['profissional_id']) || empty($_SESSION['pessoa_id'])) negarAcesso();
}

function exigirCliente(): void
{
    exigirLogin();
    if (($_SESSION['contexto'] ?? '') !== 'cliente' || empty($_SESSION['cliente_id']) || empty($_SESSION['pessoa_id'])) negarAcesso();
}

function encerrarSessao(): void
{
    $_SESSION=[];
    if (ini_get('session.use_cookies')) {
        $params=session_get_cookie_params();
        setcookie(session_name(),'',time()-42000,$params['path'],$params['domain'],$params['secure'],$params['httponly']);
    }
    session_destroy();
}
