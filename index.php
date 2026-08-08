<?php
require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/config/funcoes.php';
garantirTabelaCategoria();
garantirTabelaProduto();
garantirTabelaVariacaoProduto();
garantirTabelaImagemProduto();
garantirTabelaFavorito();

$busca = trim($_GET['busca'] ?? '');
$produtos = obterProdutosAtivos(null, $busca !== '' ? $busca : null);
$voltarParaCategoria = URL_BASE . '/index.php';

require __DIR__ . '/geral/header.php';
?>
<h1 class="h3 mb-4 titulo-estilizado">Produtos</h1>

<form method="get" action="<?= URL_BASE ?>/index.php" class="mb-4">
    <div class="input-group barra-pesquisa">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="search" name="busca" class="form-control" placeholder="Buscar produtos..." value="<?= htmlspecialchars($busca) ?>">
        <?php if ($busca !== ''): ?>
            <a href="<?= URL_BASE ?>/index.php" class="btn btn-outline-secondary" aria-label="Limpar busca"><i class="bi bi-x-lg"></i></a>
        <?php endif; ?>
        <button type="submit" class="btn btn-marca" aria-label="Buscar">
            <i class="bi bi-search"></i><span class="d-none d-sm-inline"> Buscar</span>
        </button>
    </div>
</form>

<?php if ($busca !== ''): ?>
    <p class="text-secundario mb-3">
        <?= count($produtos) ?> <?= count($produtos) === 1 ? 'resultado' : 'resultados' ?> pra "<?= htmlspecialchars($busca) ?>"
    </p>
<?php endif; ?>

<?php require __DIR__ . '/geral/_grade-produtos.php'; ?>
<?php if (!$produtos): ?>
    <p class="text-secundario">
        <?= $busca !== '' ? 'Nenhum produto encontrado pra "' . htmlspecialchars($busca) . '".' : 'Nenhum produto disponível ainda.' ?>
    </p>
<?php endif; ?>
<?php require __DIR__ . '/geral/footer.php'; ?>
