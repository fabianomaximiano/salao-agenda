<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function clienteEmpresaPorSlug(PDO $pdo, string $slug): ?array
{
    if ($slug === '') return null;
    $stmt=$pdo->prepare('SELECT id,nome_fantasia,slug,logo_url FROM empresas WHERE slug=:slug AND ativo=1 LIMIT 1');
    $stmt->execute([':slug'=>$slug]); $r=$stmt->fetch(PDO::FETCH_ASSOC); return $r ?: null;
}

function clienteSomenteDigitos(string $valor): string
{
    return preg_replace('/\D+/', '', $valor) ?? '';
}

function clienteDataValida(string $data): bool
{
    $dt=DateTime::createFromFormat('Y-m-d',$data); return $dt instanceof DateTime && $dt->format('Y-m-d')===$data;
}

function clienteBuscarVinculo(PDO $pdo, int $usuarioId, int $empresaId): ?array
{
    $stmt=$pdo->prepare('SELECT c.id AS cliente_id,c.pessoa_id,c.ativo AS cliente_ativo,p.nome_completo,p.ativo AS pessoa_ativa FROM clientes c INNER JOIN pessoas p ON p.id=c.pessoa_id AND p.empresa_id=c.empresa_id WHERE c.usuario_id=:usuario_id AND c.empresa_id=:empresa_id LIMIT 1');
    $stmt->execute([':usuario_id'=>$usuarioId,':empresa_id'=>$empresaId]); $r=$stmt->fetch(PDO::FETCH_ASSOC); return $r ?: null;
}

function clienteIniciarSessao(PDO $pdo, array $usuario, array $cliente, array $empresa): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']=(int)$usuario['id']; $_SESSION['user_email']=(string)$usuario['email']; $_SESSION['user_name']=(string)$cliente['nome_completo'];
    $_SESSION['empresa_id']=(int)$empresa['id']; $_SESSION['empresa_nome']=(string)$empresa['nome_fantasia']; $_SESSION['cliente_id']=(int)$cliente['cliente_id']; $_SESSION['pessoa_id']=(int)$cliente['pessoa_id']; $_SESSION['contexto']='cliente';
    unset($_SESSION['administrador_id'],$_SESSION['profissional_id'],$_SESSION['colaborador_id'],$_SESSION['permissoes_colaborador'],$_SESSION['cliente_cadastro_pendente']);
    $stmt=$pdo->prepare('UPDATE usuarios SET ultimo_acesso_em=NOW() WHERE id=:id'); $stmt->execute([':id'=>(int)$usuario['id']]);
}

function clienteSalvarPerfil(PDO $pdo, array $empresa, int $usuarioId, string $email, array $dados): array
{
    $empresaId=(int)$empresa['id'];
    $stmt=$pdo->prepare('SELECT id FROM clientes WHERE empresa_id=:empresa_id AND usuario_id=:usuario_id LIMIT 1 FOR UPDATE');
    $stmt->execute([':empresa_id'=>$empresaId,':usuario_id'=>$usuarioId]); if($stmt->fetch()) throw new RuntimeException('Seu cadastro de cliente já existe neste estabelecimento.');

    $stmt=$pdo->prepare('SELECT id FROM pessoas WHERE empresa_id=:empresa_id AND cpf=:cpf LIMIT 1 FOR UPDATE');
    $stmt->execute([':empresa_id'=>$empresaId,':cpf'=>$dados['cpf']]); $p=$stmt->fetch(PDO::FETCH_ASSOC);

    if($p){
        $pessoaId=(int)$p['id'];
        $stmt=$pdo->prepare('SELECT id,usuario_id FROM clientes WHERE empresa_id=:empresa_id AND pessoa_id=:pessoa_id LIMIT 1 FOR UPDATE');
        $stmt->execute([':empresa_id'=>$empresaId,':pessoa_id'=>$pessoaId]); $c=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$c) throw new RuntimeException('Já existe uma pessoa com este CPF, mas sem cadastro de cliente. Procure o estabelecimento.');
        if(!empty($c['usuario_id']) && (int)$c['usuario_id']!==$usuarioId) throw new RuntimeException('Este CPF já está vinculado a outra conta de acesso.');
        $stmt=$pdo->prepare('UPDATE pessoas SET nome_completo=:nome,data_nascimento=:nascimento,genero=:genero,email=:email,ativo=1 WHERE id=:id AND empresa_id=:empresa_id');
        $stmt->execute([':nome'=>$dados['nome_completo'],':nascimento'=>$dados['data_nascimento'],':genero'=>$dados['genero'],':email'=>$email,':id'=>$pessoaId,':empresa_id'=>$empresaId]);
        $stmt=$pdo->prepare('UPDATE clientes SET usuario_id=:usuario_id,ativo=1 WHERE id=:id'); $stmt->execute([':usuario_id'=>$usuarioId,':id'=>(int)$c['id']]); $clienteId=(int)$c['id'];
    } else {
        $stmt=$pdo->prepare('INSERT INTO pessoas (empresa_id,nome_completo,cpf,data_nascimento,genero,email,ativo) VALUES (:empresa_id,:nome,:cpf,:nascimento,:genero,:email,1)');
        $stmt->execute([':empresa_id'=>$empresaId,':nome'=>$dados['nome_completo'],':cpf'=>$dados['cpf'],':nascimento'=>$dados['data_nascimento'],':genero'=>$dados['genero'],':email'=>$email]); $pessoaId=(int)$pdo->lastInsertId();
        $stmt=$pdo->prepare('INSERT INTO clientes (empresa_id,pessoa_id,usuario_id,ativo) VALUES (:empresa_id,:pessoa_id,:usuario_id,1)');
        $stmt->execute([':empresa_id'=>$empresaId,':pessoa_id'=>$pessoaId,':usuario_id'=>$usuarioId]); $clienteId=(int)$pdo->lastInsertId();
    }

    $stmt=$pdo->prepare('SELECT id FROM telefones_pessoa WHERE pessoa_id=:pessoa_id AND principal=1 LIMIT 1 FOR UPDATE'); $stmt->execute([':pessoa_id'=>$pessoaId]); $tel=$stmt->fetchColumn();
    if($tel){$stmt=$pdo->prepare('UPDATE telefones_pessoa SET numero=:numero,tipo="celular",whatsapp=1,principal=1 WHERE id=:id');$stmt->execute([':numero'=>$dados['celular'],':id'=>(int)$tel]);}
    else{$stmt=$pdo->prepare('INSERT INTO telefones_pessoa (pessoa_id,numero,tipo,whatsapp,principal) VALUES (:pessoa_id,:numero,"celular",1,1)');$stmt->execute([':pessoa_id'=>$pessoaId,':numero'=>$dados['celular']]);}

    $stmt=$pdo->prepare('INSERT INTO enderecos_pessoa (pessoa_id,cep,logradouro,numero,complemento,bairro,cidade,estado) VALUES (:pessoa_id,:cep,:logradouro,:numero,:complemento,:bairro,:cidade,:estado) ON DUPLICATE KEY UPDATE cep=VALUES(cep),logradouro=VALUES(logradouro),numero=VALUES(numero),complemento=VALUES(complemento),bairro=VALUES(bairro),cidade=VALUES(cidade),estado=VALUES(estado)');
    $stmt->execute([':pessoa_id'=>$pessoaId,':cep'=>$dados['cep']?:null,':logradouro'=>$dados['logradouro']?:null,':numero'=>$dados['numero']?:null,':complemento'=>$dados['complemento']?:null,':bairro'=>$dados['bairro']?:null,':cidade'=>$dados['cidade']?:null,':estado'=>$dados['estado']?:null]);
    return ['cliente_id'=>$clienteId,'pessoa_id'=>$pessoaId,'nome_completo'=>$dados['nome_completo']];
}
