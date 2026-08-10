<?php
session_start();
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../config/funcoes.php';
require_once __DIR__ . '/../../config/chaves.php';
exigirLoginAdmin();

// State aleatório guardado na sessão e conferido de volta no callback — sem isso, qualquer um
// poderia forjar um redirect com um "code" de outra conta pra essa URL de callback (CSRF do OAuth).
$state = bin2hex(random_bytes(16));
$_SESSION['melhor_envio_oauth_state'] = $state;

$url = melhorEnvioUrlBase() . '/oauth/authorize?' . http_build_query([
    'client_id' => MELHOR_ENVIO_CLIENT_ID,
    'redirect_uri' => MELHOR_ENVIO_REDIRECT_URI,
    'response_type' => 'code',
    'state' => $state,
    'scope' => 'shipping-calculate',
]);

header('Location: ' . $url);
exit;
