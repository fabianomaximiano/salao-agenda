<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Pegando as credenciais do .env ou ambiente do Docker
$clientId     = getenv('GOOGLE_CLIENT_ID');
$clientSecret = getenv('GOOGLE_CLIENT_SECRET');
$redirectUri  = getenv('GOOGLE_REDIRECT_URI') ?: 'http://localhost:8080/callback.php';

$action = $_GET['action'] ?? '';

// 1. Se clicou em entrar, redireciona para o Google OAuth
if ($action === 'auth') {
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id'     => $clientId,
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'access_type'   => 'online',
        'prompt'        => 'select_account'
    ]);
    header('Location: ' . $authUrl);
    exit;
}

// 2. Se o Google redirecionou de volta com o 'code'
if (isset($_GET['code'])) {
    $code = $_GET['code'];

    // Troca o código temporário por um Token de Acesso
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'code'          => $code,
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri'  => $redirectUri,
        'grant_type'    => 'authorization_code'
    ]));
    
    $response = curl_exec($ch);
    curl_close($ch);
    $tokenData = json_decode($response, true);

    if (isset($tokenData['access_token'])) {
        // Puxa as informações do perfil do usuário no Google
        $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenData['access_token']]);
        
        $userInfoResponse = curl_exec($ch);
        curl_close($ch);
        $userInfo = json_decode($userInfoResponse, true);

        if (isset($userInfo['email'])) {
            $email = $userInfo['email'];
            $nome  = $userInfo['name'] ?? 'Usuário Google';

            $pdo = getDB();

            // Verifica se o usuário já existe na tabela
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                // Se não existe, cadastra automaticamente como cliente
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, tipo) VALUES (?, ?, 'cliente')");
                $stmt->execute([$nome, $email]);
                $userId = $pdo->lastInsertId();
                
                $user = [
                    'id' => $userId,
                    'nome' => $nome,
                    'email' => $email,
                    'tipo' => 'cliente'
                ];
            }

            // Salva os dados na Sessão do PHP
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nome'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_tipo'] = $user['tipo'];

            // Redireciona para o painel principal
            header('Location: dashboard.php');
            exit;
        }
    }

    // Se algo falhar
    header('Location: login.php?erro=google');
    exit;

}