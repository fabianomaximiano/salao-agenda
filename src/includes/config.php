<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Carrega as variáveis do .env localizado na raiz do projeto (/var/www/html/.env)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();

// Configuração centralizada do Banco de Dados via .env
$host = $_ENV['DB_HOST'] ?? 'mysql';
$db   = $_ENV['DB_NAME'] ?? 'salao_agenda';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? 'secret';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (\PDOException $e) {
    die("Erro de conexão com o banco de dados: " . $e->getMessage());
}