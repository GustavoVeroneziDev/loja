<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
require_once __DIR__ . '/../config/marca.php';
exigirLoginAdmin();

garantirTabelaCategoria();
garantirTabelaProduto();
garantirTabelaVariacaoProduto();
garantirTabelaPedido();

global $pdo;
$totalProdutosAtivos = (int) $pdo->query("SELECT COUNT(*) FROM Produto WHERE Ativo = 1")->fetchColumn();
$totalCategorias = (int) $pdo->query("SELECT COUNT(*) FROM Categoria")->fetchColumn();
$totalRascunho = (int) $pdo->query("SELECT COUNT(*) FROM Produto WHERE Ativo = 0")->fetchColumn();
$totalSemEstoque = (int) $pdo->query("SELECT COUNT(DISTINCT FKProduto) FROM VariacaoProduto WHERE Estoque = 0")->fetchColumn();
$totalEstoqueBaixo = (int) $pdo->query("SELECT COUNT(*) FROM VariacaoProduto WHERE Estoque > 0 AND Estoque <= COALESCE(EstoqueMinimo, " . (int) ESTOQUE_MINIMO_PADRAO . ")")->fetchColumn();
$totalPedidosAguardando = (int) $pdo->query("SELECT COUNT(*) FROM Pedido WHERE Status = 'aguardando_pagamento' AND Simulacao = 0")->fetchColumn();

require __DIR__ . '/_topo.php';
?>
<h1 class="h4 mb-4">Olá, <?= htmlspecialchars($_SESSION['usuario_nome'] ?? '') ?></h1>

<h2 class="h6 text-secundario text-uppercase mb-3">Visão geral</h2>
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-lg-3">
        <a href="<?= URL_BASE ?>/admin/pedido/index.php?filtro=aguardando_pagamento" class="card p-4 h-100 d-block text-reset text-decoration-none stat-card-admin">
            <div class="d-flex align-items-center gap-3">
                <div class="icone-stat-admin icone-stat-atencao"><i class="bi bi-bag-check-fill"></i></div>
                <div class="flex-truncate">
                    <div class="text-secundario small">Pedidos aguardando pagamento</div>
                    <div class="h3 mb-0"><?= $totalPedidosAguardando ?></div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <a href="<?= URL_BASE ?>/admin/produto/index.php" class="card p-4 h-100 d-block text-reset text-decoration-none stat-card-admin">
            <div class="d-flex align-items-center gap-3">
                <div class="icone-stat-admin icone-stat-sucesso"><i class="bi bi-box-seam-fill"></i></div>
                <div class="flex-truncate">
                    <div class="text-secundario small">Produtos ativos</div>
                    <div class="h3 mb-0"><?= $totalProdutosAtivos ?></div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <a href="<?= URL_BASE ?>/admin/categoria.php" class="card p-4 h-100 d-block text-reset text-decoration-none stat-card-admin">
            <div class="d-flex align-items-center gap-3">
                <div class="icone-stat-admin icone-stat-admin-cor"><i class="bi bi-tags-fill"></i></div>
                <div class="flex-truncate">
                    <div class="text-secundario small">Categorias</div>
                    <div class="h3 mb-0"><?= $totalCategorias ?></div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <a href="<?= URL_BASE ?>/admin/produto/index.php?filtro=rascunho" class="card p-4 h-100 d-block text-reset text-decoration-none stat-card-admin">
            <div class="d-flex align-items-center gap-3">
                <div class="icone-stat-admin icone-stat-atencao"><i class="bi bi-eye-slash-fill"></i></div>
                <div class="flex-truncate">
                    <div class="text-secundario small">Em rascunho</div>
                    <div class="h3 mb-0"><?= $totalRascunho ?></div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <a href="<?= URL_BASE ?>/admin/produto/index.php?filtro=sem_estoque" class="card p-4 h-100 d-block text-reset text-decoration-none stat-card-admin">
            <div class="d-flex align-items-center gap-3">
                <div class="icone-stat-admin icone-stat-atencao"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div class="flex-truncate">
                    <div class="text-secundario small">Sem estoque</div>
                    <div class="h3 mb-0"><?= $totalSemEstoque ?></div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <a href="<?= URL_BASE ?>/admin/estoque.php" class="card p-4 h-100 d-block text-reset text-decoration-none stat-card-admin">
            <div class="d-flex align-items-center gap-3">
                <div class="icone-stat-admin icone-stat-atencao"><i class="bi bi-fire"></i></div>
                <div class="flex-truncate">
                    <div class="text-secundario small">Estoque baixo</div>
                    <div class="h3 mb-0"><?= $totalEstoqueBaixo ?></div>
                </div>
            </div>
        </a>
    </div>
</div>

<h2 class="h6 text-secundario text-uppercase mb-3">Ações rápidas</h2>
<div class="d-flex gap-2 flex-wrap">
    <a href="<?= URL_BASE ?>/admin/produto/index.php" class="btn btn-marca rounded-pill"><i class="bi bi-box-seam"></i> Gerenciar produtos</a>
    <a href="<?= URL_BASE ?>/admin/pedido/index.php" class="btn btn-outline-secondary rounded-pill"><i class="bi bi-bag-check"></i> Gerenciar pedidos</a>
    <a href="<?= URL_BASE ?>/admin/estoque.php" class="btn btn-outline-secondary rounded-pill"><i class="bi bi-boxes"></i> Gerenciar estoque</a>
    <a href="<?= URL_BASE ?>/admin/categoria.php" class="btn btn-outline-secondary rounded-pill"><i class="bi bi-tags"></i> Gerenciar categorias</a>
</div>
<?php require __DIR__ . '/_rodape.php'; ?>
