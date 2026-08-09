<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
require_once __DIR__ . '/../config/marca.php';
exigirLoginAdmin();
garantirTabelaProduto();
garantirTabelaVariacaoProduto();
garantirTabelaMovimentoEstoque();

global $pdo;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $sucesso = null;

    if ($action === 'movimentar') {
        $idVariacao = $_POST['id_variacao'] ?? '';
        $tipo = $_POST['tipo'] ?? '';
        $quantidade = (int) ($_POST['quantidade'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '');
        $tiposPermitidos = ['entrada', 'saida', 'ajuste'];

        if ($idVariacao !== '' && in_array($tipo, $tiposPermitidos, true) && $quantidade >= 0 && ($tipo === 'ajuste' || $quantidade > 0)) {
            $resultado = registrarMovimentoEstoque($idVariacao, $tipo, $quantidade, $motivo, $_SESSION['usuario_id']);
            $sucesso = $resultado !== null;
        }
    }

    if ($action === 'definir_minimo') {
        $idVariacao = $_POST['id_variacao'] ?? '';
        $minimo = trim($_POST['estoque_minimo'] ?? '');
        if ($idVariacao !== '') {
            $stmt = $pdo->prepare("UPDATE VariacaoProduto SET EstoqueMinimo = :minimo WHERE IDVariacao = :id");
            $stmt->execute(['minimo' => $minimo !== '' ? max(0, (int) $minimo) : null, 'id' => $idVariacao]);
            $sucesso = true;
        }
    }

    header('Location: ' . URL_BASE . '/admin/estoque.php' . ($sucesso ? '?ok=1' : '?erro=1'));
    exit;
}

$sucesso = isset($_GET['ok']) ? 'Movimentação registrada.' : null;
$erro = isset($_GET['erro']) ? 'Não foi possível registrar — confere a quantidade (não pode deixar estoque negativo).' : null;

$estoque = obterEstoqueDetalhado();
$movimentosRecentes = obterMovimentosEstoqueRecentes(20);

require __DIR__ . '/_topo.php';
?>
<h1 class="h4 mb-4">Estoque</h1>

<?php if ($sucesso): ?><script>document.addEventListener('DOMContentLoaded', function () { mostrarToastSucesso(<?= json_encode($sucesso) ?>); });</script><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-danger"><?= $erro ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-lg-8">
        <div class="card">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>SKU</th>
                        <th>Estoque</th>
                        <th>Status</th>
                        <th style="width: 90px; min-width: 90px; max-width: 90px;">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($estoque as $v): ?>
                        <?php
                            $desc = descricaoVariacao($v);
                            $baixo = (int) $v['Estoque'] <= (int) $v['EstoqueMinimoEfetivo'];
                            $zerado = (int) $v['Estoque'] === 0;
                        ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($v['NomeProduto']) ?>
                                <?php if ($desc): ?><div class="text-secundario small"><?= htmlspecialchars($desc) ?></div><?php endif; ?>
                            </td>
                            <td class="text-secundario"><?= htmlspecialchars($v['SKU']) ?></td>
                            <td class="fw-semibold"><?= (int) $v['Estoque'] ?></td>
                            <td>
                                <?php if ($zerado): ?>
                                    <span class="badge-perigo px-2 py-1 d-inline-flex align-items-center gap-1">Esgotado</span>
                                <?php elseif ($baixo): ?>
                                    <span class="badge-atencao px-2 py-1 d-inline-flex align-items-center gap-1"><i class="bi bi-fire"></i> Baixo</span>
                                <?php else: ?>
                                    <span class="badge-sucesso px-2 py-1 d-inline-flex align-items-center gap-1">OK</span>
                                <?php endif; ?>
                            </td>
                            <td style="width: 90px; min-width: 90px; max-width: 90px;">
                                <button class="btn btn-sm btn-outline-secondary rounded-pill" data-bs-toggle="modal" data-bs-target="#modalMovimentar<?= $v['IDVariacao'] ?>">
                                    <i class="bi bi-arrow-left-right"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$estoque): ?>
                        <tr><td colspan="5" class="text-secundario text-center py-4">Nenhuma variação cadastrada ainda.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card p-4">
            <h2 class="h6 text-secundario text-uppercase mb-3">Movimentações recentes</h2>
            <?php if (!$movimentosRecentes): ?>
                <p class="text-secundario small mb-0">Nenhuma movimentação ainda.</p>
            <?php endif; ?>
            <?php foreach ($movimentosRecentes as $m): ?>
                <?php
                    $rotulos = ['entrada' => 'Entrada', 'saida' => 'Saída', 'ajuste' => 'Ajuste', 'venda' => 'Venda', 'cancelamento' => 'Cancelamento'];
                    $descM = descricaoVariacao($m);
                ?>
                <div class="d-flex justify-content-between align-items-start gap-2 py-2 border-bottom small">
                    <div>
                        <div><?= htmlspecialchars($m['NomeProduto']) ?><?= $descM ? ' — ' . htmlspecialchars($descM) : '' ?></div>
                        <div class="text-secundario">
                            <?= $rotulos[$m['Tipo']] ?? $m['Tipo'] ?>
                            <?= $m['Quantidade'] > 0 ? '+' . $m['Quantidade'] : $m['Quantidade'] ?>
                            <?php if ($m['NomeUsuario']): ?> — <?= htmlspecialchars($m['NomeUsuario']) ?><?php endif; ?>
                        </div>
                        <?php if ($m['Motivo']): ?><div class="text-secundario fst-italic"><?= htmlspecialchars($m['Motivo']) ?></div><?php endif; ?>
                    </div>
                    <span class="text-secundario text-nowrap"><?= date('d/m H:i', strtotime($m['MomentoMovimento'])) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php foreach ($estoque as $v): ?>
    <div class="modal fade" id="modalMovimentar<?= $v['IDVariacao'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= htmlspecialchars($v['NomeProduto']) ?><?= descricaoVariacao($v) ? ' — ' . htmlspecialchars(descricaoVariacao($v)) : '' ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secundario">Estoque atual: <strong><?= (int) $v['Estoque'] ?></strong></p>
                    <form method="post" class="mb-4">
                        <input type="hidden" name="action" value="movimentar">
                        <input type="hidden" name="id_variacao" value="<?= htmlspecialchars($v['IDVariacao']) ?>">
                        <div class="mb-3">
                            <label class="form-label d-block">Tipo</label>
                            <input type="radio" class="btn-check" name="tipo" value="entrada" id="entrada<?= $v['IDVariacao'] ?>" checked>
                            <label class="btn btn-outline-secondary btn-sm" for="entrada<?= $v['IDVariacao'] ?>">Entrada</label>
                            <input type="radio" class="btn-check" name="tipo" value="saida" id="saida<?= $v['IDVariacao'] ?>">
                            <label class="btn btn-outline-secondary btn-sm" for="saida<?= $v['IDVariacao'] ?>">Saída</label>
                            <input type="radio" class="btn-check" name="tipo" value="ajuste" id="ajuste<?= $v['IDVariacao'] ?>">
                            <label class="btn btn-outline-secondary btn-sm" for="ajuste<?= $v['IDVariacao'] ?>">Ajustar pra um número exato</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantidade</label>
                            <input type="number" name="quantidade" class="form-control" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Motivo (opcional)</label>
                            <input type="text" name="motivo" class="form-control" placeholder="ex: Reposição NF 1234, avaria, contagem física...">
                        </div>
                        <button type="submit" class="btn btn-marca rounded-pill">Registrar</button>
                    </form>

                    <hr>
                    <label class="form-label">Alertar quando chegar em</label>
                    <form method="post" class="d-flex gap-2">
                        <input type="hidden" name="action" value="definir_minimo">
                        <input type="hidden" name="id_variacao" value="<?= htmlspecialchars($v['IDVariacao']) ?>">
                        <input type="number" name="estoque_minimo" class="form-control" min="0" placeholder="padrão: <?= (int) ESTOQUE_MINIMO_PADRAO ?>" value="<?= $v['EstoqueMinimo'] !== null ? (int) $v['EstoqueMinimo'] : '' ?>">
                        <button type="submit" class="btn btn-outline-secondary rounded-pill text-nowrap">Salvar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php require __DIR__ . '/_rodape.php'; ?>
