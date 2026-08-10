<?php
// Diagnóstico cru — investiga por que "Calcular frete" / "Aplicar" cupom parecem não fazer nada em
// produção. Simula a MESMA requisição que o navegador faz (cURL pro próprio domínio, mesmos
// cookies de sessão), não só chama a função PHP direto — problema pode estar em qualquer camada:
// WAF bloqueando POST, resposta que não é JSON de verdade, timeout, cookie não indo junto, etc.
// Acesso: /checkout_diagnostico.php (exige estar logado, cliente ou admin).
session_start();
require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/config/funcoes.php';
require_once __DIR__ . '/config/marca.php';
require_once __DIR__ . '/config/chaves.php';

header('Content-Type: text/plain; charset=utf-8');

if (empty($_SESSION['usuario_id'])) {
    echo "Não logado — faça login (cliente ou admin) e acesse essa página de novo.\n";
    exit;
}

$inicioGeral = microtime(true);
function tempo($desde) {
    return round((microtime(true) - $desde) * 1000) . 'ms';
}

echo "======================================================\n";
echo "DIAGNÓSTICO CHECKOUT — " . date('d/m/Y H:i:s') . "\n";
echo "======================================================\n\n";

echo "=== TESTE 1: ambiente ===\n";
echo "PHP: " . PHP_VERSION . " (SAPI: " . php_sapi_name() . ")\n";
echo "Sessão: " . session_id() . " (status: " . session_status() . ")\n";
echo "Usuário logado: " . ($_SESSION['usuario_nome'] ?? '?') . ' (' . ($_SESSION['usuario_tipo'] ?? '?') . ", id " . $_SESSION['usuario_id'] . ")\n";
echo "URL_BASE resolvida: '" . URL_BASE . "'\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? '?') . "\n";
echo "HTTPS: " . (!empty($_SERVER['HTTPS']) ? 'sim' : (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'sim (via proxy)' : 'não')) . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? '?') . "\n";
echo "SERVER_SOFTWARE: " . ($_SERVER['SERVER_SOFTWARE'] ?? '?') . "\n";
echo "\n";

echo "=== TESTE 2: arquivos existem no servidor? ===\n";
foreach (['checkout.php', 'checkout_frete.php', 'config/chaves.php'] as $arq) {
    $caminho = __DIR__ . '/' . $arq;
    echo "$arq: " . (file_exists($caminho) ? 'existe (' . filesize($caminho) . ' bytes, modificado ' . date('d/m/Y H:i', filemtime($caminho)) . ')' : 'NÃO EXISTE') . "\n";
}
echo "\n";

echo "=== TESTE 3: carrinho ===\n";
garantirTabelaItemCarrinho();
garantirTabelaProduto();
garantirTabelaVariacaoProduto();
garantirTabelaImagemProduto();
$itens = obterCarrinho();
echo count($itens) . " item(ns) no carrinho\n";
if (!$itens) {
    echo "CARRINHO VAZIO — adicione algo antes de testar, o frete depende do carrinho.\n";
}
$subtotal = array_sum(array_column($itens, 'subtotal'));
echo "Subtotal: " . $subtotal . "\n\n";

echo "=== TESTE 4: endereços salvos ===\n";
garantirTabelaEndereco();
$enderecos = obterEnderecosPorUsuario($_SESSION['usuario_id']);
echo count($enderecos) . " endereço(s) salvo(s)\n";
foreach ($enderecos as $e) {
    echo "- [{$e['IDEndereco']}] {$e['Logradouro']}, {$e['Numero']} — {$e['Cidade']}/{$e['UF']} — CEP {$e['CEP']}\n";
}
$enderecoTeste = $enderecos[0] ?? null;
echo "\n";

// Monta os mesmos dados que o JS do checkout manda — endereço salvo se tiver, senão um CEP de teste fixo.
if ($enderecoTeste) {
    $dadosPost = ['endereco_id' => $enderecoTeste['IDEndereco']];
    echo "Vou testar com o endereço salvo: {$enderecoTeste['Logradouro']}, CEP {$enderecoTeste['CEP']}\n\n";
} else {
    $dadosPost = [
        'endereco_id' => 'novo',
        'cep' => '01310-100',
        'logradouro' => 'Avenida Paulista',
        'numero' => '1000',
        'complemento' => '',
        'bairro' => 'Bela Vista',
        'cidade' => 'São Paulo',
        'uf' => 'SP',
    ];
    echo "Sem endereço salvo — vou testar com um CEP fixo de exemplo (01310-100, Av. Paulista).\n\n";
}

echo "=== TESTE 5: obterOpcoesFrete() chamada DIRETO (sem passar pelo servidor web) ===\n";
echo "Isola se o problema é na lógica de cotação em si ou em outra camada (rede, servidor).\n";
if ($itens) {
    $enderecoResolvido = resolverEndereco($enderecos, $dadosPost['endereco_id'], $dadosPost);
    if ($enderecoResolvido === null) {
        echo "resolverEndereco() retornou null — dados de endereço considerados inválidos.\n";
    } else {
        echo "Endereço resolvido: CEP {$enderecoResolvido['cep']}\n";
        $t0 = microtime(true);
        $opcoes = obterOpcoesFrete($enderecoResolvido['cep'], $itens);
        echo "Tempo: " . tempo($t0) . "\n";
        if ($opcoes === null) {
            echo "RETORNOU NULL — ver TESTE 8 (log de erro) pra saber o motivo exato (sem caixa? token? API fora?).\n";
        } else {
            echo count($opcoes) . " opção(ões):\n";
            foreach ($opcoes as $op) {
                echo "  - {$op['transportadora']} {$op['servico']}: R$ {$op['preco']} em {$op['prazo_dias']}d\n";
            }
        }
    }
} else {
    echo "PULADO — carrinho vazio.\n";
}
echo "\n";

echo "=== TESTE 6: POST real pro checkout_frete.php, passando pelo servidor web de verdade ===\n";
echo "Esse é o teste que mais importa — reproduz exatamente o que o fetch() do navegador faz,\n";
echo "incluindo qualquer bloqueio de WAF/proxy/firewall que só acontece nessa camada.\n";

// Sessão tem lock de arquivo — precisa liberar antes de fazer uma requisição HTTP pra essa MESMA
// sessão, senão a requisição interna trava esperando o lock que essa própria página ainda seguraria.
session_write_close();

$protocolo = (!empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
$urlBaseAbsoluta = $protocolo . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . URL_BASE;
$urlFrete = $urlBaseAbsoluta . '/checkout_frete.php';
echo "URL de destino: $urlFrete\n";
echo "Dados enviados: " . json_encode($dadosPost, JSON_UNESCAPED_UNICODE) . "\n";
echo "Cookie de sessão enviado: " . session_name() . "=" . substr(session_id(), 0, 8) . "...\n\n";

function chamarComoNavegador($url, $dados, $cookieNome, $cookieValor) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($dados),
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
            'X-Requested-With: XMLHttpRequest',
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_COOKIE => $cookieNome . '=' . $cookieValor,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HEADER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $t0 = microtime(true);
    $resposta = curl_exec($ch);
    $duracao = tempo($t0);
    $info = curl_getinfo($ch);
    $erro = curl_error($ch);
    curl_close($ch);
    return compact('resposta', 'duracao', 'info', 'erro');
}

$r = chamarComoNavegador($urlFrete, $dadosPost, session_name(), session_id());

echo "Tempo total: {$r['duracao']}\n";
echo "Erro de cURL: " . ($r['erro'] !== '' ? $r['erro'] : '(nenhum)') . "\n";
echo "HTTP status: " . $r['info']['http_code'] . "\n";
echo "Content-Type da resposta: " . ($r['info']['content_type'] ?? '?') . "\n";
echo "Redirecionou? " . ($r['info']['redirect_url'] ? 'sim, pra: ' . $r['info']['redirect_url'] : 'não') . "\n";

if ($r['resposta'] !== false) {
    $tamanhoHeader = $r['info']['header_size'];
    $headers = substr($r['resposta'], 0, $tamanhoHeader);
    $corpo = substr($r['resposta'], $tamanhoHeader);

    echo "\n--- Headers de resposta ---\n" . trim($headers) . "\n";
    echo "\n--- Corpo bruto da resposta (" . strlen($corpo) . " bytes) ---\n";
    echo $corpo . "\n";

    $json = json_decode($corpo, true);
    echo "\n--- É JSON válido? ---\n";
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "SIM — " . json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "NÃO — erro: " . json_last_error_msg() . "\n";
        echo "Isso explica o navegador não mostrar nada: o JS espera JSON e recebeu outra coisa\n";
        echo "(página de erro do PHP, página de bloqueio de firewall/WAF, redirect pra login, etc.)\n";
    }
} else {
    echo "SEM RESPOSTA NENHUMA — a conexão falhou antes de completar (timeout, DNS, TLS, firewall\n";
    echo "bloqueando a porta, etc.). Ver 'Erro de cURL' acima.\n";
}
echo "\n";

echo "=== TESTE 7: POST real pro checkout.php (simula clicar em 'Aplicar' do cupom) ===\n";
$rCupom = chamarComoNavegador($urlBaseAbsoluta . '/checkout.php', array_merge($dadosPost, ['action' => 'recalcular', 'cupom' => '']), session_name(), session_id());
echo "Tempo total: {$rCupom['duracao']}\n";
echo "Erro de cURL: " . ($rCupom['erro'] !== '' ? $rCupom['erro'] : '(nenhum)') . "\n";
echo "HTTP status: " . $rCupom['info']['http_code'] . "\n";
echo "Tamanho da resposta: " . strlen($rCupom['resposta'] ?? '') . " bytes\n";
if ($rCupom['resposta'] !== false) {
    $corpoCupom = substr($rCupom['resposta'], $rCupom['info']['header_size']);
    echo "Contém 'Finalizar compra' (título da página)? " . (str_contains($corpoCupom, 'Finalizar compra') ? 'sim, página normal renderizou' : 'NÃO — algo interrompeu o render') . "\n";
    echo "Contém erro fatal do PHP? " . (preg_match('/Fatal error|Uncaught|Parse error/i', $corpoCupom) ? 'SIM — tem erro fatal no meio da página' : 'não') . "\n";
}
echo "\n";

echo "=== TESTE 8: mensagens de erro registradas (error_log do PHP) ===\n";
$logPhp = ini_get('error_log');
echo "Caminho configurado do error_log: " . ($logPhp ?: '(não configurado / vai pro log padrão do servidor, não acessível aqui)') . "\n";
if ($logPhp && file_exists($logPhp) && is_readable($logPhp)) {
    $linhas = file($logPhp);
    $ultimas = array_slice($linhas, -30);
    echo "Últimas 30 linhas:\n" . implode('', $ultimas) . "\n";
} else {
    echo "Não consegui ler o arquivo direto — normal em muita hospedagem compartilhada (log fica\n";
    echo "fora do alcance do PHP). Se precisar, veja o log de erro pelo painel do cPanel.\n";
}
echo "\n";

echo "=== TESTE 9: token Melhor Envio ===\n";
echo "Conectado: " . (melhorEnvioConectado() ? 'sim' : 'não') . "\n";
$expira = melhorEnvioTokenExpiraEm();
echo "Expira em: " . ($expira ? date('d/m/Y H:i', $expira) : '?') . "\n";
echo "\n";

echo "======================================================\n";
echo "FIM — tempo total do diagnóstico: " . tempo($inicioGeral) . "\n";
echo "======================================================\n";
