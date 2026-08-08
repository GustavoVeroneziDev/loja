<?php
session_start();
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../config/funcoes.php';
exigirLoginAdmin();
garantirTabelaCategoria();
garantirTabelaProduto();
garantirTabelaVariacaoProduto();
garantirTabelaImagemProduto();

global $pdo;

$idProduto = $_GET['id'] ?? '';
$produto = obterProdutoPorId($idProduto);
if (!$produto) {
    header('Location: ' . URL_BASE . '/admin/produto/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $sucesso = null;

    if ($action === 'editar') {
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $fkCategoria = $_POST['fk_categoria'] ?? '';
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($nome !== '') {
            $stmt = $pdo->prepare("UPDATE Produto SET Nome = :nome, Descricao = :descricao, FKCategoria = :categoria, Ativo = :ativo WHERE IDProduto = :id");
            $stmt->execute([
                'nome' => $nome,
                'descricao' => $descricao,
                'categoria' => $fkCategoria !== '' ? $fkCategoria : null,
                'ativo' => $ativo,
                'id' => $idProduto,
            ]);
            $sucesso = true;
        }
    }

    if ($action === 'criar_variacao') {
        $atributo = trim($_POST['atributo'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $preco = (float) str_replace(',', '.', $_POST['preco'] ?? '0');
        $estoque = max(0, (int) ($_POST['estoque'] ?? 0));

        if ($sku !== '') {
            $stmt = $pdo->prepare("INSERT INTO VariacaoProduto (IDVariacao, FKProduto, Atributo, SKU, Preco, Estoque) VALUES (:id, :produto, :atributo, :sku, :preco, :estoque)");
            $stmt->execute([
                'id' => gerarUuid(),
                'produto' => $idProduto,
                'atributo' => $atributo !== '' ? $atributo : null,
                'sku' => $sku,
                'preco' => $preco,
                'estoque' => $estoque,
            ]);
            $sucesso = true;
        }
    }

    if ($action === 'editar_variacao') {
        $idVariacao = $_POST['id_variacao'] ?? '';
        $atributo = trim($_POST['atributo'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $preco = (float) str_replace(',', '.', $_POST['preco'] ?? '0');
        $estoque = max(0, (int) ($_POST['estoque'] ?? 0));

        if ($idVariacao !== '' && $sku !== '') {
            $stmt = $pdo->prepare("UPDATE VariacaoProduto SET Atributo = :atributo, SKU = :sku, Preco = :preco, Estoque = :estoque WHERE IDVariacao = :id AND FKProduto = :produto");
            $stmt->execute([
                'atributo' => $atributo !== '' ? $atributo : null,
                'sku' => $sku,
                'preco' => $preco,
                'estoque' => $estoque,
                'id' => $idVariacao,
                'produto' => $idProduto,
            ]);
            $sucesso = true;
        }
    }

    if ($action === 'excluir_variacao') {
        $idVariacao = $_POST['id_variacao'] ?? '';

        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM VariacaoProduto WHERE FKProduto = :produto");
        $stmtCount->execute(['produto' => $idProduto]);
        $totalVariacoes = (int) $stmtCount->fetchColumn();

        // Nunca deixa o produto sem nenhuma variação — storefront sempre lê preço/estoque por ali.
        if ($idVariacao !== '' && $totalVariacoes > 1) {
            $stmt = $pdo->prepare("DELETE FROM VariacaoProduto WHERE IDVariacao = :id AND FKProduto = :produto");
            $stmt->execute(['id' => $idVariacao, 'produto' => $idProduto]);
            $sucesso = true;
        } else {
            $sucesso = false;
        }
    }

    if ($action === 'adicionar_imagem') {
        if (!empty($_FILES['imagem']['tmp_name'])) {
            $url = uploadImagem($_FILES['imagem'], 'produtos');
            if ($url) {
                $stmtOrdem = $pdo->prepare("SELECT COALESCE(MAX(Ordem), -1) + 1 FROM ImagemProduto WHERE FKProduto = :produto");
                $stmtOrdem->execute(['produto' => $idProduto]);
                $proximaOrdem = (int) $stmtOrdem->fetchColumn();

                $stmt = $pdo->prepare("INSERT INTO ImagemProduto (IDImagem, FKProduto, Url, Ordem) VALUES (:id, :produto, :url, :ordem)");
                $stmt->execute([
                    'id' => gerarUuid(),
                    'produto' => $idProduto,
                    'url' => $url,
                    'ordem' => $proximaOrdem,
                ]);
                $sucesso = true;
            } else {
                $sucesso = false;
            }
        }
    }

    if ($action === 'excluir_imagem') {
        $idImagem = $_POST['id_imagem'] ?? '';
        if ($idImagem !== '') {
            $stmt = $pdo->prepare("DELETE FROM ImagemProduto WHERE IDImagem = :id AND FKProduto = :produto");
            $stmt->execute(['id' => $idImagem, 'produto' => $idProduto]);
            $sucesso = true;
        }
    }

    header('Location: ' . URL_BASE . '/admin/produto/editar.php?id=' . urlencode($idProduto) . ($sucesso ? '&ok=1' : '&erro=1'));
    exit;
}

$sucesso = isset($_GET['ok']) ? 'Operação realizada com sucesso.' : null;
$erro = isset($_GET['erro']) ? 'Não foi possível concluir a ação (o produto precisa de ao menos 1 variação e 1 imagem válida).' : null;

$categorias = obterCategoriasArvore();
$variacoes = obterVariacoesPorProduto($idProduto);
$imagens = obterImagensPorProduto($idProduto);

require __DIR__ . '/../_topo.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Editar produto</h1>
    <a href="<?= URL_BASE ?>/admin/produto/index.php" class="btn btn-outline-secondary rounded-pill btn-sm">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<?php if ($sucesso): ?><div class="alert alert-success">Operação realizada com sucesso.</div><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-danger"><?= $erro ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card p-4">
            <h2 class="h5 mb-3">Dados básicos</h2>
            <form method="post">
                <input type="hidden" name="action" value="editar">
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($produto['Nome']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Descrição</label>
                    <textarea name="descricao" class="form-control" rows="4"><?= htmlspecialchars($produto['Descricao'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Categoria</label>
                    <select name="fk_categoria" class="form-select">
                        <option value="">Sem categoria</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?= htmlspecialchars($categoria['IDCategoria']) ?>" <?= $categoria['IDCategoria'] === $produto['FKCategoria'] ? 'selected' : '' ?>>
                                <?= str_repeat('— ', $categoria['Nivel']) ?><?= htmlspecialchars($categoria['Nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-check form-switch mb-3">
                    <input type="checkbox" name="ativo" class="form-check-input" id="ativoSwitch" <?= $produto['Ativo'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="ativoSwitch">Visível na loja</label>
                </div>
                <button type="submit" class="btn btn-marca rounded-pill">Salvar</button>
            </form>
        </div>

        <div class="card p-4 mt-4">
            <h2 class="h5 mb-3">Imagens</h2>
            <div class="row g-2 mb-3">
                <?php foreach ($imagens as $imagem): ?>
                    <div class="col-4">
                        <div class="position-relative">
                            <img src="<?= htmlspecialchars(urlAsset($imagem['Url'])) ?>" class="img-fluid rounded" alt="">
                            <form method="post" data-confirmar="Excluir esta imagem?" class="position-absolute top-0 end-0 m-1">
                                <input type="hidden" name="action" value="excluir_imagem">
                                <input type="hidden" name="id_imagem" value="<?= htmlspecialchars($imagem['IDImagem']) ?>">
                                <button type="submit" class="btn btn-sm btn-perigo rounded-pill"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$imagens): ?>
                    <p class="text-secundario mb-0">Nenhuma imagem ainda.</p>
                <?php endif; ?>
            </div>
            <form method="post" enctype="multipart/form-data" class="d-flex gap-2">
                <input type="hidden" name="action" value="adicionar_imagem">
                <input type="file" name="imagem" class="form-control" accept=".jpg,.jpeg,.png,.webp" required>
                <button type="submit" class="btn btn-marca rounded-pill text-nowrap">Enviar</button>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Variações</h2>
                <button class="btn btn-sm btn-marca rounded-pill" data-bs-toggle="modal" data-bs-target="#modalCriarVariacao">
                    <i class="bi bi-plus-lg"></i> Nova variação
                </button>
            </div>
            <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Atributo</th>
                        <th>SKU</th>
                        <th>Preço</th>
                        <th>Estoque</th>
                        <th style="width: 110px; min-width: 110px; max-width: 110px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($variacoes as $variacao): ?>
                        <tr>
                            <td><?= htmlspecialchars($variacao['Atributo'] ?? 'Padrão') ?></td>
                            <td class="text-secundario"><?= htmlspecialchars($variacao['SKU']) ?></td>
                            <td><?= formatarPreco($variacao['Preco']) ?></td>
                            <td><?= $variacao['Estoque'] == 0 ? '<span class="badge-perigo px-2 py-1">0</span>' : (int) $variacao['Estoque'] ?></td>
                            <td style="width: 110px; min-width: 110px; max-width: 110px;">
                                <button class="btn btn-sm btn-outline-secondary rounded-pill" data-bs-toggle="modal" data-bs-target="#modalEditarVariacao<?= $variacao['IDVariacao'] ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="post" class="d-inline" data-confirmar="Excluir esta variação?">
                                    <input type="hidden" name="action" value="excluir_variacao">
                                    <input type="hidden" name="id_variacao" value="<?= htmlspecialchars($variacao['IDVariacao']) ?>">
                                    <button type="submit" class="btn btn-sm btn-perigo rounded-pill"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php foreach ($variacoes as $variacao): ?>
    <div class="modal fade" id="modalEditarVariacao<?= $variacao['IDVariacao'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar variação</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="editar_variacao">
                        <input type="hidden" name="id_variacao" value="<?= htmlspecialchars($variacao['IDVariacao']) ?>">
                        <div class="mb-3">
                            <label class="form-label">Atributo (ex: Tamanho M / Cor Azul)</label>
                            <input type="text" name="atributo" class="form-control" value="<?= htmlspecialchars($variacao['Atributo'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">SKU</label>
                            <input type="text" name="sku" class="form-control" value="<?= htmlspecialchars($variacao['SKU']) ?>" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label">Preço</label>
                                <input type="text" name="preco" class="form-control" value="<?= htmlspecialchars($variacao['Preco']) ?>" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Estoque</label>
                                <input type="number" name="estoque" class="form-control" min="0" value="<?= (int) $variacao['Estoque'] ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-marca rounded-pill">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<div class="modal fade" id="modalCriarVariacao" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Nova variação</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="criar_variacao">
                    <div class="mb-3">
                        <label class="form-label">Atributo (ex: Tamanho M / Cor Azul)</label>
                        <input type="text" name="atributo" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">SKU</label>
                        <input type="text" name="sku" class="form-control" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label">Preço</label>
                            <input type="text" name="preco" class="form-control" placeholder="0,00" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Estoque</label>
                            <input type="number" name="estoque" class="form-control" min="0" value="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-marca rounded-pill">Criar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../_rodape.php'; ?>
