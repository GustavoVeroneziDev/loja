<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
unset($_SESSION['cliente_id'], $_SESSION['cliente_nome']);
header('Location: ' . URL_BASE . '/index.php');
exit;
