Plataforma de Agendamento e Gestão de Atendimentos

Sistema web independente para agendamento e gestão de atendimentos de
negócios que trabalham com serviços com hora marcada.

O projeto não é restrito a salões de beleza. A arquitetura foi pensada
para atender segmentos como Unhas, Cabelos, Barbearia, Depilação,
Maquiagem, Sobrancelhas, Massagem e Estética, Podologia, Pet Shop e
outros negócios baseados em serviços agendados.

A aplicação é desenvolvida em PHP nativo, MySQL, Nginx e Docker, com
autenticação via Google OAuth 2.0, buscando uma base leve,
independente e evolutiva.

Nota sobre o nome: salao-agenda é atualmente o nome técnico do
repositório. A interface e o produto devem utilizar linguagem neutra,
pois a solução atende segmentos além de salão de beleza.

Objetivo do projeto

A plataforma centraliza a operação de agendamentos do estabelecimento,
permitindo administrar:

empresas/estabelecimentos;

usuários e níveis de acesso;

clientes;

profissionais;

categorias e serviços;

horários de trabalho, intervalos e bloqueios;

disponibilidade;

agendamentos e seus status;

histórico de atendimentos;

indicadores operacionais.

Fluxo principal:

Cliente
   ↓
Serviço
   ↓
Profissional
   ↓
Data
   ↓
Horário disponível
   ↓
Agendamento

O sistema deve nascer preparado para múltiplos estabelecimentos, ainda
que a primeira implantação utilize apenas uma empresa.

Arquitetura: site e sistema independentes

O projeto separa presença digital de operação de agendamento.

Site institucional em WordPress

O estabelecimento poderá possuir um site WordPress responsável por:

apresentação da empresa e do espaço;

páginas de serviços;

apresentação dos profissionais;

conteúdo institucional;

SEO;

campanhas e landing pages;

localização e canais de contato.

Sistema de agendamento

A aplicação salao-agenda é independente do WordPress e será
responsável por:

autenticação;

clientes;

profissionais;

serviços;

disponibilidade;

agenda;

agendamentos;

regras de cancelamento/remarcação;

indicadores e relatórios.

O WordPress não será responsável pela agenda e não compartilhará o
banco de dados da aplicação.

┌──────────────────────────────┐
│          WORDPRESS           │
│ Marketing / SEO / Conteúdo   │
│ Serviços / Profissionais     │
└──────────────┬───────────────┘
               │ API HTTP/JSON
┌──────────────▼───────────────┐
│   SISTEMA DE AGENDAMENTO     │
│ PHP / MySQL / Autenticação   │
│ Agenda / Serviços / Clientes │
│ Profissionais / Regras       │
└──────────────────────────────┘

API

Está prevista uma API própria para permitir que o site institucional e,
futuramente, outras aplicações consumam dados do sistema.

Exemplos de recursos públicos:

GET /api/v1/services
GET /api/v1/professionals
GET /api/v1/categories
GET /api/v1/business

Operações sensíveis deverão exigir autenticação:

GET    /api/v1/appointments
POST   /api/v1/appointments
PATCH  /api/v1/appointments/{id}
GET    /api/v1/clients
GET    /api/v1/dashboard

A API será o contrato entre os sistemas. O sistema de agendamento não
será um plugin WordPress.

Essa separação permitirá que futuramente a mesma plataforma seja
consumida por WordPress, outro site, aplicativo mobile, totem ou outras
integrações.

Usuários e níveis de acesso

O modelo diferencia identidade do usuário, papel de acesso e
relação com o estabelecimento.

Um mesmo usuário poderá possuir mais de um papel. Por exemplo, a
proprietária de um estabelecimento também poderá atuar como
profissional.

O tipo de acesso não precisa ser apresentado explicitamente na interface
do cliente. O painel exibirá apenas as funcionalidades permitidas.

Cliente

Dados previstos:

CPF;

nome completo;

data de nascimento;

gênero: masculino, feminino, não-binário ou não informado;

telefone principal;

indicação de WhatsApp;

telefones adicionais;

e-mail, preferencialmente compatível com autenticação Google.

Funcionalidades:

administrar os próprios dados;

visualizar sua agenda;

realizar agendamentos;

visualizar data, horário, serviço e profissional;

remarcar ou cancelar conforme as regras do estabelecimento;

consultar histórico de atendimentos.

Profissional

Além dos dados pessoais e de acesso:

serviços executados;

especialidades;

horários de trabalho;

intervalos;

bloqueios de agenda;

status ativo/inativo;

duração e preço específicos por serviço, quando aplicável.

Funcionalidades:

visualizar a própria agenda;

consultar próximos atendimentos;

acessar informações necessárias do cliente;

acompanhar o status dos atendimentos;

bloquear períodos quando permitido.

Administrativo

O cadastro da empresa será separado do cadastro das pessoas com
acesso administrativo.

Uma empresa poderá possuir vários usuários administrativos. Futuramente
poderão existir papéis como:

proprietário;

administrador;

gerente;

recepção.

O acesso administrativo deverá permitir:

visualizar agendas dos profissionais;

administrar profissionais e clientes;

administrar categorias e serviços;

administrar horários e disponibilidade;

acompanhar agendamentos;

acompanhar cancelamentos, faltas e atendimentos concluídos;

consultar indicadores.

Indicadores previstos incluem agendamentos por dia, mês, trimestre,
semestre e ano, serviços mais agendados, profissionais com mais
atendimentos, cancelamentos, faltas, clientes recorrentes e,
futuramente, faturamento.

No MVP, a prioridade será a operação da agenda e os indicadores
essenciais de hoje e do mês atual.

Estrutura multiempresa

O estabelecimento é uma entidade independente do usuário.

EMPRESA
│
├── usuários / papéis
├── clientes
├── profissionais
├── categorias
├── serviços
└── agendamentos

As entidades operacionais deverão ser associadas à empresa
correspondente, normalmente por empresa_id.

Isso permitirá:

Empresa A
├── profissionais
├── serviços
└── agendamentos

Empresa B
├── profissionais
├── serviços
└── agendamentos

Cada estabelecimento deverá acessar somente seus próprios dados.

Modelo de dados planejado

O banco atual representa a primeira versão do protótipo e será evoluído
gradualmente.

empresas
│
├── usuarios
│   ├── telefones_usuario
│   └── usuario_papeis
│
├── clientes
│
├── profissionais
│   ├── profissional_servicos
│   ├── profissional_horarios
│   └── profissional_bloqueios
│
├── categorias_servicos
│   └── servicos
│
└── agendamentos
    ├── agendamento_servicos
    └── agendamento_historico

Usuários e papéis

Os dados pessoais e de autenticação pertencem ao usuário. Os papéis
determinam o que ele pode fazer em cada estabelecimento.

Papéis iniciais:

cliente
profissional
administrador

O objetivo é evitar um tipo rígido de usuário e permitir combinações
como administrador + profissional.

Clientes e profissionais

clientes e profissionais representam relações do usuário com
determinada empresa. A mesma pessoa poderá, por exemplo, ser
profissional em um estabelecimento e cliente em outro.

Serviços

Os serviços serão organizados por categorias e poderão possuir nome,
descrição, duração, intervalo após atendimento, preço, status e
disponibilidade para agendamento online.

profissional_servicos determinará quais profissionais executam cada
serviço e poderá sobrescrever duração ou preço quando necessário.

Disponibilidade

A disponibilidade será calculada a partir de:

horários recorrentes de trabalho;

intervalos;

bloqueios;

agendamentos existentes.

Agendamentos

Um agendamento deverá registrar empresa, cliente, início, fim, status,
origem, observações, valor total, serviços, profissional responsável e
histórico de alterações.

Status iniciais previstos:

pendente
confirmado
em_atendimento
concluido
cancelado
nao_compareceu

Um agendamento poderá conter mais de um serviço, permitindo combinações
como:

Corte + barba
Manicure + pedicure
Banho + tosa

Por isso os serviços serão relacionados através de
agendamento_servicos, em vez de limitar o agendamento a um único
servico_id.

Também está prevista a manutenção do identificador do evento do Google
Calendar para futuras sincronizações.

Particularidade: Pet Shop

O Pet Shop utilizará o mesmo núcleo da agenda.

O usuário que possui conta e realiza o agendamento é o responsável
humano, enquanto o destinatário do serviço pode ser um pet.

Fabiano (cliente)
    │
    └── Thor (pet)
           │
           └── Banho e Tosa

Está prevista uma extensão pets, relacionada ao cliente e ao
estabelecimento, sem alterar o núcleo utilizado pelos demais segmentos.

Financeiro

O módulo financeiro completo não faz parte da primeira etapa.

Inicialmente, serviços e agendamentos poderão armazenar valores
suficientes para indicadores básicos.

Uma evolução futura poderá incluir:

pagamentos
formas_pagamento
comissoes
despesas
caixa

Tecnologias utilizadas

PHP 8.2 (FPM) com cURL e PDO

MySQL 8.0

Nginx

Docker & Docker Compose

Google OAuth 2.0

API HTTP/JSON planejada

Estrutura atual do projeto

salao-agenda/
├── .env
├── .gitignore
├── docker-compose.yml
├── docker/
│   ├── Dockerfile
│   └── nginx.conf
└── src/
    ├── composer.json
    ├── composer.lock
    ├── includes/
    │   ├── config.php
    │   └── db.php
    └── public/
        ├── callback.php
        ├── dashboard.php
        ├── index.php
        ├── login.php
        ├── logout.php
        └── test_db.php

O diretório vendor/ e o arquivo .env não devem ser versionados.

Configuração do ambiente

Crie um .env na raiz do projeto sem versionar credenciais reais:

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost:8080/callback.php

DB_HOST=mysql
DB_NAME=salao_agenda
DB_USER=root
DB_PASS=

Suba o ambiente:

docker compose up -d

Reconstrua quando necessário:

docker compose up -d --build

Verifique os serviços:

docker compose ps

Encerre o ambiente:

docker compose down

Google OAuth 2.0

A autenticação atual utiliza Google OAuth 2.0.

URI de redirecionamento no ambiente local:

http://localhost:8080/callback.php

Client ID, Client Secret e demais credenciais devem ser informados
somente através do .env.

Nunca envie Client Secret, senhas ou outras credenciais para o Git.

Ambiente local

Aplicação:  http://localhost:8080
phpMyAdmin: http://localhost:8081

Fluxo atual de autenticação Google:

http://localhost:8080/callback.php?action=auth

Direção de desenvolvimento

1. Modelo de dados multiempresa
        ↓
2. Usuários, papéis e permissões
        ↓
3. Empresas e configurações
        ↓
4. Clientes
        ↓
5. Profissionais
        ↓
6. Categorias e serviços
        ↓
7. Disponibilidade e bloqueios
        ↓
8. Agenda e agendamentos
        ↓
9. Dashboard e indicadores
        ↓
10. API pública/autenticada
        ↓
11. Integração com site institucional
        ↓
12. Relatórios e financeiro

A prioridade é construir primeiro um núcleo sólido de empresa →
serviço → profissional → disponibilidade → cliente → agendamento.

Princípios arquiteturais

WordPress é responsável por presença digital, marketing, conteúdo
e SEO.

A aplicação de agenda é responsável pela operação do
estabelecimento.

WordPress e agenda possuem bancos e ciclos de vida
independentes.

A API será o contrato de comunicação entre aplicações.

O sistema de agenda não será um plugin WordPress.

O banco da agenda é a fonte oficial dos dados operacionais.

Usuários podem possuir mais de um papel.

O sistema deve suportar múltiplos estabelecimentos.

Particularidades de segmentos devem estender o núcleo sem
contaminá-lo.

Segurança e isolamento dos dados por empresa são requisitos
estruturais.

Status atual

Já estão funcionando na base inicial:

ambiente Docker;

Nginx + PHP-FPM;

MySQL;

phpMyAdmin;

conexão da aplicação com o banco;

autenticação Google OAuth 2.0;

criação/login inicial de usuário;

sessão autenticada;

dashboard inicial;

versionamento Git e repositório remoto.

A próxima etapa é consolidar o modelo lógico de dados v2 e evoluir o
banco atual para a estrutura multiempresa descrita neste documento.