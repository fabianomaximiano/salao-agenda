<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel - Salão Agenda</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-5">
    <div class="container">
        <div class="card shadow p-4">
            <h2>Bem-vindo, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h2>
            <p class="text-muted">E-mail: <?= htmlspecialchars($_SESSION['user_email']) ?></p>
            <p>Tipo de Acesso: <span class="badge bg-primary"><?= htmlspecialchars($_SESSION['user_tipo']) ?></span></p>
            <hr>
            <a href="logout.php" class="btn btn-danger w-25">Sair da Conta</a>
        </div>
    </div>
</body>
</html>