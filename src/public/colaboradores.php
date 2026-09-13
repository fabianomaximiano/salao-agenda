<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
exigirAdministrador();

$empresaId=(int)$_SESSION['empresa_id'];
$pdo=getDB();

if (empty($_SESSION['csrf_colaboradores'])) {
    $_SESSION['csrf_colaboradores']=bin2hex(random_bytes(32));
}
$csrfToken=$_SESSION['csrf_colaboradores'];
$flash=$_SESSION['flash_lista_colaboradores']??null;
unset($_SESSION['flash_lista_colaboradores']);

$stmt=$pdo->prepare(
    'SELECT c.id,c.cargo,c.ativo,c.usuario_id,
            c.pode_agenda,c.pode_clientes,c.pode_profissionais,c.pode_servicos,
            c.pode_financeiro,c.pode_relatorios,c.pode_configuracoes,
            p.nome_completo,p.email
     FROM colaboradores c
     INNER JOIN pessoas p ON p.id=c.pessoa_id AND p.empresa_id=c.empresa_id
     WHERE c.empresa_id=:empresa_id
     ORDER BY c.ativo DESC,p.nome_completo ASC'
);
$stmt->execute([':empresa_id'=>$empresaId]);
$colaboradores=$stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle='Colaboradores';
require __DIR__.'/partials/header.php';
require __DIR__.'/partials/sidebar.php';
require __DIR__.'/partials/navbar.php';
?>
<main class="app-content">
    <div class="app-page-header d-md-flex justify-content-between align-items-center">
        <div><h1>Colaboradores</h1><p>Gerencie a equipe administrativa e suas permissões.</p></div>
        <div class="mt-3 mt-md-0"><a href="cadastro-colaborador.php" class="btn btn-primary">Cadastrar colaborador</a></div>
    </div>

    <?php if (is_array($flash)): ?>
        <div class="alert alert-<?= ($flash['tipo']??'')==='success'?'success':'danger' ?>">
            <?= htmlspecialchars((string)($flash['mensagem']??''),ENT_QUOTES,'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!$colaboradores): ?>
        <div class="app-card"><div class="app-empty-state">
            <h3>Nenhum colaborador cadastrado</h3>
            <p>Cadastre recepcionistas, gerentes ou outros membros da equipe administrativa.</p>
            <a href="cadastro-colaborador.php" class="btn btn-primary">Cadastrar primeiro colaborador</a>
        </div></div>
    <?php else: ?>
        <div class="app-card">
            <div class="app-card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Nome</th><th>Cargo</th><th>Acesso</th><th>Permissões</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach($colaboradores as $c): ?>
                            <?php
                            $perms=[];
                            foreach(['agenda'=>'Agenda','clientes'=>'Clientes','profissionais'=>'Profissionais','servicos'=>'Serviços','financeiro'=>'Financeiro','relatorios'=>'Relatórios','configuracoes'=>'Configurações'] as $k=>$rotulo){
                                if((int)$c['pode_'.$k]===1){$perms[]=$rotulo;}
                            }
                            ?>
                            <tr>
                                <td><strong><?=htmlspecialchars((string)$c['nome_completo'],ENT_QUOTES,'UTF-8')?></strong><br><small class="text-muted"><?=htmlspecialchars((string)($c['email']??''),ENT_QUOTES,'UTF-8')?></small></td>
                                <td><?=htmlspecialchars((string)($c['cargo']??'—'),ENT_QUOTES,'UTF-8')?></td>
                                <td>
                                    <span class="badge badge-<?=(int)$c['ativo']===1?'success':'secondary'?>"><?=(int)$c['ativo']===1?'Ativo':'Inativo'?></span>
                                    <?php if((int)($c['usuario_id']??0)>0): ?><span class="badge badge-info">Conta vinculada</span><?php else: ?><span class="badge badge-light">Sem acesso</span><?php endif; ?>
                                </td>
                                <td><?=htmlspecialchars($perms?implode(', ',$perms):'Nenhuma',ENT_QUOTES,'UTF-8')?></td>
                                <td class="text-right">
                                    <a href="cadastro-colaborador.php?editar=<?=(int)$c['id']?>" class="btn btn-outline-primary btn-sm">Editar</a>
                                    <form action="api/colaboradores.php" method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrfToken,ENT_QUOTES,'UTF-8')?>">
                                        <input type="hidden" name="acao" value="alternar_status">
                                        <input type="hidden" name="colaborador_id" value="<?=(int)$c['id']?>">
                                        <button class="btn btn-outline-secondary btn-sm" type="submit"><?=(int)$c['ativo']===1?'Desativar':'Ativar'?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>
<?php require __DIR__.'/partials/footer.php'; ?>
