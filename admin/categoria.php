<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
exigirLoginAdmin();
garantirTabelaCategoria();

global $pdo;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $sucesso = null;

    if ($action === 'criar') {
        $nome = trim($_POST['nome'] ?? '');
        $fkPai = $_POST['fk_categoria_pai'] ?? '';
        if ($nome !== '') {
            $stmt = $pdo->prepare("INSERT INTO Categoria (IDCategoria, Nome, FKCategoriaPai) VALUES (:id, :nome, :pai)");
            $stmt->execute([
                'id' => gerarUuid(),
                'nome' => $nome,
                'pai' => $fkPai !== '' ? $fkPai : null,
            ]);
            $sucesso = true;
        }
    }

    if ($action === 'editar') {
        $id = $_POST['id'] ?? '';
        $nome = trim($_POST['nome'] ?? '');
        $fkPai = $_POST['fk_categoria_pai'] ?? '';
        if ($id !== '' && $nome !== '' && $fkPai !== $id) {
            $stmt = $pdo->prepare("UPDATE Categoria SET Nome = :nome, FKCategoriaPai = :pai WHERE IDCategoria = :id");
            $stmt->execute([
                'nome' => $nome,
                'pai' => $fkPai !== '' ? $fkPai : null,
                'id' => $id,
            ]);
            $sucesso = true;
        }
    }

    if ($action === 'excluir') {
        $id = $_POST['id'] ?? '';
        if ($id !== '') {
            $stmt = $pdo->prepare("DELETE FROM Categoria WHERE IDCategoria = :id");
            $stmt->execute(['id' => $id]);
            $sucesso = true;
        }
    }

    header('Location: ' . URL_BASE . '/admin/categoria.php' . ($sucesso ? '?ok=1' : '?erro=1'));
    exit;
}

$sucesso = isset($_GET['ok']) ? 'Operação realizada com sucesso.' : null;
$erro = isset($_GET['erro']) ? 'Ocorreu um erro. Tente novamente.' : null;
$categorias = obterCategoriasArvore();

require __DIR__ . '/_topo.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Categorias</h1>
    <button class="btn btn-marca rounded-pill" data-bs-toggle="modal" data-bs-target="#modalCriarCategoria">
        <i class="bi bi-plus-lg"></i> Nova categoria
    </button>
</div>

<?php if ($sucesso): ?><div class="alert alert-success"><?= $sucesso ?></div><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-danger"><?= $erro ?></div><?php endif; ?>

<div class="card">
    <table class="table table-dark table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>Nome</th>
                <th style="width: 160px; min-width: 160px; max-width: 160px;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categorias as $categoria): ?>
                <tr>
                    <td><?= str_repeat('— ', $categoria['Nivel']) ?><?= htmlspecialchars($categoria['Nome']) ?></td>
                    <td style="width: 160px; min-width: 160px; max-width: 160px;">
                        <button class="btn btn-sm btn-outline-secondary rounded-pill" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $categoria['IDCategoria'] ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="post" class="d-inline" data-confirmar="Excluir esta categoria?">
                            <input type="hidden" name="action" value="excluir">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($categoria['IDCategoria']) ?>">
                            <button type="submit" class="btn btn-sm btn-perigo rounded-pill"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$categorias): ?>
                <tr><td colspan="2" class="text-secundario text-center py-4">Nenhuma categoria cadastrada ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php foreach ($categorias as $categoria): ?>
    <div class="modal fade" id="modalEditar<?= $categoria['IDCategoria'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar categoria</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="editar">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($categoria['IDCategoria']) ?>">
                        <div class="mb-3">
                            <label class="form-label">Nome</label>
                            <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($categoria['Nome']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Categoria pai</label>
                            <select name="fk_categoria_pai" class="form-select">
                                <option value="">Nenhuma (categoria de topo)</option>
                                <?php foreach ($categorias as $opcao): ?>
                                    <?php if ($opcao['IDCategoria'] !== $categoria['IDCategoria']): ?>
                                        <option value="<?= htmlspecialchars($opcao['IDCategoria']) ?>" <?= $opcao['IDCategoria'] === $categoria['FKCategoriaPai'] ? 'selected' : '' ?>>
                                            <?= str_repeat('— ', $opcao['Nivel']) ?><?= htmlspecialchars($opcao['Nome']) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
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

<div class="modal fade" id="modalCriarCategoria" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Nova categoria</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="criar">
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="nome" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Categoria pai</label>
                        <select name="fk_categoria_pai" class="form-select">
                            <option value="">Nenhuma (categoria de topo)</option>
                            <?php foreach ($categorias as $opcao): ?>
                                <option value="<?= htmlspecialchars($opcao['IDCategoria']) ?>">
                                    <?= str_repeat('— ', $opcao['Nivel']) ?><?= htmlspecialchars($opcao['Nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-marca rounded-pill">Criar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/_rodape.php'; ?>
