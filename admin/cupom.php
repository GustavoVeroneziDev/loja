<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
exigirLoginAdmin();
garantirTabelaCupom();

global $pdo;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $sucesso = null;

    if ($action === 'criar' || $action === 'editar') {
        $id = $_POST['id'] ?? '';
        $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
        $tipoDesconto = $_POST['tipo_desconto'] ?? 'percentual';
        // Percentual digita "10%" (número inteiro direto); fixo digita em centavos tipo o preço
        // de produto ("R$ 15,00") — são máscaras diferentes, não dá pra tratar os dois igual.
        $valorDesconto = $tipoDesconto === 'percentual'
            ? (int) preg_replace('/\D/', '', $_POST['valor_desconto'] ?? '0')
            : ((int) preg_replace('/\D/', '', $_POST['valor_desconto'] ?? '0')) / 100;
        $dataValidade = trim($_POST['data_validade'] ?? '');
        $limiteUso = trim($_POST['limite_uso'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($codigo !== '' && $valorDesconto > 0 && ($tipoDesconto !== 'percentual' || $valorDesconto <= 100)) {
            if ($action === 'criar') {
                $stmt = $pdo->prepare("INSERT INTO Cupom (IDCupom, Codigo, TipoDesconto, ValorDesconto, DataValidade, LimiteUso, Ativo) VALUES (:id, :codigo, :tipo, :valor, :validade, :limite, :ativo)");
                $stmt->execute([
                    'id' => gerarUuid(),
                    'codigo' => $codigo,
                    'tipo' => $tipoDesconto,
                    'valor' => $valorDesconto,
                    'validade' => $dataValidade !== '' ? $dataValidade : null,
                    'limite' => $limiteUso !== '' ? (int) $limiteUso : null,
                    'ativo' => $ativo,
                ]);
            } elseif ($id !== '') {
                $stmt = $pdo->prepare("UPDATE Cupom SET Codigo = :codigo, TipoDesconto = :tipo, ValorDesconto = :valor, DataValidade = :validade, LimiteUso = :limite, Ativo = :ativo WHERE IDCupom = :id");
                $stmt->execute([
                    'codigo' => $codigo,
                    'tipo' => $tipoDesconto,
                    'valor' => $valorDesconto,
                    'validade' => $dataValidade !== '' ? $dataValidade : null,
                    'limite' => $limiteUso !== '' ? (int) $limiteUso : null,
                    'ativo' => $ativo,
                    'id' => $id,
                ]);
            }
            $sucesso = true;
        }
    }

    if ($action === 'excluir') {
        $id = $_POST['id'] ?? '';
        if ($id !== '') {
            $stmt = $pdo->prepare("DELETE FROM Cupom WHERE IDCupom = :id");
            $stmt->execute(['id' => $id]);
            $sucesso = true;
        }
    }

    header('Location: ' . URL_BASE . '/admin/cupom.php' . ($sucesso ? '?ok=1' : '?erro=1'));
    exit;
}

$sucesso = isset($_GET['ok']) ? 'Operação realizada com sucesso.' : null;
$erro = isset($_GET['erro']) ? 'Confira o código e o valor do desconto (percentual não passa de 100%).' : null;
$cupons = $pdo->query("SELECT * FROM Cupom ORDER BY MomentoCriacao DESC")->fetchAll();

require __DIR__ . '/_topo.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Cupons</h1>
    <button class="btn btn-marca rounded-pill" data-bs-toggle="modal" data-bs-target="#modalCriarCupom">
        <i class="bi bi-plus-lg"></i> Novo cupom
    </button>
</div>

<?php if ($sucesso): ?><script>document.addEventListener('DOMContentLoaded', function () { mostrarToastSucesso(<?= json_encode($sucesso) ?>); });</script><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-danger"><?= $erro ?></div><?php endif; ?>

<div class="card">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>Código</th>
                <th>Desconto</th>
                <th>Validade</th>
                <th>Uso</th>
                <th>Status</th>
                <th style="width: 110px; min-width: 110px; max-width: 110px;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cupons as $cupom): ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($cupom['Codigo']) ?></td>
                    <td><?= $cupom['TipoDesconto'] === 'percentual' ? ((int) $cupom['ValorDesconto']) . '%' : formatarPreco($cupom['ValorDesconto']) ?></td>
                    <td class="text-secundario"><?= $cupom['DataValidade'] ? date('d/m/Y', strtotime($cupom['DataValidade'])) : 'sem validade' ?></td>
                    <td class="text-secundario"><?= (int) $cupom['UsosAtuais'] ?><?= $cupom['LimiteUso'] !== null ? ' / ' . (int) $cupom['LimiteUso'] : '' ?></td>
                    <td>
                        <?php if ($cupom['Ativo']): ?>
                            <span class="badge-sucesso px-2 py-1">Ativo</span>
                        <?php else: ?>
                            <span class="badge-perigo px-2 py-1">Inativo</span>
                        <?php endif; ?>
                    </td>
                    <td style="width: 110px; min-width: 110px; max-width: 110px;">
                        <button class="btn btn-sm btn-outline-secondary rounded-pill" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $cupom['IDCupom'] ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="post" class="d-inline" data-confirmar="Excluir o cupom <?= htmlspecialchars($cupom['Codigo']) ?>?">
                            <input type="hidden" name="action" value="excluir">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($cupom['IDCupom']) ?>">
                            <button type="submit" class="btn btn-sm btn-perigo rounded-pill"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$cupons): ?>
                <tr><td colspan="6" class="text-secundario text-center py-4">Nenhum cupom cadastrado ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php foreach ($cupons as $cupom): ?>
    <div class="modal fade" id="modalEditar<?= $cupom['IDCupom'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar cupom</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="editar">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($cupom['IDCupom']) ?>">
                        <div class="mb-3">
                            <label class="form-label">Código</label>
                            <input type="text" name="codigo" class="form-control text-uppercase" value="<?= htmlspecialchars($cupom['Codigo']) ?>" required>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label">Tipo</label>
                                <select name="tipo_desconto" class="form-select">
                                    <option value="percentual" <?= $cupom['TipoDesconto'] === 'percentual' ? 'selected' : '' ?>>Percentual</option>
                                    <option value="fixo" <?= $cupom['TipoDesconto'] === 'fixo' ? 'selected' : '' ?>>Valor fixo</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Valor</label>
                                <input type="text" name="valor_desconto" class="form-control <?= $cupom['TipoDesconto'] === 'percentual' ? 'mascara-percentual' : 'mascara-preco' ?>" inputmode="numeric"
                                       value="<?= $cupom['TipoDesconto'] === 'percentual' ? ((int) $cupom['ValorDesconto']) . '%' : htmlspecialchars(formatarPreco($cupom['ValorDesconto'])) ?>" required>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label">Validade</label>
                                <input type="date" name="data_validade" class="form-control" value="<?= htmlspecialchars($cupom['DataValidade'] ?? '') ?>">
                                <div class="form-text">Em branco = sem validade.</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Limite de usos</label>
                                <input type="number" name="limite_uso" class="form-control" min="1" value="<?= htmlspecialchars($cupom['LimiteUso'] ?? '') ?>">
                                <div class="form-text">Em branco = ilimitado.</div>
                            </div>
                        </div>
                        <div class="form-check form-switch">
                            <input type="checkbox" name="ativo" class="form-check-input" id="ativo<?= $cupom['IDCupom'] ?>" <?= $cupom['Ativo'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativo<?= $cupom['IDCupom'] ?>">Ativo</label>
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

<div class="modal fade" id="modalCriarCupom" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Novo cupom</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="criar">
                    <div class="mb-3">
                        <label class="form-label">Código</label>
                        <input type="text" name="codigo" class="form-control text-uppercase" placeholder="ex: BEMVINDO10" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label">Tipo</label>
                            <select name="tipo_desconto" class="form-select" id="tipoDescontoNovo" onchange="document.getElementById('valorDescontoNovo').className = 'form-control ' + (this.value === 'percentual' ? 'mascara-percentual' : 'mascara-preco'); document.getElementById('valorDescontoNovo').value = '';">
                                <option value="percentual">Percentual</option>
                                <option value="fixo">Valor fixo</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Valor</label>
                            <input type="text" name="valor_desconto" id="valorDescontoNovo" class="form-control mascara-percentual" inputmode="numeric" placeholder="0%" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label">Validade</label>
                            <input type="date" name="data_validade" class="form-control">
                            <div class="form-text">Em branco = sem validade.</div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Limite de usos</label>
                            <input type="number" name="limite_uso" class="form-control" min="1">
                            <div class="form-text">Em branco = ilimitado.</div>
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input type="checkbox" name="ativo" class="form-check-input" id="ativoNovo" checked>
                        <label class="form-check-label" for="ativoNovo">Ativo</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-marca rounded-pill">Criar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.mascara-percentual').forEach(function (campo) {
    campo.addEventListener('input', function () {
        var numeros = parseInt(campo.value.replace(/\D/g, ''), 10) || 0;
        campo.value = Math.min(100, numeros) + '%';
        campo.setSelectionRange(campo.value.length - 1, campo.value.length - 1);
    });
});
</script>
<?php require __DIR__ . '/_rodape.php'; ?>
