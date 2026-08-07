<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
exigirLoginAdmin();

garantirTabelaCategoria();
garantirTabelaProduto();
garantirTabelaVariacaoProduto();

global $pdo;
$totalProdutosAtivos = (int) $pdo->query("SELECT COUNT(*) FROM Produto WHERE Ativo = 1")->fetchColumn();
$totalCategorias = (int) $pdo->query("SELECT COUNT(*) FROM Categoria")->fetchColumn();
$totalSemEstoque = (int) $pdo->query("SELECT COUNT(*) FROM VariacaoProduto WHERE Estoque = 0")->fetchColumn();

require __DIR__ . '/_topo.php';
?>
<h1 class="h4 mb-4">Olá, <?= htmlspecialchars($_SESSION['admin_nome'] ?? '') ?></h1>
<div class="row g-3">
    <div class="col-md-4">
        <div class="card p-4">
            <div class="text-secundario small">Produtos ativos</div>
            <div class="h2 mb-0"><?= $totalProdutosAtivos ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-4">
            <div class="text-secundario small">Categorias</div>
            <div class="h2 mb-0"><?= $totalCategorias ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-4">
            <div class="text-secundario small">Variações sem estoque</div>
            <div class="h2 mb-0 <?= $totalSemEstoque > 0 ? 'text-warning' : '' ?>"><?= $totalSemEstoque ?></div>
        </div>
    </div>
</div>
<div class="mt-4 d-flex gap-2 flex-wrap">
    <a href="<?= URL_BASE ?>/admin/produto/index.php" class="btn btn-marca rounded-pill"><i class="bi bi-box-seam"></i> Gerenciar produtos</a>
    <a href="<?= URL_BASE ?>/admin/categoria.php" class="btn btn-outline-secondary rounded-pill"><i class="bi bi-tags"></i> Gerenciar categorias</a>
    <a href="<?= URL_BASE ?>/admin/configuracoes.php" class="btn btn-outline-secondary rounded-pill"><i class="bi bi-palette"></i> Configurações da loja</a>
</div>
<?php require __DIR__ . '/_rodape.php'; ?>
