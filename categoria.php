<?php
require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/config/funcoes.php';
garantirTabelaCategoria();
garantirTabelaProduto();
garantirTabelaVariacaoProduto();
garantirTabelaImagemProduto();
garantirTabelaFavorito();

$idCategoria = $_GET['id'] ?? '';
global $pdo;
$stmt = $pdo->prepare("SELECT * FROM Categoria WHERE IDCategoria = :id");
$stmt->execute(['id' => $idCategoria]);
$categoria = $stmt->fetch();

if (!$categoria) {
    header('Location: ' . URL_BASE . '/index.php');
    exit;
}

// Categoria com subcategoria soma o produto das filhas junto (não só o que tá exatamente
// nela) — separado em seção com subtítulo por subcategoria, pra ficar organizado em vez de
// tudo misturado. Sem subcategoria nenhuma, cai no grid simples de sempre.
$agrupado = obterProdutosAgrupadosPorCategoria($idCategoria);
$totalProdutos = count($agrupado['diretos']) + array_sum(array_map(fn($g) => count($g['produtos']), $agrupado['grupos']));
$voltarParaCategoria = URL_BASE . '/categoria.php?id=' . urlencode($idCategoria);

require __DIR__ . '/geral/header.php';
?>
<h1 class="h3 mb-4 titulo-estilizado"><?= htmlspecialchars($categoria['Nome']) ?></h1>

<?php if (!$agrupado['grupos']): ?>
    <?php $produtos = $agrupado['diretos']; ?>
    <?php require __DIR__ . '/geral/_grade-produtos.php'; ?>
<?php else: ?>
    <?php $primeiraSecao = true; ?>
    <?php if ($agrupado['diretos']): ?>
        <?php $produtos = $agrupado['diretos']; $primeiraSecao = false; ?>
        <?php require __DIR__ . '/geral/_grade-produtos.php'; ?>
    <?php endif; ?>
    <?php foreach ($agrupado['grupos'] as $grupo): ?>
        <h2 class="h5 mb-3 titulo-estilizado <?= $primeiraSecao ? '' : 'mt-5' ?>"><?= htmlspecialchars($grupo['categoria']['Nome']) ?></h2>
        <?php $produtos = $grupo['produtos']; $primeiraSecao = false; ?>
        <?php require __DIR__ . '/geral/_grade-produtos.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($totalProdutos === 0): ?>
    <p class="text-secundario">Nenhum produto nesta categoria ainda.</p>
<?php endif; ?>
<?php require __DIR__ . '/geral/footer.php'; ?>
