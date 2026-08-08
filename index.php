<?php
require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/config/funcoes.php';
garantirTabelaCategoria();
garantirTabelaProduto();
garantirTabelaVariacaoProduto();
garantirTabelaImagemProduto();
garantirTabelaFavorito();

$produtos = obterProdutosAtivos();

require __DIR__ . '/geral/header.php';
?>
<h1 class="h3 mb-4 titulo-estilizado">Produtos</h1>
<div class="row g-4">
    <?php foreach ($produtos as $produto): $favoritado = ehFavorito($produto['IDProduto']); ?>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="position-relative">
                <?php if (clienteLogado()): ?>
                    <form method="post" action="<?= URL_BASE ?>/usuario/favoritos.php" class="position-absolute top-0 end-0 m-2" style="z-index: 2;">
                        <input type="hidden" name="action" value="alternar">
                        <input type="hidden" name="produto_id" value="<?= htmlspecialchars($produto['IDProduto']) ?>">
                        <input type="hidden" name="voltar_para" value="<?= htmlspecialchars(URL_BASE . '/index.php') ?>">
                        <button type="submit" class="btn-favorito <?= $favoritado ? 'ativo' : '' ?>" aria-label="<?= $favoritado ? 'Remover dos favoritos' : 'Favoritar' ?>">
                            <i class="bi <?= $favoritado ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                        </button>
                    </form>
                <?php else: ?>
                    <a href="<?= URL_BASE ?>/usuario/login.php" class="btn-favorito position-absolute top-0 end-0 m-2" style="z-index: 2;" aria-label="Entrar pra favoritar">
                        <i class="bi bi-heart"></i>
                    </a>
                <?php endif; ?>
                <a href="<?= URL_BASE ?>/produto.php?id=<?= urlencode($produto['IDProduto']) ?>" class="text-decoration-none text-reset">
                    <div class="card h-100">
                        <img src="<?= htmlspecialchars(urlAsset($produto['ImagemCapa'] ?? '/geral/img/uscountry-logosolo.svg')) ?>"
                             class="card-img-top" style="aspect-ratio: 1; object-fit: cover; border-radius: 16px 16px 0 0;"
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
        </div>
    <?php endforeach; ?>
    <?php if (!$produtos): ?>
        <p class="text-secundario">Nenhum produto disponível ainda.</p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/geral/footer.php'; ?>
