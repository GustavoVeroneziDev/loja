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

$produtos = $pdo->query("SELECT p.*, c.Nome AS NomeCategoria,
        (SELECT MIN(Preco) FROM VariacaoProduto WHERE FKProduto = p.IDProduto) AS PrecoMinimo,
        (SELECT SUM(Estoque) FROM VariacaoProduto WHERE FKProduto = p.IDProduto) AS EstoqueTotal
    FROM Produto p
    LEFT JOIN Categoria c ON c.IDCategoria = p.FKCategoria
    ORDER BY p.MomentoCriacao DESC")->fetchAll();

$categorias = obterCategoriasArvore();

require __DIR__ . '/../_topo.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Produtos</h1>
    <button class="btn btn-marca rounded-pill" data-bs-toggle="modal" data-bs-target="#modalCriarProduto">
        <i class="bi bi-plus-lg"></i> Novo produto
    </button>
</div>

<?php if ($sucesso): ?><div class="alert alert-success"><?= $sucesso ?></div><?php endif; ?>

<div class="card">
    <table class="table table-dark table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>Produto</th>
                <th>Categoria</th>
                <th>Preço a partir de</th>
                <th>Estoque</th>
                <th>Status</th>
                <th style="width: 140px; min-width: 140px; max-width: 140px;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($produtos as $produto): ?>
                <tr>
                    <td><?= htmlspecialchars($produto['Nome']) ?></td>
                    <td class="text-secundario"><?= htmlspecialchars($produto['NomeCategoria'] ?? '—') ?></td>
                    <td><?= $produto['PrecoMinimo'] !== null ? formatarPreco($produto['PrecoMinimo']) : '—' ?></td>
                    <td><?= (int) $produto['EstoqueTotal'] ?></td>
                    <td>
                        <?php if ($produto['Ativo']): ?>
                            <span class="badge-sucesso px-2 py-1">Ativo</span>
                        <?php else: ?>
                            <span class="badge-atencao px-2 py-1">Rascunho</span>
                        <?php endif; ?>
                    </td>
                    <td style="width: 140px; min-width: 140px; max-width: 140px;">
                        <a href="<?= URL_BASE ?>/admin/produto/editar.php?id=<?= urlencode($produto['IDProduto']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="post" class="d-inline" data-confirmar="Excluir este produto e todas as suas variações/imagens?">
                            <input type="hidden" name="action" value="excluir">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($produto['IDProduto']) ?>">
                            <button type="submit" class="btn btn-sm btn-perigo rounded-pill"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$produtos): ?>
                <tr><td colspan="6" class="text-secundario text-center py-4">Nenhum produto cadastrado ainda.</td></tr>
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
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
                            <option value="">Sem categoria</option>
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
