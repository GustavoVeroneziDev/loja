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

<div class="row g-4">
    <?php foreach ($produtos as $produto): $favoritado = ehFavorito($produto['IDProduto']); ?>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="position-relative">
                <?php if ($produto['NomeCategoria']): ?>
                    <a href="<?= URL_BASE ?>/categoria.php?id=<?= urlencode($produto['FKCategoria']) ?>" class="badge-categoria-produto position-absolute top-0 start-0 m-2" style="z-index: 2;">
                        <span><?= htmlspecialchars($produto['NomeCategoria']) ?></span>
                    </a>
                <?php endif; ?>
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
                                    <input type="hidden" name="voltar_para" value="<?= htmlspecialchars(URL_BASE . '/index.php') ?>">
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
    <?php if (!$produtos): ?>
        <p class="text-secundario">
            <?= $busca !== '' ? 'Nenhum produto encontrado pra "' . htmlspecialchars($busca) . '".' : 'Nenhum produto disponível ainda.' ?>
        </p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/geral/footer.php'; ?>
