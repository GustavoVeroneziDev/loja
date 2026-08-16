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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'criar') {
        $nome = trim($_POST['nome'] ?? '');
        $fkCategoria = $_POST['fk_categoria'] ?? '';

        if ($nome !== '') {
            $idProduto = gerarUuid();
            $stmt = $pdo->prepare("INSERT INTO Produto (IDProduto, Nome, FKCategoria, Ativo) VALUES (:id, :nome, :categoria, 0)");
            $stmt->execute([
                'id' => $idProduto,
                'nome' => $nome,
                'categoria' => $fkCategoria !== '' ? $fkCategoria : null,
            ]);

            // Toda variação nasce ligada a um produto — mesmo sem variação real, o produto
            // precisa de 1 linha em VariacaoProduto pra ter preço/estoque (padrão implícito).
            $stmt = $pdo->prepare("INSERT INTO VariacaoProduto (IDVariacao, FKProduto, SKU, Preco, Estoque) VALUES (:id, :produto, :sku, 0, 0)");
            $stmt->execute([
                'id' => gerarUuid(),
                'produto' => $idProduto,
                'sku' => 'PROD-' . strtoupper(substr($idProduto, 0, 8)),
            ]);

            header('Location: ' . URL_BASE . '/admin/produto/editar.php?id=' . urlencode($idProduto));
            exit;
        }
    }

    if ($action === 'excluir') {
        $id = $_POST['id'] ?? '';
        if ($id !== '') {
            $stmt = $pdo->prepare("DELETE FROM Produto WHERE IDProduto = :id");
            $stmt->execute(['id' => $id]);
        }
        header('Location: ' . URL_BASE . '/admin/produto/index.php?ok=1');
        exit;
    }
}

$sucesso = isset($_GET['ok']) ? 'Operação realizada com sucesso.' : null;
$filtro = $_GET['filtro'] ?? '';

$sql = "SELECT p.*, c.Nome AS NomeCategoria,
        (SELECT MIN(Preco) FROM VariacaoProduto WHERE FKProduto = p.IDProduto) AS PrecoMinimo,
        (SELECT SUM(Estoque) FROM VariacaoProduto WHERE FKProduto = p.IDProduto) AS EstoqueTotal
    FROM Produto p
    LEFT JOIN Categoria c ON c.IDCategoria = p.FKCategoria";
if ($filtro === 'rascunho') {
    $sql .= " WHERE p.Ativo = 0";
} elseif ($filtro === 'sem_estoque') {
    $sql .= " WHERE EXISTS (SELECT 1 FROM VariacaoProduto v WHERE v.FKProduto = p.IDProduto AND v.Estoque = 0)";
}
$sql .= " ORDER BY p.MomentoCriacao DESC";
$produtos = $pdo->query($sql)->fetchAll();

$categorias = obterCategoriasArvore();

require __DIR__ . '/../_topo.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Produtos</h1>
    <button class="btn btn-marca rounded-pill" data-bs-toggle="modal" data-bs-target="#modalCriarProduto">
        <i class="bi bi-plus-lg"></i> Novo produto
    </button>
</div>

<?php if ($sucesso): ?><script>document.addEventListener('DOMContentLoaded', function () { mostrarToastSucesso(<?= json_encode($sucesso) ?>); });</script><?php endif; ?>

<?php if ($filtro === 'rascunho' || $filtro === 'sem_estoque'): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <span>
            <i class="bi bi-funnel"></i>
            Mostrando só produtos <?= $filtro === 'rascunho' ? 'em rascunho' : 'com alguma variação sem estoque' ?>.
        </span>
        <a href="<?= URL_BASE ?>/admin/produto/index.php" class="btn btn-sm btn-outline-secondary rounded-pill">Limpar filtro</a>
    </div>
<?php endif; ?>

<div class="card">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>Produto</th>
                <th class="d-none d-md-table-cell">Categoria</th>
                <th>Preço a partir de</th>
                <th class="d-none d-md-table-cell">Estoque</th>
                <th class="d-none d-md-table-cell">Status</th>
                <th class="d-none d-md-table-cell" style="width: 180px; min-width: 180px; max-width: 180px;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($produtos as $produto): ?>
                <tr class="linha-expandivel" data-alvo-expandir="detalhesProduto<?= $produto['IDProduto'] ?>">
                    <td><?= htmlspecialchars($produto['Nome']) ?></td>
                    <td class="d-none d-md-table-cell text-secundario"><?= htmlspecialchars($produto['NomeCategoria'] ?? '—') ?></td>
                    <td>
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <span><?= $produto['PrecoMinimo'] !== null ? formatarPreco($produto['PrecoMinimo']) : '—' ?></span>
                            <i class="bi bi-chevron-down icone-expandir d-md-none text-secundario"></i>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell"><?= (int) $produto['EstoqueTotal'] ?></td>
                    <td class="d-none d-md-table-cell">
                        <?php if ($produto['Ativo']): ?>
                            <span class="badge-sucesso px-2 py-1">Ativo</span>
                        <?php else: ?>
                            <span class="badge-atencao px-2 py-1">Rascunho</span>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-md-table-cell" style="width: 180px; min-width: 180px; max-width: 180px;">
                        <a href="<?= URL_BASE ?>/produto.php?id=<?= urlencode($produto['IDProduto']) ?>&preview=1" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary rounded-pill" aria-label="Pré-visualizar">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="<?= URL_BASE ?>/admin/produto/editar.php?id=<?= urlencode($produto['IDProduto']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill" aria-label="Editar">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="post" class="d-inline" data-confirmar="Excluir este produto e todas as suas variações/imagens?">
                            <input type="hidden" name="action" value="excluir">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($produto['IDProduto']) ?>">
                            <button type="submit" class="btn btn-sm btn-perigo rounded-pill"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <tr class="d-md-none">
                    <td colspan="3" class="p-0">
                        <div id="detalhesProduto<?= $produto['IDProduto'] ?>" class="linha-detalhe-expandida">
                            <div class="d-flex justify-content-between small mb-2">
                                <span class="text-secundario">Categoria</span>
                                <span><?= htmlspecialchars($produto['NomeCategoria'] ?? '—') ?></span>
                            </div>
                            <div class="d-flex justify-content-between small mb-2">
                                <span class="text-secundario">Estoque</span>
                                <span><?= (int) $produto['EstoqueTotal'] ?></span>
                            </div>
                            <div class="d-flex justify-content-between small mb-3">
                                <span class="text-secundario">Status</span>
                                <?php if ($produto['Ativo']): ?>
                                    <span class="badge-sucesso px-2 py-1">Ativo</span>
                                <?php else: ?>
                                    <span class="badge-atencao px-2 py-1">Rascunho</span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?= URL_BASE ?>/produto.php?id=<?= urlencode($produto['IDProduto']) ?>&preview=1" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary rounded-pill" aria-label="Pré-visualizar">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="<?= URL_BASE ?>/admin/produto/editar.php?id=<?= urlencode($produto['IDProduto']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill" aria-label="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="post" data-confirmar="Excluir este produto e todas as suas variações/imagens?">
                                    <input type="hidden" name="action" value="excluir">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($produto['IDProduto']) ?>">
                                    <button type="submit" class="btn btn-sm btn-perigo rounded-pill"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$produtos): ?>
                <tr><td colspan="6" class="text-secundario text-center py-4">
                    <?= $filtro !== '' ? 'Nenhum produto encontrado com esse filtro.' : 'Nenhum produto cadastrado ainda.' ?>
                </td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="modalCriarProduto" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Novo produto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="criar">
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="nome" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Categoria</label>
                        <select name="fk_categoria" class="form-select">
                            <option value="" class="opcao-titulo">Sem categoria</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= htmlspecialchars($categoria['IDCategoria']) ?>">
                                    <?= str_repeat('— ', $categoria['Nivel']) ?><?= htmlspecialchars($categoria['Nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p class="text-secundario small mb-0">Depois de criar, você define preço, estoque, descrição e imagens na tela de edição.</p>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-marca rounded-pill">Criar e continuar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../_rodape.php'; ?>
