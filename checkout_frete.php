<?php
// Endpoint AJAX (JSON) chamado pelo JS do checkout ao clicar "Calcular frete" — nunca roda sozinho
// no carregamento da página, só quando o cliente pede explicitamente. Mantém a chamada real ao
// Melhor Envio fora do caminho de toda página do checkout (cupom, reload etc.).
session_start();
require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/config/funcoes.php';
require_once __DIR__ . '/config/marca.php';
require_once __DIR__ . '/config/chaves.php';

header('Content-Type: application/json; charset=utf-8');

if (!clienteLogado() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'erro' => 'Não autorizado.']);
    exit;
}

garantirTabelaEndereco();
garantirTabelaProduto();
garantirTabelaVariacaoProduto();
garantirTabelaImagemProduto();
garantirTabelaItemCarrinho();

$itens = obterCarrinho();
if (!$itens) {
    echo json_encode(['sucesso' => false, 'erro' => 'Carrinho vazio.']);
    exit;
}

$enderecos = obterEnderecosPorUsuario($_SESSION['usuario_id']);
$enderecoResolvido = resolverEndereco($enderecos, $_POST['endereco_id'] ?? '', $_POST);
if ($enderecoResolvido === null) {
    echo json_encode(['sucesso' => false, 'erro' => 'Endereço inválido — confira CEP, logradouro, número e cidade.']);
    exit;
}

$subtotal = array_sum(array_column($itens, 'subtotal'));

if ($subtotal >= FRETE_GRATIS_ACIMA_DE) {
    echo json_encode(['sucesso' => true, 'gratis' => true, 'opcoes' => [], 'fallback' => false, 'valor_fallback' => null]);
    exit;
}

$opcoes = obterOpcoesFrete($enderecoResolvido['cep'], $itens);
if ($opcoes) {
    echo json_encode(['sucesso' => true, 'gratis' => false, 'opcoes' => $opcoes, 'fallback' => false, 'valor_fallback' => null]);
    exit;
}

// Melhor Envio desconectado, API fora do ar, ou produto sem CaixaEnvio — cai no fixo.
$freteFixo = calcularFrete($enderecoResolvido['cep'], $subtotal);
echo json_encode(['sucesso' => true, 'gratis' => false, 'opcoes' => [], 'fallback' => true, 'valor_fallback' => $freteFixo]);
