<?php
// Arquivo de teste cru, sem estilo — só pra ver se o Melhor Envio está enviando/recebendo certo.
// Acesso: admin/entregas/teste-frete.php (exige login de admin, usa o token de produção de verdade).
// Pode passar ?cep_destino=00000000 na URL pra testar outro destino.
session_start();
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../config/funcoes.php';
require_once __DIR__ . '/../../config/marca.php';
require_once __DIR__ . '/../../config/chaves.php';
exigirLoginAdmin();
garantirTabelaProduto();
garantirTabelaCaixaEnvio();
garantirTabelaUsuario();
garantirTabelaConfiguracaoSistema();

header('Content-Type: text/plain; charset=utf-8');

$cepDestino = preg_replace('/\D/', '', $_GET['cep_destino'] ?? '01310100');
$configEnvio = obterConfigEnvio();

echo "=== TESTE 1: config carregada ===\n";
echo "MELHOR_ENVIO_AMBIENTE: " . (defined('MELHOR_ENVIO_AMBIENTE') ? MELHOR_ENVIO_AMBIENTE : 'NAO DEFINIDA') . "\n";
echo "MELHOR_ENVIO_TOKEN: " . (defined('MELHOR_ENVIO_TOKEN') && MELHOR_ENVIO_TOKEN !== '' ? substr(MELHOR_ENVIO_TOKEN, 0, 20) . '... (' . strlen(MELHOR_ENVIO_TOKEN) . ' chars)' : 'VAZIO') . "\n";
echo "CEP de origem (banco, Admin > Entregas): " . ($configEnvio['cep_origem'] !== '' ? $configEnvio['cep_origem'] : 'NAO CONFIGURADO') . "\n";
echo "Remetente completo? " . (configEnvioCompleta($configEnvio) ? 'sim' : 'NAO — falta preencher em Admin > Entregas') . "\n";
echo "\n";

echo "=== TESTE 2: melhorEnvioConectado() ===\n";
echo melhorEnvioConectado() ? "SIM, token configurado\n" : "NAO, token vazio\n";
echo "\n";

echo "=== TESTE 3: validade do token (lida do próprio JWT) ===\n";
$expiraEm = melhorEnvioTokenExpiraEm();
if ($expiraEm) {
    echo "Expira em: " . date('d/m/Y H:i', $expiraEm) . "\n";
    echo $expiraEm < time() ? "STATUS: EXPIRADO\n" : "STATUS: válido\n";
} else {
    echo "Não deu pra ler a expiração (token vazio ou malformado)\n";
}
echo "\n";

echo "=== TESTE 4: quantos produtos têm caixa de envio atribuída ===\n";
global $pdo;
$totalProdutos = (int) $pdo->query("SELECT COUNT(*) FROM Produto")->fetchColumn();
$comCaixa = (int) $pdo->query("SELECT COUNT(*) FROM Produto WHERE FKCaixaEnvio IS NOT NULL")->fetchColumn();
echo "$comCaixa de $totalProdutos produtos com caixa definida\n";
if ($comCaixa < $totalProdutos) {
    echo "ATENÇÃO: produto sem caixa faz a cotação do carrinho inteiro cair no frete fixo.\n";
    echo "Sem caixa:\n";
    $semCaixa = $pdo->query("SELECT IDProduto, Nome FROM Produto WHERE FKCaixaEnvio IS NULL")->fetchAll();
    foreach ($semCaixa as $p) {
        echo "- [{$p['IDProduto']}] {$p['Nome']}\n";
    }
}
echo "\n";

echo "=== TESTE 5: chamada crua na API de cotação (POST /api/v2/me/shipment/calculate) ===\n";
echo "De (origem): " . $configEnvio['cep_origem'] . "\n";
echo "Para (destino): $cepDestino  [troque com ?cep_destino=00000000 na URL]\n";
echo "URL base: " . melhorEnvioUrlBase() . "\n\n";

$inicio = microtime(true);
$dados = _melhorEnvioRequisicao('POST', melhorEnvioUrlBase() . '/api/v2/me/shipment/calculate', [
    'from' => ['postal_code' => $configEnvio['cep_origem']],
    'to' => ['postal_code' => $cepDestino],
    'products' => [[
        'id' => 'teste-1',
        'width' => 15,
        'height' => 10,
        'length' => 20,
        'weight' => 0.3,
        'insurance_value' => 100,
        'quantity' => 1,
    ]],
], MELHOR_ENVIO_TOKEN);
$duracaoMs = round((microtime(true) - $inicio) * 1000);

echo "Tempo de resposta: {$duracaoMs}ms\n\n";

if (!is_array($dados)) {
    echo "FALHOU — _melhorEnvioRequisicao() retornou null. Ver detalhe no error_log do PHP (geralmente logs/error_log ou php_error_log no XAMPP).\n";
} else {
    echo "Resposta bruta da API:\n";
    echo json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    echo "--- Resumido ---\n";
    foreach ($dados as $op) {
        $nome = ($op['company']['name'] ?? '?') . ' ' . ($op['name'] ?? '?');
        if (!empty($op['error'])) {
            echo "[ERRO] $nome: {$op['error']}\n";
        } else {
            echo "[OK]   $nome: R$ " . ($op['custom_price'] ?? $op['price'] ?? '?') . " em " . ($op['custom_delivery_time'] ?? $op['delivery_time'] ?? '?') . " dia(s)\n";
        }
    }
}
echo "\n";

echo "=== TESTE 6: obterOpcoesFrete() com um item fake (função real usada no checkout) ===\n";
if ($comCaixa === 0) {
    echo "PULADO — nenhum produto tem caixa ainda, obterOpcoesFrete() vai abortar de propósito.\n";
} else {
    $produtoComCaixa = $pdo->query("SELECT p.IDProduto, v.IDVariacao, v.Preco FROM Produto p
        JOIN VariacaoProduto v ON v.FKProduto = p.IDProduto
        WHERE p.FKCaixaEnvio IS NOT NULL LIMIT 1")->fetch();
    if ($produtoComCaixa) {
        $itemFake = [[
            'IDVariacao' => $produtoComCaixa['IDVariacao'],
            'Quantidade' => 1,
            'variacao' => ['IDProduto' => $produtoComCaixa['IDProduto'], 'Preco' => (float) $produtoComCaixa['Preco']],
            'subtotal' => (float) $produtoComCaixa['Preco'],
        ]];
        $opcoes = obterOpcoesFrete($cepDestino, $itemFake);
        if ($opcoes === null) {
            echo "obterOpcoesFrete() retornou null (checar error_log).\n";
        } else {
            echo count($opcoes) . " opção(ões) retornada(s):\n";
            foreach ($opcoes as $op) {
                echo "- {$op['transportadora']} {$op['servico']}: R$ {$op['preco']} em {$op['prazo_dias']} dia(s)\n";
            }
        }
    } else {
        echo "Não achei variação pra testar.\n";
    }
}
