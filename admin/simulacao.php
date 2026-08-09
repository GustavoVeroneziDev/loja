<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
exigirLoginAdmin();
garantirTabelaUsuario();
garantirTabelaPedido();
garantirTabelaItemPedido();
garantirTabelaHistoricoStatusPedido();
garantirTabelaItemCarrinho();

global $pdo;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $clienteSimulacao = obterOuCriarClienteSimulacao();

    if ($action === 'entrar') {
        // Guarda quem é o admin de verdade pra dar pra voltar depois — a sessão vira o cliente de
        // teste a partir daqui, exigirLoginAdmin() de qualquer outra tela do admin passa a barrar
        // até sair da simulação (é a prova de que virou cliente de verdade, não só "modo visual").
        $_SESSION['simulacao_admin_id'] = $_SESSION['usuario_id'];
        $_SESSION['simulacao_admin_nome'] = $_SESSION['usuario_nome'];
        $_SESSION['usuario_id'] = $clienteSimulacao['IDUsuario'];
        $_SESSION['usuario_nome'] = $clienteSimulacao['Nome'];
        $_SESSION['usuario_tipo'] = 'cliente';
        header('Location: ' . URL_BASE . '/index.php');
        exit;
    }

    if ($action === 'resetar') {
        resetarDadosSimulacao($clienteSimulacao['IDUsuario']);
        header('Location: ' . URL_BASE . '/admin/simulacao.php?ok=1');
        exit;
    }
}

$sucesso = isset($_GET['ok']) ? 'Dados de simulação resetados.' : null;

$clienteSimulacao = obterOuCriarClienteSimulacao();
$stmtPedidos = $pdo->prepare("SELECT COUNT(*) FROM Pedido WHERE FKUsuario = :u");
$stmtPedidos->execute(['u' => $clienteSimulacao['IDUsuario']]);
$totalPedidosSimulacao = (int) $stmtPedidos->fetchColumn();
$stmtCarrinho = $pdo->prepare("SELECT COALESCE(SUM(Quantidade), 0) FROM ItemCarrinho WHERE FKUsuario = :u");
$stmtCarrinho->execute(['u' => $clienteSimulacao['IDUsuario']]);
$totalItensCarrinho = (int) $stmtCarrinho->fetchColumn();

require __DIR__ . '/_topo.php';
?>
<h1 class="h4 mb-4">Simulação</h1>

<?php if ($sucesso): ?><script>document.addEventListener('DOMContentLoaded', function () { mostrarToastSucesso(<?= json_encode($sucesso) ?>); });</script><?php endif; ?>

<div class="card p-4">
    <p>
        Entra no site como um cliente de teste dedicado — dá pra navegar, favoritar, montar
        carrinho e fechar pedido de verdade, exatamente como um cliente real veria. É sempre o
        <strong>mesmo cliente de teste</strong> reaproveitado (não cria um novo a cada vez), e os
        pedidos dele <strong>não entram</strong> na lista de pedidos nem nos números do dashboard —
        ficam isolados, só aparecem aqui.
    </p>
    <p class="text-secundario small">
        Estoque desconta normalmente durante o teste (é o fluxo de verdade) — use "Resetar" quando
        terminar pra cancelar os pedidos de teste e devolver o estoque, deixando tudo limpo pra
        próxima rodada.
    </p>
    <div class="d-flex gap-3 mb-3">
        <span class="badge-atencao px-2 py-1"><i class="bi bi-bag"></i> <?= $totalPedidosSimulacao ?> pedido(s) de teste</span>
        <span class="badge-atencao px-2 py-1"><i class="bi bi-cart"></i> <?= $totalItensCarrinho ?> item(ns) no carrinho de teste</span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <form method="post">
            <input type="hidden" name="action" value="entrar">
            <button type="submit" class="btn btn-marca rounded-pill"><i class="bi bi-play-fill"></i> Entrar em modo simulação</button>
        </form>
        <form method="post" data-confirmar="Cancelar todos os pedidos de teste e esvaziar o carrinho de simulação?">
            <input type="hidden" name="action" value="resetar">
            <button type="submit" class="btn btn-outline-secondary rounded-pill"><i class="bi bi-arrow-counterclockwise"></i> Resetar dados de simulação</button>
        </form>
    </div>
</div>
<?php require __DIR__ . '/_rodape.php'; ?>
