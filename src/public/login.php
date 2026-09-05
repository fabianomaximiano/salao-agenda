<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login - Salão Agenda</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-light d-flex align-items-center justify-content-center vh-100">

    <div class="card shadow p-4 text-center" style="width: 400px;">

        <h3 class="mb-3">Painel do Salão</h3>

        <p class="text-muted">
            Faça login com sua conta Google para continuar.
        </p>

        <a
            href="callback.php?action=auth"
            class="btn btn-outline-dark d-flex align-items-center justify-content-center gap-2"
        >
            <img
                src="https://www.svgrepo.com/show/475656/google-color.svg"
                width="20"
                alt="Google"
            >
            Entrar com Google
        </a>

    </div>

</body>
</html>