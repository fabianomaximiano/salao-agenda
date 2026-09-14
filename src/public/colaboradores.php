<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

exigirAdministrador();

$empresaId = (int) $_SESSION['empresa_id'];
$pdo = getDB();

if (empty($_SESSION['csrf_colaboradores'])) {
    $_SESSION['csrf_colaboradores'] = bin2hex(random_bytes(32));
}

$csrfToken = (string) $_SESSION['csrf_colaboradores'];
$flash = $_SESSION['flash_lista_colaboradores'] ?? null;
unset($_SESSION['flash_lista_colaboradores']);

$stmt = $pdo->prepare(
    'SELECT c.id, c.cargo, c.ativo, c.usuario_id,
            c.pode_agenda, c.pode_clientes, c.pode_profissionais, c.pode_servicos,
            c.pode_financeiro, c.pode_relatorios, c.pode_configuracoes,
            p.nome_completo, p.email,
            tp.numero AS telefone, COALESCE(tp.whatsapp, 0) AS whatsapp,
            u.ativo AS usuario_ativo, u.senha_hash
       FROM colaboradores c
       INNER JOIN pessoas p
               ON p.id = c.pessoa_id
              AND p.empresa_id = c.empresa_id
       LEFT JOIN telefones_pessoa tp
              ON tp.id = (
                    SELECT tp2.id
                      FROM telefones_pessoa tp2
                     WHERE tp2.pessoa_id = p.id
                     ORDER BY tp2.principal DESC, tp2.id ASC
                     LIMIT 1
              )
       LEFT JOIN usuarios u ON u.id = c.usuario_id
      WHERE c.empresa_id = :empresa_id
      ORDER BY c.ativo DESC, p.nome_completo ASC'
);
$stmt->execute([':empresa_id' => $empresaId]);
$colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);

$permissoesRotulos = [
    'agenda' => 'Agenda',
    'clientes' => 'Clientes',
    'profissionais' => 'Profissionais',
    'servicos' => 'Serviços',
    'financeiro' => 'Financeiro',
    'relatorios' => 'Relatórios',
    'configuracoes' => 'Configurações',
];

$pageTitle = 'Colaboradores';
$pageCss = 'colaboradores.css?v=20260914-1';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/partials/navbar.php';
?>
<main class="app-content colaboradores-page">
    <div class="app-page-header colaboradores-header d-md-flex justify-content-between align-items-center">
        <div>
            <h1>Colaboradores</h1>
            <p>Gerencie a equipe administrativa, o acesso ao sistema e suas permissões.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="cadastro-colaborador.php" class="btn btn-primary">Cadastrar colaborador</a>
        </div>
    </div>

    <?php if (is_array($flash)): ?>
        <div class="alert alert-<?= ($flash['tipo'] ?? '') === 'success' ? 'success' : 'danger' ?>">
            <?= htmlspecialchars((string) ($flash['mensagem'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!$colaboradores): ?>
        <div class="app-card">
            <div class="app-empty-state">
                <h3>Nenhum colaborador cadastrado</h3>
                <p>Cadastre recepcionistas, gerentes ou outros membros da equipe administrativa.</p>
                <a href="cadastro-colaborador.php" class="btn btn-primary">Cadastrar primeiro colaborador</a>
            </div>
        </div>
    <?php else: ?>
        <div class="row colaboradores-grid">
            <?php foreach ($colaboradores as $c): ?>
                <?php
                $permissoes = [];
                foreach ($permissoesRotulos as $chave => $rotulo) {
                    if ((int) $c['pode_' . $chave] === 1) {
                        $permissoes[] = $rotulo;
                    }
                }

                $temConta = (int) ($c['usuario_id'] ?? 0) > 0;
                $acessoAtivo = $temConta
                    && (int) ($c['usuario_ativo'] ?? 0) === 1
                    && !empty($c['senha_hash']);

                if ($acessoAtivo) {
                    $statusAcesso = 'Acesso ativo';
                    $classeAcesso = 'success';
                } elseif ($temConta) {
                    $statusAcesso = 'Aguardando ativação';
                    $classeAcesso = 'warning';
                } else {
                    $statusAcesso = 'Sem acesso';
                    $classeAcesso = 'secondary';
                }
                ?>
                <div class="col-12 col-md-6 col-xl-4 mb-4">
                    <article class="colaborador-card">
                        <div class="colaborador-card-top">
                            <div>
                                <h2><?= htmlspecialchars((string) $c['nome_completo'], ENT_QUOTES, 'UTF-8') ?></h2>
                                <p class="colaborador-cargo">
                                    <?= htmlspecialchars((string) ($c['cargo'] ?: 'Função não informada'), ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </div>
                            <span class="badge badge-<?= (int) $c['ativo'] === 1 ? 'success' : 'secondary' ?>">
                                <?= (int) $c['ativo'] === 1 ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </div>

                        <div class="colaborador-contato">
                            <div>
                                <span>E-mail</span>
                                <strong><?= htmlspecialchars((string) ($c['email'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></strong>
                            </div>
                            <div>
                                <span>Telefone</span>
                                <strong>
                                    <?= htmlspecialchars((string) ($c['telefone'] ?: '—'), ENT_QUOTES, 'UTF-8') ?>
                                    <?= (int) ($c['whatsapp'] ?? 0) === 1 ? ' · WhatsApp' : '' ?>
                                </strong>
                            </div>
                        </div>

                        <div class="colaborador-acesso">
                            <span class="badge badge-<?= $classeAcesso ?>">
                                <?= htmlspecialchars($statusAcesso, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>

                        <div class="colaborador-permissoes">
                            <span class="colaborador-label">Permissões</span>
                            <div class="colaborador-permissoes-lista">
                                <?php if ($permissoes): ?>
                                    <?php foreach ($permissoes as $permissao): ?>
                                        <span><?= htmlspecialchars($permissao, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="sem-permissao">Nenhuma permissão</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="colaborador-card-actions">
                            <a href="cadastro-colaborador.php?editar=<?= (int) $c['id'] ?>" class="btn btn-outline-primary btn-sm">
                                Editar
                            </a>

                            <form action="api/colaboradores.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="acao" value="alternar_status">
                                <input type="hidden" name="colaborador_id" value="<?= (int) $c['id'] ?>">
                                <button class="btn btn-outline-secondary btn-sm" type="submit">
                                    <?= (int) $c['ativo'] === 1 ? 'Desativar' : 'Ativar' ?>
                                </button>
                            </form>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
