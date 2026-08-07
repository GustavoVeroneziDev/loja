<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
unset($_SESSION['admin_id'], $_SESSION['admin_nome']);
header('Location: ' . URL_BASE . '/admin/login.php');
exit;
