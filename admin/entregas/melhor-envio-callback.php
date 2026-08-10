<?php
session_start();
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../config/funcoes.php';
require_once __DIR__ . '/../../config/marca.php';
require_once __DIR__ . '/../../config/chaves.php';
exigirLoginAdmin();
garantirTabelaConfiguracaoSistema();

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$stateEsperado = $_SESSION['melhor_envio_oauth_state'] ?? '';
unset($_SESSION['melhor_envio_oauth_state']);

$sucesso = false;
if ($code !== '' && $state !== '' && hash_equals($stateEsperado, $state)) {
    $sucesso = melhorEnvioTrocarCodigoPorToken($code);
}

header('Location: ' . URL_BASE . '/admin/entregas/index.php' . ($sucesso ? '?ok=1' : '?erro=1'));
exit;
