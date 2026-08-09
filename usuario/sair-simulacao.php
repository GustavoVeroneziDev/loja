<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';

// Não exige login admin — durante a simulação a sessão VIRA o cliente de teste (TipoUsuario
// 'cliente'), então exigirLoginAdmin() bloquearia essa própria tela de sair. A prova de que isso
// é legítimo (e não qualquer cliente tentando virar admin) é ter simulacao_admin_id na sessão,
// que só existe se admin/simulacao.php colocou ali antes.
if (empty($_SESSION['simulacao_admin_id'])) {
    header('Location: ' . URL_BASE . '/index.php');
    exit;
}

$_SESSION['usuario_id'] = $_SESSION['simulacao_admin_id'];
$_SESSION['usuario_nome'] = $_SESSION['simulacao_admin_nome'];
$_SESSION['usuario_tipo'] = 'admin';
unset($_SESSION['simulacao_admin_id'], $_SESSION['simulacao_admin_nome']);

header('Location: ' . URL_BASE . '/admin/simulacao.php');
exit;
