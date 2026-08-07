<?php
require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/config/funcoes.php';
garantirTabelaCategoria();
garantirTabelaProduto();
garantirTabelaVariacaoProduto();
garantirTabelaImagemProduto();

$idCategoria = $_GET['id'] ?? '';
global $pdo;
$stmt = $pdo->prepare("SELECT * FROM Categoria WHERE IDCategoria = :id");
$stmt->execute(['id' => $idCategoria]);
$categoria = $stmt->fetch();

if (!$categoria) {
    header('Location: ' . URL_BASE . '/index.php');
    exit;
}

$produtos = obterProdutosAtivos($idCategoria);

require __DIR__ . '/geral/header.php';
?>
<h1 class="h4 mb-4"><?= htmlspecialchars($categoria['Nome']) ?></h1>
<div class="row g-4">
    <?php foreach ($produtos as $produto): ?>
        <div class="col-6 col-md-4 col-lg-3">
            <a href="<?= URL_BASE ?>/produto.php?id=<?= urlencode($produto['IDProduto']) ?>" class="text-decoration-none text-reset">
                <div class="card h-100">
                    <img src="<?= htmlspecialchars(urlAsset($produto['ImagemCapa'] ?? '/geral/img/logo-placeholder.svg')) ?>"
                         class="card-img-top" style="aspect-ratio: 1; object-fit: cover; border-radius: 12px 12px 0 0;"
                         alt="<?= htmlspecialchars($produto['Nome']) ?>">
                    <div class="card-body">
                        <h2 class="h6 mb-1"><?= htmlspecialchars($produto['Nome']) ?></h2>
                        <p class="text-marca fw-semibold mb-0">
                            <?= $produto['PrecoMinimo'] !== null ? formatarPreco($produto['PrecoMinimo']) : 'Consulte' ?>
                        </p>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
    <?php if (!$produtos): ?>
        <p class="text-secundario">Nenhum produto nesta categoria ainda.</p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/geral/footer.php'; ?>
