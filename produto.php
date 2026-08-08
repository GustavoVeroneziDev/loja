<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/config/funcoes.php';
garantirTabelaProduto();
garantirTabelaVariacaoProduto();
garantirTabelaImagemProduto();
garantirTabelaFavorito();

$idProduto = $_GET['id'] ?? '';
$produto = obterProdutoPorId($idProduto);

// Admin pode ver o produto mesmo em rascunho via link de pré-visualização (?preview=1) — precisa
// ser um link explícito, não "todo admin logado vê tudo", senão a checagem de Ativo não serve pra nada.
$modoPreview = adminLogado() && isset($_GET['preview']);

if (!$produto || (!$produto['Ativo'] && !$modoPreview)) {
    header('Location: ' . URL_BASE . '/index.php');
    exit;
}

$variacoes = obterVariacoesPorProduto($idProduto);
$imagens = obterImagensPorProduto($idProduto);
$favoritado = ehFavorito($idProduto);

require __DIR__ . '/geral/header.php';
?>
<?php if ($modoPreview && !$produto['Ativo']): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <span><i class="bi bi-eye"></i> Pré-visualização — este produto está em rascunho, ainda não aparece na loja pros clientes.</span>
        <a href="<?= URL_BASE ?>/admin/produto/editar.php?id=<?= urlencode($idProduto) ?>" class="btn btn-sm btn-outline-secondary rounded-pill">
            <i class="bi bi-arrow-left"></i> Voltar pro admin
        </a>
    </div>
<?php endif; ?>
<div class="row g-4">
    <div class="col-md-6">
        <?php if ($imagens): ?>
            <div id="carrosselProduto" class="carousel slide">
                <div class="carousel-inner rounded">
                    <?php foreach ($imagens as $i => $imagem): ?>
                        <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                            <img src="<?= htmlspecialchars(urlAsset($imagem['Url'])) ?>" class="d-block w-100" style="aspect-ratio: 1; object-fit: cover;" alt="<?= htmlspecialchars($produto['Nome']) ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($imagens) > 1): ?>
                    <button class="carousel-control-prev" type="button" data-bs-target="#carrosselProduto" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#carrosselProduto" data-bs-slide="next">
                        <span class="carousel-control-next-icon"></span>
                    </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <img src="<?= htmlspecialchars(urlAsset('/geral/img/uscountry-logosolo.svg')) ?>" class="w-100 rounded" style="aspect-ratio: 1; object-fit: contain; background: #f5f5f5;" alt="<?= htmlspecialchars($produto['Nome']) ?>">
        <?php endif; ?>
    </div>
    <div class="col-md-6">
        <div class="d-flex justify-content-between align-items-start gap-3">
            <h1 class="h3 mb-0 titulo-estilizado"><?= htmlspecialchars($produto['Nome']) ?></h1>
            <?php if (clienteLogado()): ?>
                <form method="post" action="<?= URL_BASE ?>/usuario/favoritos.php" class="flex-shrink-0">
                    <input type="hidden" name="action" value="alternar">
                    <input type="hidden" name="produto_id" value="<?= htmlspecialchars($produto['IDProduto']) ?>">
                    <input type="hidden" name="voltar_para" value="<?= htmlspecialchars(URL_BASE . '/produto.php?id=' . $produto['IDProduto']) ?>">
                    <button type="submit" class="btn-favorito <?= $favoritado ? 'ativo' : '' ?>" aria-label="<?= $favoritado ? 'Remover dos favoritos' : 'Favoritar' ?>">
                        <i class="bi <?= $favoritado ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                    </button>
                </form>
            <?php else: ?>
                <a href="<?= URL_BASE ?>/usuario/login.php" class="btn-favorito flex-shrink-0" aria-label="Entrar pra favoritar">
                    <i class="bi bi-heart"></i>
                </a>
            <?php endif; ?>
        </div>
        <p class="text-secundario"><?= nl2br(htmlspecialchars($produto['Descricao'] ?? '')) ?></p>

        <form method="post" action="<?= URL_BASE ?>/carrinho.php" id="formAdicionarCarrinho">
            <input type="hidden" name="action" value="adicionar">

            <?php if (count($variacoes) > 1): ?>
                <div class="mb-3">
                    <label class="form-label d-block">Opção</label>
                    <div class="btn-group flex-wrap" role="group">
                        <?php foreach ($variacoes as $i => $variacao): ?>
                            <input type="radio" class="btn-check" name="variacao_id" value="<?= htmlspecialchars($variacao['IDVariacao']) ?>" id="variacao<?= $variacao['IDVariacao'] ?>"
                                   data-preco="<?= $variacao['Preco'] ?>" data-estoque="<?= (int) $variacao['Estoque'] ?>"
                                   <?= $i === 0 ? 'checked' : '' ?> onchange="atualizarVariacaoSelecionada(this)">
                            <label class="btn btn-outline-secondary" for="variacao<?= $variacao['IDVariacao'] ?>">
                                <?= htmlspecialchars($variacao['Atributo'] ?? 'Padrão') ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <input type="hidden" name="variacao_id" value="<?= htmlspecialchars($variacoes[0]['IDVariacao'] ?? '') ?>">
            <?php endif; ?>

            <p class="h3 text-marca" id="precoSelecionado"><?= formatarPreco($variacoes[0]['Preco'] ?? 0) ?></p>
            <p class="text-secundario small" id="estoqueSelecionado">
                <?= ($variacoes[0]['Estoque'] ?? 0) > 0 ? (int) $variacoes[0]['Estoque'] . ' em estoque' : 'Fora de estoque' ?>
            </p>

            <div class="d-flex gap-2 align-items-center mb-3">
                <label for="quantidade" class="form-label mb-0 text-secundario small">Quantidade</label>
                <input type="number" name="quantidade" id="quantidade" class="form-control" style="width: 90px;"
                       value="1" min="1" max="<?= (int) ($variacoes[0]['Estoque'] ?? 0) ?>">
            </div>

            <button type="submit" id="btnAdicionar" class="btn btn-marca rounded-pill btn-lg" <?= ($variacoes[0]['Estoque'] ?? 0) > 0 ? '' : 'disabled' ?>>
                <i class="bi bi-cart-plus"></i> Adicionar ao carrinho
            </button>
        </form>
    </div>
</div>

<script>
function atualizarVariacaoSelecionada(input) {
    const preco = parseFloat(input.dataset.preco).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    const estoque = parseInt(input.dataset.estoque, 10);
    document.getElementById('precoSelecionado').textContent = preco;
    document.getElementById('estoqueSelecionado').textContent = estoque > 0 ? estoque + ' em estoque' : 'Fora de estoque';

    const campoQuantidade = document.getElementById('quantidade');
    campoQuantidade.max = estoque;
    if (parseInt(campoQuantidade.value, 10) > estoque) {
        campoQuantidade.value = estoque > 0 ? 1 : 0;
    }

    document.getElementById('btnAdicionar').disabled = estoque <= 0;
}
</script>
<?php require __DIR__ . '/geral/footer.php'; ?>
