<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
unset($_SESSION['usuario_id'], $_SESSION['usuario_nome'], $_SESSION['usuario_tipo']);
header('Location: ' . URL_BASE . '/usuario/login.php');
exit;
