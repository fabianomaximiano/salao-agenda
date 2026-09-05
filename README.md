```markdown
# Sistema de Agendamento para Salão (Salão Agenda)

Sistema web leve para gerenciamento de agendamentos e autenticação de usuários via Google OAuth, desenvolvido com PHP nativo, Docker e MySQL, sem dependências pesadas.

## 🚀 Tecnologias Utilizadas
- **PHP 8.2 (FPM)** com cURL e PDO
- **MySQL 8.0** para persistência de dados
- **Nginx** como servidor web
- **Docker & Docker Compose** para containerização do ambiente
- **Google OAuth 2.0** para autenticação de usuários

---

## 📁 Estrutura de Arquivos do Projeto
```text
salao-agenda/
├── docker-compose.yml
├── Dockerfile
├── .env
└── src/
    ├── includes/
    │   └── db.php
    └── public/
        ├── callback.php
        ├── dashboard.php
        └── logout.php

```

---

## ⚙️ Configuração do Ambiente

1. Crie um arquivo chamado `.env` na raiz do projeto com a seguinte estrutura:

```env
GOOGLE_CLIENT_ID=seu_client_id_aqui.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=seu_client_secret_aqui
GOOGLE_REDIRECT_URI=http://localhost:8080/callback.php

DB_HOST=mysql
DB_NAME=salao_agenda
DB_USER=root
DB_PASS=secret

```

2. Suba os containers utilizando o Docker Compose:

```powershell
docker compose down
docker compose up -d --build

```

---

## 🔐 Configuração do Google OAuth 2.0

1. Acesse o [Google Cloud Console](https://console.cloud.google.com/).
2. Crie um projeto e configure a **Tela de consentimento OAuth** como **Externa**.
3. Adicione seu e-mail como **Usuário de teste**.
4. Crie credenciais de **ID do cliente OAuth** para **Aplicativo da Web**.
5. Em **URIs de redirecionamento autorizados**, adicione:
`http://localhost:8080/callback.php`
6. Copie o **Client ID** e o **Client Secret** gerados para o seu arquivo `.env`.

---

## 🌐 Como Acessar

* **Painel / Login com Google:** `http://localhost:8080/callback.php?action=auth`
* **Dashboard:** `http://localhost:8080/dashboard.php`
* **PhpMyAdmin (Banco de Dados):** `http://localhost:8081`

```

```