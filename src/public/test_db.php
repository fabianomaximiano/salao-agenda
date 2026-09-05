<?php
$host = getenv('DB_HOST') ?: 'mysql';
$db   = getenv('DB_NAME') ?: 'salao_agenda';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'secret';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>Sucesso!</h1>";
    echo "<p>A conexão com o banco de dados MySQL foi estabelecida com sucesso através do Docker!</p>";
    
    // Testa listando as tabelas criadas
    $stmt = $pdo->query("SHOW TABLES");
    $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Tabelas encontradas no banco <code>$db</code>:</h3>";
    echo "<ul>";
    foreach ($tabelas as $tabela) {
        echo "<li>" . htmlspecialchars($tabela) . "</li>";
    }
    echo "</ul>";

} catch (PDOException $e) {
    echo "<h1>Erro na Conexão:</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}