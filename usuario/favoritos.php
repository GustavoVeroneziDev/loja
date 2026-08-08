<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
garantirTabelaUsuario();
garantirTabelaProduto();
garantirTabelaVariacaoProduto();
garantirTabelaImagemProduto();
garantirTabelaFavorito();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirLoginCliente();
    if (($_POST['action'] ?? '') === 'alternar') {
        alternarFavorito($_POST['produto_id'] ?? '');
    }
    $voltarPara = $_POST['voltar_para'] ?? (URL_BASE . '/usuario/favoritos.php');
    header('Location: ' . $voltarPara);
    exit;
}

exigirLoginCliente();
$favoritos = obterFavoritos();

require __DIR__ . '/../geral/header.php';
?>
<h1 class="h3 mb-4 titulo-estilizado">Meus favoritos</h1>

<?php if (!$favoritos): ?>
    <div class="card p-5 text-center text-secundario">
        <p class="mb-3">Você ainda não favoritou nada.</p>
        <a href="<?= URL_BASE ?>/index.php" class="btn btn-marca rounded-pill">Ver produtos</a>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($favoritos as $produto): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="position-relative">
                    <?php if ($produto['NomeCategoria']): ?>
                        <a href="<?= URL_BASE ?>/categoria.php?id=<?= urlencode($produto['FKCategoria']) ?>" class="badge-categoria-produto position-absolute top-0 start-0 m-2" style="z-index: 2;">
                            <span><?= htmlspecialchars($produto['NomeCategoria']) ?></span>
                        </a>
                    <?php endif; ?>
                    <form method="post" action="<?= URL_BASE ?>/usuario/favoritos.php" class="position-absolute top-0 end-0 m-2" style="z-index: 2;">
                        <input type="hidden" name="action" value="alternar">
                        <input type="hidden" name="produto_id" value="<?= htmlspecialchars($produto['IDProduto']) ?>">
                        <input type="hidden" name="voltar_para" value="<?= htmlspecialchars(URL_BASE . '/usuario/favoritos.php') ?>">
                        <button type="submit" class="btn-favorito ativo" aria-label="Remover dos favoritos">
                            <i class="bi bi-heart-fill"></i>
                        </button>
                    </form>
                    <div class="card h-100">
                        <a href="<?= URL_BASE ?>/produto.php?id=<?= urlencode($produto['IDProduto']) ?>" class="text-decoration-none text-reset">
                            <img src="<?= htmlspecialchars(urlAsset($produto['ImagemCapa'] ?? '/geral/img/uscountry-logosolo.svg')) ?>"
                                 class="card-img-top" style="aspect-ratio: 1; object-fit: cover; border-radius: 16px 16px 0 0;"
                                 alt="<?= htmlspecialchars($produto['Nome']) ?>">
                            <div class="card-body pb-1">
                                <h2 class="h6 mb-1"><?= htmlspecialchars($produto['Nome']) ?></h2>
                                <p class="text-marca fw-semibold mb-0">
                                    <?= $produto['PrecoMinimo'] !== null ? formatarPreco($produto['PrecoMinimo']) : 'Consulte' ?>
                                </p>
                            </div>
                        </a>
                        <div class="card-body pt-0">
                            <?php if ((int) ($produto['TotalVariacoes'] ?? 0) === 1): $semEstoque = (int) ($produto['EstoqueVariacaoUnica'] ?? 0) <= 0; ?>
                                <div class="botoes-acao-produto">
                                    <form method="post" action="<?= URL_BASE ?>/carrinho.php">
                                        <input type="hidden" name="action" value="adicionar">
                                        <input type="hidden" name="variacao_id" value="<?= htmlspecialchars($produto['IDVariacaoUnica']) ?>">
                                        <input type="hidden" name="quantidade" value="1">
                                        <input type="hidden" name="voltar_para" value="<?= htmlspecialchars(URL_BASE . '/usuario/favoritos.php') ?>">
                                        <button type="submit" class="btn-acao-produto" <?= $semEstoque ? 'disabled' : '' ?> aria-label="Adicionar ao carrinho">
                                            <i class="bi bi-cart-plus"></i><span class="d-none d-sm-inline">Carrinho</span>
                                        </button>
                                    </form>
                                    <form method="post" action="<?= URL_BASE ?>/carrinho.php">
                                        <input type="hidden" name="action" value="adicionar">
                                        <input type="hidden" name="variacao_id" value="<?= htmlspecialchars($produto['IDVariacaoUnica']) ?>">
                                        <input type="hidden" name="quantidade" value="1">
                                        <button type="submit" class="btn-acao-produto acao-comprar" <?= $semEstoque ? 'disabled' : '' ?> aria-label="Comprar agora">
                                            <i class="bi bi-bag-check"></i><span class="d-none d-sm-inline">Comprar</span>
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <a href="<?= URL_BASE ?>/produto.php?id=<?= urlencode($produto['IDProduto']) ?>" class="botoes-acao-produto-simples">
                                    <i class="bi bi-eye"></i> Ver opções
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../geral/footer.php'; ?>
