<?php
session_start();
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../config/funcoes.php';
require_once __DIR__ . '/../../config/marca.php';
require_once __DIR__ . '/../../config/chaves.php';
exigirLoginAdmin();
garantirTabelaPedido();
garantirTabelaMovimentoEstoque();
garantirTabelaItemPedido();
garantirTabelaHistoricoStatusPedido();
garantirTabelaUsuario();
garantirTabelaConfiguracaoSistema();

global $pdo;

$idPedido = (int) ($_GET['id'] ?? 0);
$pedido = obterPedidoPorId($idPedido);
if (!$pedido) {
    header('Location: ' . URL_BASE . '/admin/pedido/index.php');
    exit;
}

// Gera/compra etiqueta só faz sentido pra pedido pago de verdade — comprar etiqueta (gasta saldo
// real) pra um pedido ainda não pago, ou cancelado (estoque já foi devolvido), não devia nem ser
// possível clicar, e muito menos aceitar via POST direto se alguém tentar pular a tela.
$statusPermiteEtiqueta = !in_array($pedido['Status'], ['aguardando_pagamento', 'cancelado'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $sucesso = null;

    if ($action === 'mudar_status') {
        $novoStatus = $_POST['status'] ?? '';
        $statusValidos = ['aguardando_pagamento', 'pago', 'preparando', 'enviado', 'entregue', 'cancelado'];
        if (in_array($novoStatus, $statusValidos, true)) {
            $sucesso = mudarStatusPedido($idPedido, $novoStatus);
        }
    }

    if ($action === 'atualizar_rastreio') {
        $codigo = trim($_POST['codigo_rastreio'] ?? '');
        $stmt = $pdo->prepare("UPDATE Pedido SET CodigoRastreio = :codigo WHERE IDPedido = :id");
        $stmt->execute(['codigo' => $codigo !== '' ? $codigo : null, 'id' => $idPedido]);
        $sucesso = true;
    }

    // Só um passo (adicionar ao carrinho, comprar, gerar+imprimir) por clique — assim, se falhar no
    // meio, o admin sabe exatamente qual passo tentar de novo em vez de repetir tudo (e "comprar" de
    // novo o que já foi comprado). "Comprar" é o único que debita saldo de verdade — não roda
    // automático encadeado com os outros, exige clique explícito próprio. Confere de novo aqui (não
    // só esconder o botão) porque nada impede um POST direto pulando a tela.
    if (in_array($action, ['etiqueta_adicionar_carrinho', 'etiqueta_comprar', 'etiqueta_gerar_imprimir'], true) && !$statusPermiteEtiqueta) {
        $_SESSION['flash_erro_etiqueta'] = 'Esse pedido está "' . statusPedidoInfo($pedido['Status'])['label'] . '" — só dá pra gerar etiqueta de pedido pago (ou em status posterior), nunca aguardando pagamento ou cancelado.';
        $sucesso = false;
    } elseif ($action === 'etiqueta_adicionar_carrinho') {
        $resultado = melhorEnvioAdicionarAoCarrinho($idPedido);
        $sucesso = $resultado['sucesso'];
        if (!$sucesso) {
            $_SESSION['flash_erro_etiqueta'] = $resultado['erro'];
        }
    } elseif ($action === 'etiqueta_comprar') {
        $resultado = melhorEnvioComprarEtiqueta($idPedido);
        $sucesso = $resultado['sucesso'];
        if (!$sucesso) {
            $_SESSION['flash_erro_etiqueta'] = $resultado['erro'];
        }
    } elseif ($action === 'etiqueta_gerar_imprimir') {
        $resultado = melhorEnvioGerarEImprimirEtiqueta($idPedido);
        $sucesso = $resultado['sucesso'];
        if (!$sucesso) {
            $_SESSION['flash_erro_etiqueta'] = $resultado['erro'];
        }
    }

    header('Location: ' . URL_BASE . '/admin/pedido/detalhe.php?id=' . $idPedido . ($sucesso ? '&ok=1' : '&erro=1'));
    exit;
}

$sucesso = isset($_GET['ok']) ? 'Operação realizada com sucesso.' : null;
$erro = isset($_GET['erro']) ? (isset($_SESSION['flash_erro_etiqueta']) ? $_SESSION['flash_erro_etiqueta'] : 'Não foi possível concluir a ação.') : null;
unset($_SESSION['flash_erro_etiqueta']);

$pedido = obterPedidoPorId($idPedido);
$itens = obterItensPedido($idPedido);
$historico = obterHistoricoPedido($idPedido);
$info = statusPedidoInfo($pedido['Status']);
$statusOpcoes = ['aguardando_pagamento', 'pago', 'preparando', 'enviado', 'entregue', 'cancelado'];

require __DIR__ . '/../_topo.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <h1 class="h4 mb-0">
        Pedido #<?= str_pad($pedido['IDPedido'], 5, '0', STR_PAD_LEFT) ?>
        <?php if ($pedido['Simulacao']): ?><span class="badge-destaque px-2 py-1 small"><i class="bi bi-flask"></i> Pedido de simulação</span><?php endif; ?>
    </h1>
    <a href="<?= URL_BASE ?>/admin/pedido/index.php" class="btn btn-sm btn-outline-secondary rounded-pill"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>

<?php if ($sucesso): ?><script>document.addEventListener('DOMContentLoaded', function () { mostrarToastSucesso(<?= json_encode($sucesso) ?>); });</script><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-danger"><?= $erro ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-lg-7">
        <div class="card p-4 mb-4">
            <h2 class="h5 mb-3">Cliente</h2>
            <p class="mb-0"><?= htmlspecialchars($pedido['NomeCliente']) ?><br><span class="text-secundario small"><?= htmlspecialchars($pedido['EmailCliente']) ?></span></p>
        </div>

        <div class="card p-4 mb-4">
            <h2 class="h5 mb-3">Itens</h2>
            <?php foreach ($itens as $item): ?>
                <div class="d-flex justify-content-between gap-2 mb-2">
                    <span><?= (int) $item['Quantidade'] ?>x <?= htmlspecialchars($item['NomeProduto']) ?><?= $item['DescricaoVariacao'] ? ' (' . htmlspecialchars($item['DescricaoVariacao']) . ')' : '' ?></span>
                    <span class="text-nowrap"><?= formatarPreco($item['PrecoUnitario'] * $item['Quantidade']) ?></span>
                </div>
            <?php endforeach; ?>
            <hr>
            <div class="d-flex justify-content-between mb-1">
                <span class="text-secundario">Subtotal</span>
                <span><?= formatarPreco($pedido['ValorSubtotal']) ?></span>
            </div>
            <?php if ($pedido['ValorDesconto'] > 0): ?>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-secundario">Desconto</span>
                    <span class="text-sucesso">-<?= formatarPreco($pedido['ValorDesconto']) ?></span>
                </div>
            <?php endif; ?>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-secundario">
                    Frete
                    <?php if ($pedido['FreteTransportadora']): ?>
                        <span class="d-block text-secundario small"><?= htmlspecialchars($pedido['FreteTransportadora']) ?> — <?= htmlspecialchars($pedido['FreteServico']) ?><?= $pedido['FretePrazoDias'] ? ' · ' . (int) $pedido['FretePrazoDias'] . ' dia(s) útil(eis)' : '' ?></span>
                    <?php endif; ?>
                </span>
                <span><?= $pedido['ValorFrete'] > 0 ? formatarPreco($pedido['ValorFrete']) : 'Grátis' ?></span>
            </div>
            <div class="d-flex justify-content-between">
                <span class="fw-semibold">Total</span>
                <span class="fw-semibold h5 mb-0"><?= formatarPreco($pedido['ValorTotal']) ?></span>
            </div>
        </div>

        <div class="card p-4">
            <h2 class="h5 mb-3">Endereço de entrega</h2>
            <p class="mb-0">
                <?= htmlspecialchars($pedido['EnderecoLogradouro']) ?>, <?= htmlspecialchars($pedido['EnderecoNumero']) ?>
                <?php if ($pedido['EnderecoComplemento']): ?> — <?= htmlspecialchars($pedido['EnderecoComplemento']) ?><?php endif; ?><br>
                <?php if ($pedido['EnderecoBairro']): ?><?= htmlspecialchars($pedido['EnderecoBairro']) ?> — <?php endif; ?>
                <?= htmlspecialchars($pedido['EnderecoCidade']) ?>/<?= htmlspecialchars($pedido['EnderecoUF']) ?><br>
                CEP <?= htmlspecialchars($pedido['EnderecoCep']) ?>
            </p>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card p-4 mb-4">
            <h2 class="h5 mb-3">Status</h2>
            <p class="mb-3"><span class="<?= $info['badge'] ?> px-2 py-1"><i class="bi <?= $info['icone'] ?>"></i> <?= $info['label'] ?></span></p>
            <form method="post" class="d-flex gap-2 mb-4">
                <input type="hidden" name="action" value="mudar_status">
                <select name="status" class="form-select">
                    <?php foreach ($statusOpcoes as $opcao): ?>
                        <option value="<?= $opcao ?>" <?= $pedido['Status'] === $opcao ? 'selected' : '' ?>><?= htmlspecialchars(statusPedidoInfo($opcao)['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-marca rounded-pill text-nowrap">Mudar</button>
            </form>

            <form method="post" class="d-flex gap-2">
                <input type="hidden" name="action" value="atualizar_rastreio">
                <input type="text" name="codigo_rastreio" class="form-control" placeholder="Código de rastreio" value="<?= htmlspecialchars($pedido['CodigoRastreio'] ?? '') ?>">
                <button type="submit" class="btn btn-outline-secondary rounded-pill text-nowrap">Salvar</button>
            </form>
        </div>

        <div class="card p-4 mb-4">
            <h2 class="h5 mb-3">Etiqueta de envio</h2>
            <?php if ($pedido['EtiquetaUrl']): ?>
                <p class="mb-2"><span class="badge-sucesso px-2 py-1 d-inline-flex align-items-center gap-1"><i class="bi bi-check-circle-fill"></i> Etiqueta gerada</span></p>
                <?php if ($pedido['CodigoRastreio']): ?><p class="mb-2">Rastreio: <strong><?= htmlspecialchars($pedido['CodigoRastreio']) ?></strong></p><?php endif; ?>
                <a href="<?= htmlspecialchars($pedido['EtiquetaUrl']) ?>" target="_blank" class="btn btn-marca rounded-pill"><i class="bi bi-printer"></i> Abrir etiqueta pra imprimir</a>
            <?php elseif (!$statusPermiteEtiqueta): ?>
                <p class="text-secundario small mb-0">
                    <i class="bi bi-hourglass-split"></i> Pedido "<?= htmlspecialchars(statusPedidoInfo($pedido['Status'])['label']) ?>" —
                    <?= $pedido['Status'] === 'cancelado' ? 'pedido cancelado não gera etiqueta.' : 'só dá pra gerar etiqueta depois que o pagamento for confirmado.' ?>
                </p>
            <?php elseif (!$pedido['FreteServicoId']): ?>
                <p class="text-secundario small mb-0">Esse pedido não tem um serviço de frete cotado pelo Melhor Envio (caiu no frete fixo, ou é de antes dessa funcionalidade) — anexe o código de rastreio manualmente ali em cima.</p>
            <?php elseif ($pedido['EtiquetaComprada']): ?>
                <p class="mb-3"><span class="badge-atencao px-2 py-1 d-inline-flex align-items-center gap-1"><i class="bi bi-check-circle"></i> Comprada</span> — falta gerar e pegar o link de impressão.</p>
                <form method="post">
                    <input type="hidden" name="action" value="etiqueta_gerar_imprimir">
                    <button type="submit" class="btn btn-marca rounded-pill"><i class="bi bi-file-earmark-arrow-down"></i> Gerar etiqueta e link de impressão</button>
                </form>
            <?php elseif ($pedido['MelhorEnvioOrderId']): ?>
                <p class="mb-3"><span class="badge-atencao px-2 py-1 d-inline-flex align-items-center gap-1"><i class="bi bi-cart"></i> No carrinho do Melhor Envio</span> — falta comprar de verdade.</p>
                <form method="post" data-confirmar="Comprar essa etiqueta agora vai descontar <?= formatarPreco($pedido['ValorFrete']) ?> do saldo da sua conta no Melhor Envio. Confirma?">
                    <input type="hidden" name="action" value="etiqueta_comprar">
                    <button type="submit" class="btn btn-marca rounded-pill"><i class="bi bi-wallet2"></i> Comprar etiqueta (<?= formatarPreco($pedido['ValorFrete']) ?>)</button>
                </form>
            <?php else: ?>
                <p class="text-secundario small mb-3">Ainda não iniciado. Primeiro passo é só reservar no Melhor Envio — não custa nada ainda.</p>
                <form method="post">
                    <input type="hidden" name="action" value="etiqueta_adicionar_carrinho">
                    <button type="submit" class="btn btn-outline-secondary rounded-pill"><i class="bi bi-cart-plus"></i> Adicionar ao carrinho do Melhor Envio</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card p-4">
            <h3 class="h6 text-secundario mb-2">Histórico</h3>
            <ul class="list-unstyled mb-0">
                <?php foreach (array_reverse($historico) as $h): $infoH = statusPedidoInfo($h['StatusNovo']); ?>
                    <li class="d-flex justify-content-between gap-2 py-2 border-bottom small">
                        <span><i class="bi <?= $infoH['icone'] ?>"></i> <?= $infoH['label'] ?></span>
                        <span class="text-secundario text-nowrap"><?= date('d/m/Y H:i', strtotime($h['MomentoMudanca'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../_rodape.php'; ?>
