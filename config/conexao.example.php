<?php
// Copie este arquivo para conexao.php e preencha com as credenciais reais do banco.
// conexao.php fica fora do git (.gitignore) porque cada cliente/deploy tem seu próprio banco.

date_default_timezone_set('America/Sao_Paulo');

$dbHost = 'localhost';
$dbName = 'loja_base';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    $pdo->exec("SET time_zone = '-03:00'");
} catch (PDOException $e) {
    error_log('Erro ao conectar ao banco de dados: ' . $e->getMessage());
    die('Erro ao conectar ao banco de dados.');
}

// URL_BASE permite que o projeto rode em qualquer subpasta (ex: /loja em dev, raiz do domínio em produção)
// sem precisar trocar link nenhum manualmente quando o clone de um cliente muda de nome/local.
$raizProjeto = str_replace('\\', '/', dirname(__DIR__));
$documentRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
define('URL_BASE', $documentRoot !== '' && str_starts_with($raizProjeto, $documentRoot)
    ? substr($raizProjeto, strlen($documentRoot))
    : '');
