<?php
session_start();
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../config/funcoes.php';
require_once __DIR__ . '/../../config/chaves.php';
exigirLoginAdmin();
garantirTabelaCaixaEnvio();
garantirTabelaConfiguracaoSistema();

global $pdo;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $sucesso = null;

    if ($action === 'desconectar_melhor_envio') {
        melhorEnvioDesconectar();
        $sucesso = true;
    }

    if ($action === 'criar' || $action === 'editar') {
        $id = $_POST['id'] ?? '';
        $nome = trim($_POST['nome'] ?? '');
        $peso = (float) str_replace(',', '.', $_POST['peso'] ?? '0');
        $altura = (float) str_replace(',', '.', $_POST['altura'] ?? '0');
        $largura = (float) str_replace(',', '.', $_POST['largura'] ?? '0');
        $comprimento = (float) str_replace(',', '.', $_POST['comprimento'] ?? '0');

        if ($nome !== '' && $peso > 0 && $altura > 0 && $largura > 0 && $comprimento > 0) {
            if ($action === 'criar') {
                $stmt = $pdo->prepare("INSERT INTO CaixaEnvio (IDCaixaEnvio, Nome, Peso, Altura, Largura, Comprimento) VALUES (:id, :nome, :peso, :altura, :largura, :comprimento)");
                $stmt->execute([
                    'id' => gerarUuid(), 'nome' => $nome, 'peso' => $peso,
                    'altura' => $altura, 'largura' => $largura, 'comprimento' => $comprimento,
                ]);
            } elseif ($id !== '') {
                $stmt = $pdo->prepare("UPDATE CaixaEnvio SET Nome = :nome, Peso = :peso, Altura = :altura, Largura = :largura, Comprimento = :comprimento WHERE IDCaixaEnvio = :id");
                $stmt->execute([
                    'nome' => $nome, 'peso' => $peso, 'altura' => $altura,
                    'largura' => $largura, 'comprimento' => $comprimento, 'id' => $id,
                ]);
            }
            $sucesso = true;
        }
    }

    if ($action === 'excluir') {
        $id = $_POST['id'] ?? '';
        if ($id !== '') {
            // Produto que usava essa caixa não trava a exclusão — FKCaixaEnvio vai pra NULL
            // sozinho (ON DELETE SET NULL), o produto só fica "sem caixa definida" até escolher outra.
            $stmt = $pdo->prepare("DELETE FROM CaixaEnvio WHERE IDCaixaEnvio = :id");
            $stmt->execute(['id' => $id]);
            $sucesso = true;
        }
    }

    header('Location: ' . URL_BASE . '/admin/entregas/index.php' . ($sucesso ? '?ok=1' : '?erro=1'));
    exit;
}

$sucesso = isset($_GET['ok']) ? 'Operação realizada com sucesso.' : null;
$erro = isset($_GET['erro']) ? 'Preencha nome, peso e as 3 dimensões (todos maiores que zero).' : null;
$caixas = obterCaixasEnvio();
$melhorEnvioConectado = melhorEnvioConectado();

require __DIR__ . '/../_topo.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Entregas</h1>
    <button class="btn btn-marca rounded-pill" data-bs-toggle="modal" data-bs-target="#modalCriarCaixa">
        <i class="bi bi-plus-lg"></i> Nova caixa
    </button>
</div>

<?php if ($sucesso): ?><script>document.addEventListener('DOMContentLoaded', function () { mostrarToastSucesso(<?= json_encode($sucesso) ?>); });</script><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-danger"><?= $erro ?></div><?php endif; ?>

<div class="card p-4 mb-4">
    <h2 class="h5 mb-3">Integração Melhor Envio</h2>
    <?php if ($melhorEnvioConectado): ?>
        <p class="mb-3"><span class="badge-sucesso px-2 py-1 d-inline-flex align-items-center gap-1"><i class="bi bi-check-circle-fill"></i> Conectado</span> — o frete no checkout já usa cotação real (ambiente: <strong><?= MELHOR_ENVIO_AMBIENTE === 'producao' ? 'produção' : 'sandbox (teste)' ?></strong>).</p>
        <form method="post" data-confirmar="Desconectar o Melhor Envio? O checkout volta a usar o frete fixo até reconectar.">
            <input type="hidden" name="action" value="desconectar_melhor_envio">
            <button type="submit" class="btn btn-outline-secondary rounded-pill"><i class="bi bi-plug"></i> Desconectar</button>
        </form>
    <?php elseif (MELHOR_ENVIO_CLIENT_ID === '' || MELHOR_ENVIO_CEP_ORIGEM === ''): ?>
        <p class="mb-0"><span class="badge-atencao px-2 py-1 d-inline-flex align-items-center gap-1"><i class="bi bi-exclamation-triangle"></i> Não configurado</span> — falta preencher Client ID/Secret e o CEP de origem em <code>config/chaves.php</code> antes de conectar.</p>
    <?php else: ?>
        <p class="mb-3"><span class="badge-atencao px-2 py-1 d-inline-flex align-items-center gap-1"><i class="bi bi-exclamation-triangle"></i> Não conectado</span> — o checkout está usando o frete fixo de reserva (config/marca.php) até conectar.</p>
        <a href="<?= URL_BASE ?>/admin/entregas/melhor-envio-conectar.php" class="btn btn-marca rounded-pill"><i class="bi bi-plug"></i> Conectar com Melhor Envio</a>
    <?php endif; ?>
</div>

<p class="text-secundario">Peso e tamanho de embalagem, pra calcular o frete de verdade. Cadastre uma vez (ex: "Caixa P", "Caixa M") e escolha qual cada produto usa na própria tela de produto.</p>

<div class="card">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Peso</th>
                <th>Dimensões (A×L×C)</th>
                <th style="width: 100px; min-width: 100px; max-width: 100px;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($caixas as $caixa): ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($caixa['Nome']) ?></td>
                    <td><?= number_format($caixa['Peso'], 3, ',', '.') ?> kg</td>
                    <td class="text-secundario"><?= number_format($caixa['Altura'], 1, ',', '.') ?> × <?= number_format($caixa['Largura'], 1, ',', '.') ?> × <?= number_format($caixa['Comprimento'], 1, ',', '.') ?> cm</td>
                    <td style="width: 100px; min-width: 100px; max-width: 100px;">
                        <button class="btn btn-sm btn-outline-secondary rounded-pill" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $caixa['IDCaixaEnvio'] ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="post" class="d-inline" data-confirmar="Excluir a <?= htmlspecialchars($caixa['Nome']) ?>? Produtos que usam ela ficam sem caixa definida.">
                            <input type="hidden" name="action" value="excluir">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($caixa['IDCaixaEnvio']) ?>">
                            <button type="submit" class="btn btn-sm btn-perigo rounded-pill"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$caixas): ?>
                <tr><td colspan="4" class="text-secundario text-center py-4">Nenhuma caixa cadastrada ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php foreach ($caixas as $caixa): ?>
    <div class="modal fade" id="modalEditar<?= $caixa['IDCaixaEnvio'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar caixa</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="editar">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($caixa['IDCaixaEnvio']) ?>">
                        <div class="mb-3">
                            <label class="form-label">Nome</label>
                            <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($caixa['Nome']) ?>" placeholder="ex: Caixa P" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Peso (kg)</label>
                            <input type="text" name="peso" class="form-control mascara-peso" inputmode="decimal" value="<?= number_format($caixa['Peso'], 3, ',', '.') ?>" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-4">
                                <label class="form-label">Altura (cm)</label>
                                <input type="text" name="altura" class="form-control mascara-dimensao" inputmode="decimal" value="<?= number_format($caixa['Altura'], 1, ',', '.') ?>" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label">Largura (cm)</label>
                                <input type="text" name="largura" class="form-control mascara-dimensao" inputmode="decimal" value="<?= number_format($caixa['Largura'], 1, ',', '.') ?>" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label">Comprimento (cm)</label>
                                <input type="text" name="comprimento" class="form-control mascara-dimensao" inputmode="decimal" value="<?= number_format($caixa['Comprimento'], 1, ',', '.') ?>" required>
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

<div class="modal fade" id="modalCriarCaixa" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Nova caixa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="criar">
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="nome" class="form-control" placeholder="ex: Caixa P" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Peso (kg)</label>
                        <input type="text" name="peso" class="form-control mascara-peso" inputmode="decimal" placeholder="0,300" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-4">
                            <label class="form-label">Altura (cm)</label>
                            <input type="text" name="altura" class="form-control mascara-dimensao" inputmode="decimal" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Largura (cm)</label>
                            <input type="text" name="largura" class="form-control mascara-dimensao" inputmode="decimal" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Comprimento (cm)</label>
                            <input type="text" name="comprimento" class="form-control mascara-dimensao" inputmode="decimal" required>
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
