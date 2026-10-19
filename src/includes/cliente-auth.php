<?php

declare(strict_types=1);
require_once __DIR__ . '/db.php';

function clienteEmpresaPorSlug(PDO $pdo, string $slug): ?array
{
    if ($slug === '') return null;
    $stmt=$pdo->prepare('SELECT id,nome_fantasia,slug,logo_url FROM empresas WHERE slug=:slug AND ativo=1 LIMIT 1');
    $stmt->execute([':slug'=>$slug]);
    $r=$stmt->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

function clienteSomenteDigitos(string $valor): string { return preg_replace('/\D+/', '', $valor) ?? ''; }
function clienteDataValida(string $data): bool { $dt=DateTime::createFromFormat('Y-m-d',$data); return $dt instanceof DateTime && $dt->format('Y-m-d')===$data; }
function clienteCpfFormatado(string $valor): string {
    $d=clienteSomenteDigitos($valor);
    return strlen($d)===11 ? substr($d,0,3).'.'.substr($d,3,3).'.'.substr($d,6,3).'-'.substr($d,9,2) : $valor;
}

function clienteIdentidadePorUsuario(PDO $pdo, int $usuarioId, bool $travar=false): ?array
{
    $sql='SELECT id,usuario_id,cpf,email FROM clientes_identidades WHERE usuario_id=:u LIMIT 1'.($travar?' FOR UPDATE':'');
    $s=$pdo->prepare($sql);$s->execute([':u'=>$usuarioId]);$r=$s->fetch(PDO::FETCH_ASSOC);return $r?:null;
}

function clienteBuscarVinculo(PDO $pdo, int $usuarioId, int $empresaId): ?array
{
    $stmt=$pdo->prepare('SELECT c.id AS cliente_id,c.pessoa_id,c.ativo AS cliente_ativo,p.nome_completo,p.ativo AS pessoa_ativa FROM clientes c INNER JOIN pessoas p ON p.id=c.pessoa_id AND p.empresa_id=c.empresa_id INNER JOIN clientes_identidades ci ON ci.id=c.cliente_identidade_id WHERE ci.usuario_id=:usuario_id AND c.empresa_id=:empresa_id LIMIT 1');
    $stmt->execute([':usuario_id'=>$usuarioId,':empresa_id'=>$empresaId]);$r=$stmt->fetch(PDO::FETCH_ASSOC);return $r?:null;
}

function clienteIniciarSessao(PDO $pdo, array $usuario, array $cliente, array $empresa): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']=(int)$usuario['id'];$_SESSION['user_email']=(string)$usuario['email'];$_SESSION['user_name']=(string)$cliente['nome_completo'];
    $_SESSION['empresa_id']=(int)$empresa['id'];$_SESSION['empresa_nome']=(string)$empresa['nome_fantasia'];$_SESSION['cliente_id']=(int)$cliente['cliente_id'];$_SESSION['pessoa_id']=(int)$cliente['pessoa_id'];$_SESSION['contexto']='cliente';
    unset($_SESSION['administrador_id'],$_SESSION['profissional_id'],$_SESSION['colaborador_id'],$_SESSION['permissoes_colaborador'],$_SESSION['cliente_cadastro_pendente']);
    $stmt=$pdo->prepare('UPDATE usuarios SET ultimo_acesso_em=NOW() WHERE id=:id');$stmt->execute([':id'=>(int)$usuario['id']]);
}

function clienteSalvarPerfil(PDO $pdo, array $empresa, int $usuarioId, string $email, array $dados): array
{
    $empresaId=(int)$empresa['id'];$cpf=clienteCpfFormatado((string)$dados['cpf']);$email=mb_strtolower(trim($email));
    $identidade=clienteIdentidadePorUsuario($pdo,$usuarioId,true);
    $s=$pdo->prepare('SELECT id,usuario_id,email FROM clientes_identidades WHERE cpf=:cpf LIMIT 1 FOR UPDATE');$s->execute([':cpf'=>$cpf]);$porCpf=$s->fetch(PDO::FETCH_ASSOC);
    if($identidade && $porCpf && (int)$identidade['id']!==(int)$porCpf['id']) throw new RuntimeException('O CPF informado pertence a outra conta de cliente.');
    if($identidade && (string)$identidade['cpf']!==$cpf) throw new RuntimeException('O CPF informado não corresponde à sua conta.');
    if(!$identidade && $porCpf){
        if(!empty($porCpf['usuario_id']) && (int)$porCpf['usuario_id']!==$usuarioId) throw new RuntimeException('Este CPF já está vinculado a outra conta de acesso.');
        if(!empty($porCpf['email']) && mb_strtolower((string)$porCpf['email'])!==$email) throw new RuntimeException('Este CPF já está cadastrado com outro e-mail.');
        $s=$pdo->prepare('UPDATE clientes_identidades SET usuario_id=:u,email=:e WHERE id=:id');$s->execute([':u'=>$usuarioId,':e'=>$email,':id'=>(int)$porCpf['id']]);
        $identidade=['id'=>(int)$porCpf['id'],'usuario_id'=>$usuarioId,'cpf'=>$cpf,'email'=>$email];
    }
    if(!$identidade){
        $s=$pdo->prepare('INSERT INTO clientes_identidades (usuario_id,cpf,email) VALUES (:u,:cpf,:e)');$s->execute([':u'=>$usuarioId,':cpf'=>$cpf,':e'=>$email]);
        $identidade=['id'=>(int)$pdo->lastInsertId(),'usuario_id'=>$usuarioId,'cpf'=>$cpf,'email'=>$email];
    }
    $s=$pdo->prepare('SELECT id FROM clientes WHERE empresa_id=:e AND cliente_identidade_id=:ci LIMIT 1 FOR UPDATE');$s->execute([':e'=>$empresaId,':ci'=>(int)$identidade['id']]);
    if($s->fetch()) throw new RuntimeException('Seu cadastro de cliente já existe neste estabelecimento.');
    $s=$pdo->prepare('SELECT id FROM pessoas WHERE empresa_id=:e AND cpf=:cpf LIMIT 1 FOR UPDATE');$s->execute([':e'=>$empresaId,':cpf'=>$cpf]);$p=$s->fetch(PDO::FETCH_ASSOC);
    if($p){
        $pessoaId=(int)$p['id'];$s=$pdo->prepare('SELECT id,usuario_id,cliente_identidade_id FROM clientes WHERE empresa_id=:e AND pessoa_id=:p LIMIT 1 FOR UPDATE');$s->execute([':e'=>$empresaId,':p'=>$pessoaId]);$c=$s->fetch(PDO::FETCH_ASSOC);
        if(!$c) throw new RuntimeException('Já existe uma pessoa com este CPF, mas sem cadastro de cliente. Procure o estabelecimento.');
        if(!empty($c['cliente_identidade_id']) && (int)$c['cliente_identidade_id']!==(int)$identidade['id']) throw new RuntimeException('Este CPF já está vinculado a outra identidade.');
        $s=$pdo->prepare('UPDATE clientes SET usuario_id=:u,cliente_identidade_id=:ci,ativo=1 WHERE id=:id');$s->execute([':u'=>$usuarioId,':ci'=>(int)$identidade['id'],':id'=>(int)$c['id']]);$clienteId=(int)$c['id'];
    }else{
        $s=$pdo->prepare('INSERT INTO pessoas (empresa_id,nome_completo,cpf,data_nascimento,genero,email,ativo) VALUES (:e,:n,:cpf,:dn,:g,:em,1)');$s->execute([':e'=>$empresaId,':n'=>$dados['nome_completo'],':cpf'=>$cpf,':dn'=>$dados['data_nascimento'],':g'=>$dados['genero'],':em'=>$email]);$pessoaId=(int)$pdo->lastInsertId();
        $s=$pdo->prepare('INSERT INTO clientes (empresa_id,cliente_identidade_id,pessoa_id,usuario_id,ativo) VALUES (:e,:ci,:p,:u,1)');$s->execute([':e'=>$empresaId,':ci'=>(int)$identidade['id'],':p'=>$pessoaId,':u'=>$usuarioId]);$clienteId=(int)$pdo->lastInsertId();
    }
    $s=$pdo->prepare('UPDATE pessoas SET nome_completo=:n,data_nascimento=:dn,genero=:g,email=:em,ativo=1 WHERE id=:p AND empresa_id=:e');$s->execute([':n'=>$dados['nome_completo'],':dn'=>$dados['data_nascimento'],':g'=>$dados['genero'],':em'=>$email,':p'=>$pessoaId,':e'=>$empresaId]);
    $s=$pdo->prepare('SELECT id FROM telefones_pessoa WHERE pessoa_id=:p AND principal=1 LIMIT 1 FOR UPDATE');$s->execute([':p'=>$pessoaId]);$tid=$s->fetchColumn();
    if($tid){$s=$pdo->prepare('UPDATE telefones_pessoa SET numero=:n,tipo="celular",whatsapp=1,principal=1 WHERE id=:id');$s->execute([':n'=>$dados['celular'],':id'=>$tid]);}else{$s=$pdo->prepare('INSERT INTO telefones_pessoa (pessoa_id,numero,tipo,whatsapp,principal) VALUES (:p,:n,"celular",1,1)');$s->execute([':p'=>$pessoaId,':n'=>$dados['celular']]);}
    $s=$pdo->prepare('INSERT INTO enderecos_pessoa (pessoa_id,cep,logradouro,numero,complemento,bairro,cidade,estado) VALUES (:p,:cep,:l,:n,:c,:b,:cid,:uf) ON DUPLICATE KEY UPDATE cep=VALUES(cep),logradouro=VALUES(logradouro),numero=VALUES(numero),complemento=VALUES(complemento),bairro=VALUES(bairro),cidade=VALUES(cidade),estado=VALUES(estado)');
    $s->execute([':p'=>$pessoaId,':cep'=>$dados['cep']?:null,':l'=>$dados['logradouro']?:null,':n'=>$dados['numero']?:null,':c'=>$dados['complemento']?:null,':b'=>$dados['bairro']?:null,':cid'=>$dados['cidade']?:null,':uf'=>$dados['estado']?:null]);
    return ['cliente_id'=>$clienteId,'pessoa_id'=>$pessoaId,'nome_completo'=>$dados['nome_completo']];
}
