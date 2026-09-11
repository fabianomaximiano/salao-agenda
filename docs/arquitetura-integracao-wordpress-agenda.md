# Arquitetura — Integração WordPress + Salão Agenda

## 1. Objetivo

Este documento define a arquitetura conceitual da integração entre o site
WordPress de cada empresa e o sistema Salão Agenda.

A separação principal é:

- **WordPress:** presença digital da empresa;
- **Salão Agenda:** operação e agendamento da empresa.

Os dois sistemas pertencem à mesma solução, mas possuem responsabilidades
diferentes e se comunicam através de API.

---

## 2. Visão geral

Cada empresa possuirá um site WordPress e uma estrutura correspondente
dentro do Salão Agenda.

Exemplo:

```text
Empresa: Barbearia Zé

Site:
www.josebarber.com.br

Agenda:
agenda.josebarber.com.br

Identificador no Salão Agenda:
empresa_id = 27
```

Estrutura conceitual:

```text
EMPRESA
│
├── SITE WORDPRESS
│   ├── presença digital
│   ├── conteúdo
│   ├── SEO
│   ├── dados institucionais
│   └── identidade visual
│
└── SALÃO AGENDA
    ├── empresa_id
    ├── administrador
    ├── profissionais
    ├── serviços
    ├── horários
    ├── clientes
    └── agendamentos
```

O vínculo entre os dois sistemas será realizado através do identificador
da empresa no Salão Agenda.

---

## 3. Empresa como entidade central

A empresa é a entidade que conecta o site à Agenda.

Exemplo:

```text
Barbearia Zé
│
├── Site
│   └── www.josebarber.com.br
│
├── Agenda
│   └── agenda.josebarber.com.br
│
└── Salão Agenda
    └── empresa_id = 27
```

O `empresa_id` representa a identidade interna da empresa dentro do
Salão Agenda.

O domínio não deve ser utilizado como identidade definitiva da empresa,
pois poderá ser alterado no futuro sem que a empresa deixe de ser a mesma.

---

## 4. Dados institucionais da empresa

Os dados institucionais serão cadastrados uma única vez e reutilizados
em diferentes partes do site.

Exemplos:

- nome fantasia;
- razão social;
- CPF/CNPJ;
- segmento;
- telefone;
- WhatsApp;
- CEP;
- logradouro;
- número;
- complemento;
- bairro;
- cidade;
- estado.

Exemplo:

```text
Nome da empresa:
Barbearia Zé

CNPJ:
00.000.000/0001-00

Telefone:
(11) ...

WhatsApp:
(11) ...

Endereço:
...

Segmento:
Barbearia
```

Essas informações não deverão ser escritas manualmente em diferentes
templates do WordPress.

O cadastro será centralizado e reutilizado pelo tema.

---

## 5. Reutilização dos dados no WordPress

Os dados institucionais poderão alimentar diferentes áreas do site.

Exemplos:

```text
DADOS DA EMPRESA
        │
        ├── Cabeçalho
        ├── Rodapé
        ├── Página de contato
        ├── WhatsApp
        ├── Telefone
        ├── Endereço
        ├── Mapa
        ├── SEO local
        └── Dados estruturados
```

Dessa forma, uma alteração no telefone, endereço ou WhatsApp não deverá
exigir alterações manuais em diferentes partes do site.

O tema deverá obter essas informações a partir do cadastro central da
empresa.

---

## 6. Identidade visual

Além dos dados institucionais, o WordPress poderá centralizar elementos
da identidade visual da empresa.

Exemplos:

- logotipo;
- favicon;
- cor principal;
- cor secundária;
- imagens institucionais.

Esses elementos poderão ser reutilizados pelo próprio site e,
posteriormente, também pelo Salão Agenda.

Isso permitirá que a Agenda tenha a identidade da empresa em vez de
apresentar uma interface completamente genérica ao cliente.

Exemplo:

```text
SITE WORDPRESS
│
├── Barbearia Zé
├── logotipo
├── identidade visual
└── dados institucionais
        │
        ▼
      API
        │
        ▼
SALÃO AGENDA
│
└── experiência personalizada
    para a Barbearia Zé
```

---


## 8. Identidades, papéis e contextos de acesso

A plataforma deve separar claramente identidade de autenticação, contexto administrativo, profissional e consumidor.

Essas entidades não devem ser tratadas como equivalentes, mesmo quando uma mesma pessoa física eventualmente possuir mais de um relacionamento com a plataforma.

### 7.1 Identidade / autenticação

`usuarios` representa exclusivamente a identidade utilizada para acessar o sistema.

Responsabilidades:

- armazenar e-mail e credenciais de autenticação;
- permitir autenticação por senha;
- permitir login com Google de forma opcional;
- identificar quem está autenticado.

A existência de um registro em `usuarios` não determina, por si só, uma função operacional dentro da plataforma.

**Autenticação e função operacional são responsabilidades distintas.**

O identificador utilizado para login também não deve ser confundido com credenciais de integrações externas, como Google Calendar.

### 7.2 Administrador

O administrador representa o responsável pela gestão de um estabelecimento.

Responsabilidades:

- administrar os dados da empresa;
- configurar serviços;
- cadastrar e gerenciar profissionais;
- configurar horários e disponibilidade;
- acompanhar clientes e agendamentos;
- acessar funções administrativas da empresa.

O administrador possui contexto administrativo próprio e sua relação com a empresa deve ser explícita.

O administrador principal não deve ser tratado automaticamente como cliente ou profissional.

A relação administrativa com a empresa deve permanecer isolada das relações de consumo de serviços e de prestação profissional.

O modelo definitivo do vínculo do administrador principal com a empresa será refletido no banco de dados antes da implementação do serviço de criação de empresas.

### 7.3 Profissional

O profissional representa quem executa os serviços oferecidos pelo estabelecimento.

Responsabilidades:

- prestar serviços;
- possuir serviços associados;
- possuir horários de trabalho;
- possuir períodos de bloqueio;
- participar dos agendamentos;
- possuir agenda operacional própria.

Um profissional pode existir sem possuir uma conta de acesso ao sistema.

Caso possua login, sua identidade de autenticação deve permanecer separada do seu cadastro profissional.

O profissional não deve ser tratado como administrador apenas por possuir acesso ao sistema.

A integração com Google Calendar será opcional e independente da autenticação utilizada no Salão Agenda.

### 7.4 Cliente / consumidor

O cliente representa quem consome os serviços oferecidos pelos estabelecimentos.

Um mesmo consumidor pode utilizar serviços de diversas empresas da plataforma.

```text
CLIENTE
│
├── Barbearia A
├── Podologia B
└── Pet Shop C
```

Cada relacionamento deve permanecer isolado dentro do contexto da respectiva empresa. Ao acessar o contexto da Barbearia A, o cliente visualizará somente dados relacionados à Barbearia A.

Históricos, agendamentos, observações e demais informações operacionais de outros estabelecimentos não devem ser misturados.

A interface poderá futuramente permitir a troca de estabelecimento/contexto, de maneira semelhante à troca de contas em outras plataformas, mantendo cada contexto isolado.

### 7.5 Contexto ativo

Toda operação da plataforma deve possuir um contexto claramente definido.

```text
usuario_id = 50
contexto = cliente
empresa_id = 10
```

é diferente de:

```text
usuario_id = 50
contexto = administrador
empresa_id = 3
```

O `empresa_id` do contexto ativo deve ser utilizado para limitar as consultas operacionais.

Nunca deve ser permitido que a simples identificação do usuário resulte em consultas que misturem dados de diferentes estabelecimentos.

Como regra de arquitetura, consultas operacionais devem ser ancoradas no contexto da empresa e utilizar índices adequados por `empresa_id` sempre que aplicável.

### 7.6 Google Calendar e autenticação Google

Login com Google e integração com Google Calendar são responsabilidades diferentes.

**Login com Google:**

- autentica uma identidade no Salão Agenda;
- é opcional;
- não determina qual calendário será utilizado.

**Google Calendar:**

- é uma integração externa opcional;
- pode utilizar uma conta Google diferente do e-mail utilizado no Salão Agenda;
- pode ser conectado individualmente por um profissional;
- não é requisito para utilização da plataforma.

Um profissional sem conta Google deve conseguir utilizar normalmente todos os recursos internos de agenda, disponibilidade, bloqueios e agendamentos.

O Salão Agenda permanece como fonte oficial dos agendamentos.

```text
SALÃO AGENDA
    ↓
fonte oficial dos agendamentos

GOOGLE CALENDAR
    ↓
integração complementar e opcional
```

Credenciais e autorizações de calendário não devem ser armazenadas como se fossem dados de autenticação do usuário.

Essa separação permitirá futuramente integrar outros provedores de calendário, como Microsoft Outlook, sem alterar as entidades principais da plataforma.

### 7.7 Regra conceitual

```text
IDENTIDADE / AUTENTICAÇÃO
        ↓
      usuarios

ADMINISTRADOR
        ↓
gestão da empresa

PROFISSIONAL
        ↓
prestação de serviços e agenda

CLIENTE / CONSUMIDOR
        ↓
consumo de serviços em uma ou mais empresas

INTEGRAÇÕES EXTERNAS
        ↓
Google Calendar / futuros provedores
```

**Administrador, profissional e cliente são contextos funcionais distintos e não devem ser tratados como equivalentes.**

---

## 8. Nascimento da integração

O site e a empresa dentro do Salão Agenda deverão nascer vinculados.

Fluxo conceitual:

```text
Criação do site WordPress
        ↓
Cadastro dos dados da empresa
        ↓
Conexão com o Salão Agenda
        ↓
WordPress envia os dados necessários
        ↓
Salão Agenda cria a empresa
        ↓
Salão Agenda gera empresa_id
        ↓
WordPress recebe empresa_id
        ↓
WordPress armazena o vínculo
        ↓
Site e Agenda passam a representar
a mesma empresa
```

Exemplo:

```text
www.josebarber.com.br
        │
        │ empresa_id = 27
        ▼
Salão Agenda
        │
        └── Barbearia Zé
```

O `empresa_id` não deverá ser um campo digitado manualmente pelo usuário.

Ele será gerado pelo Salão Agenda durante o processo de criação/vinculação
da empresa.

---

## 9. Fluxo de dados entre WordPress e Salão Agenda

A integração possui fluxo de informações nos dois sentidos.

Cada sistema possui responsabilidades diferentes e deve ser considerado
a fonte oficial dos dados pertencentes ao seu domínio.

### 9.1 Dados institucionais

Os dados institucionais da empresa são cadastrados no WordPress e podem
ser reutilizados pelo próprio site e pelo Salão Agenda.

Exemplos:

- nome da empresa;
- razão social;
- CPF/CNPJ;
- telefone;
- WhatsApp;
- endereço;
- segmento;
- logotipo;
- identidade visual.

Fluxo:

```text
WordPress
    ↓
API
    ↓
Salão Agenda
```

Esses dados podem ser utilizados no WordPress em diferentes áreas do site,
como:

- cabeçalho;
- rodapé;
- página de contato;
- botão do WhatsApp;
- mapa;
- informações de endereço;
- SEO local;
- dados estruturados.

Também poderão ser reutilizados pela Agenda para identificar e personalizar
a experiência da empresa.

Exemplo:

```text
Site:
www.josebarber.com.br

Empresa:
Barbearia Zé

Agenda:
agenda.josebarber.com.br
```

### 9.2 Dados operacionais

O Salão Agenda é a fonte oficial dos dados relacionados à operação e ao
agendamento da empresa.

São considerados dados operacionais, entre outros:

- serviços;
- descrição dos serviços;
- valores;
- duração;
- profissionais;
- fotos dos profissionais;
- especialidades;
- vínculos entre profissionais e serviços;
- combos;
- combinações;
- pacotes;
- disponibilidade;
- status ativo/inativo;
- demais recursos relacionados à operação e ao agendamento.

Fluxo:

```text
Salão Agenda
      ↓
     API
      ↓
WordPress
      ↓
Site da empresa
```

O WordPress não deverá possuir um segundo cadastro editável desses dados.

Tudo que for exibido no site relacionado a serviços, profissionais,
combos, combinações ou outros recursos operacionais deverá utilizar os
dados cadastrados no Salão Agenda.

Exemplo:

```text
SALÃO AGENDA

Corte Masculino
├── descrição
├── valor: R$ 60,00
├── duração: 45 minutos
├── status: ativo
└── profissionais
    ├── José
    └── Carlos
```

Essas informações poderão ser utilizadas automaticamente pelo site:

```text
WORDPRESS

Página Serviços

Corte Masculino
├── descrição
├── R$ 60,00
├── 45 minutos
├── José
├── Carlos
└── [ Agendar ]
```

Se o valor for alterado no Salão Agenda:

```text
R$ 60,00
   ↓
R$ 70,00
```

o site deverá passar a apresentar o novo valor sem exigir um segundo
cadastro manual no WordPress.

O mesmo princípio vale para profissionais.

Se um profissional for adicionado, removido ou desativado na Agenda,
essa alteração deverá ser refletida no site.

### 9.3 Combos, combinações e recursos futuros

A mesma regra utilizada para serviços e profissionais será aplicada a
combos, combinações, pacotes e outros recursos operacionais que venham
a existir.

Exemplo:

```text
SALÃO AGENDA

Combo Corte + Barba
├── Corte Masculino
├── Barba
├── duração
├── valor
├── profissionais habilitados
└── status
        │
        ▼
       API
        │
        ▼
WORDPRESS
        │
        ▼
Página / Home / Área de serviços
```

O WordPress apenas apresenta o recurso cadastrado na Agenda.

Não deverá existir outro cadastro manual do mesmo combo no site.

### 9.4 Regra de origem dos dados

A responsabilidade fica dividida da seguinte forma:

```text
WORDPRESS
│
├── dados institucionais
├── informações de contato
├── endereço
├── identidade visual
├── conteúdo institucional
└── SEO
```

```text
SALÃO AGENDA
│
├── serviços
├── preços
├── duração
├── profissionais
├── especialidades
├── vínculos profissionais × serviços
├── combos
├── combinações
├── pacotes
├── disponibilidade
├── clientes
└── agendamentos
```

Princípio arquitetural:

> **Dados institucionais têm o WordPress como origem.**
>
> **Dados operacionais têm o Salão Agenda como origem.**

Não deverá existir cadastro manual duplicado entre os dois sistemas.

---

## 10. Responsabilidades do WordPress

O WordPress será responsável principalmente pela presença digital da
empresa.

Responsabilidades:

- site institucional;
- páginas;
- conteúdo;
- SEO;
- SEO local;
- informações institucionais;
- informações de contato;
- endereço;
- identidade visual;
- apresentação dos dados recebidos do Salão Agenda.

O WordPress poderá apresentar dados operacionais, mas não será a fonte
oficial dessas informações.

Exemplo:

```text
WordPress
│
├── Home
├── Quem Somos
├── Serviços ──────── dados da Agenda
├── Profissionais ─── dados da Agenda
├── Combos ────────── dados da Agenda
├── Contato
└── Agendar ───────── integração com Agenda
```

---

## 11. Responsabilidades do Salão Agenda

O Salão Agenda será responsável pela operação do negócio.

Responsabilidades:

- identificação da empresa através do `empresa_id`;
- administrador da empresa;
- profissionais;
- serviços;
- valores;
- duração dos serviços;
- vínculos entre profissionais e serviços;
- horários;
- disponibilidade;
- bloqueios;
- clientes;
- agendamentos;
- combos;
- combinações;
- pacotes e outros recursos operacionais futuros.

O Salão Agenda será a fonte oficial dessas informações.

---

## 12. Painel administrativo do WordPress

O painel WordPress deverá possuir uma área específica para configuração
da empresa e da integração.

Estrutura conceitual:

```text
WordPress
│
├── Páginas
├── Posts
├── Aparência
│
└── Empresa / Salão Agenda
    │
    ├── Dados da empresa
    ├── Contatos
    ├── Endereço
    ├── Identidade visual
    └── Integração
```

A área de integração poderá apresentar informações como:

```text
Salão Agenda

Status:
Conectado

Empresa:
Barbearia Zé

Empresa ID:
27

Site:
www.josebarber.com.br
```

Os detalhes técnicos definitivos dessa integração serão definidos
posteriormente.

---

## 13. Painel do Salão Agenda

O painel operacional será separado do painel administrativo do WordPress.

Exemplo:

```text
WordPress
www.josebarber.com.br/wp-admin

        ≠

Salão Agenda
agenda.josebarber.com.br
```

O WordPress administra a presença digital.

O Salão Agenda administra a operação.

Estrutura inicial:

```text
SALÃO AGENDA
│
├── Dashboard
├── Profissionais
├── Serviços
├── Horários
├── Clientes
├── Agendamentos
└── Configurações
```

Apesar de serem sistemas separados, ambos estarão vinculados à mesma empresa.

---

## 14. Comunicação entre os sistemas

WordPress e Salão Agenda não deverão acessar diretamente o banco de dados
um do outro.

A comunicação será realizada através da API do Salão Agenda.

```text
WordPress
    ↓
   API
    ↓
Salão Agenda
    ↓
Banco Salão Agenda
```

Da mesma forma, os dados enviados pelo WordPress deverão chegar ao
Salão Agenda através de uma interface controlada da integração.

Não deverá existir:

```text
WordPress
    ↓
acesso direto
    ↓
Banco Salão Agenda
```

O contrato definitivo da API será definido posteriormente.

---

## 15. Identificação da empresa na integração

Toda comunicação entre o site e a Agenda deverá estar vinculada à empresa
correta.

Exemplo:

```text
Barbearia Zé
│
├── www.josebarber.com.br
│
├── agenda.josebarber.com.br
│
└── empresa_id = 27
```

O `empresa_id` é a identidade interna da empresa no Salão Agenda.

O domínio é uma propriedade da empresa, mas não substitui o identificador
interno.

Isso permitirá que o domínio seja alterado futuramente sem alterar os
dados operacionais da empresa.

---

## 16. Cadastro e provisionamento de empresas

O cadastro de empresas não deverá permanecer como uma página pública
aberta em produção.

O processo de criação de uma empresa faz parte do provisionamento da
plataforma.

Fluxo conceitual:

```text
Provisionamento
      ↓
Criação do site
      ↓
Dados da empresa
      ↓
Criação/vinculação no Salão Agenda
      ↓
empresa_id
      ↓
Administrador da empresa
      ↓
Empresa pronta para configuração
```

A arquitetura definitiva do acesso administrativo da plataforma
(MASTER/SUPERADMIN) será definida separadamente.

Não deverá existir cadastro público irrestrito de empresas.

---

## 17. Princípios da integração

A arquitetura deverá seguir os seguintes princípios:

1. Uma empresa possui uma identidade única no Salão Agenda.

2. O site WordPress e a Agenda representam a mesma empresa.

3. Dados institucionais são cadastrados uma única vez.

4. Dados operacionais são cadastrados uma única vez.

5. O WordPress é a fonte dos dados institucionais.

6. O Salão Agenda é a fonte dos dados operacionais.

7. O WordPress pode exibir dados operacionais provenientes da Agenda.

8. A Agenda pode reutilizar dados institucionais provenientes do WordPress.

9. Não deverá existir cadastro manual duplicado entre os sistemas.

10. A comunicação entre WordPress e Salão Agenda será realizada por API.

11. Os bancos de dados não serão compartilhados diretamente.

12. O `empresa_id` será o vínculo interno da empresa com o Salão Agenda.

13. Identidade de autenticação não determina função operacional.

14. Administrador, profissional e cliente são contextos funcionais distintos.

15. Um cliente pode possuir relacionamentos com diversas empresas, sem mistura de históricos ou dados operacionais entre elas.

16. Toda consulta operacional deverá respeitar a empresa do contexto ativo.

17. Login com Google e integração com Google Calendar são responsabilidades distintas.

18. O Salão Agenda é a fonte oficial dos agendamentos; calendários externos são integrações complementares e opcionais.

---

## 18. Questões ainda em aberto

Os seguintes assuntos ainda precisam ser definidos antes de sua
implementação definitiva:

- fluxo técnico de criação da empresa;
- contrato da API;
- autenticação entre WordPress e Salão Agenda;
- credenciais da integração;
- armazenamento seguro das credenciais;
- domínio/subdomínio utilizado pela Agenda;
- sincronização dos dados institucionais;
- atualização dos dados operacionais no WordPress;
- estratégia de cache dos dados da API;
- comportamento do site caso a API esteja temporariamente indisponível;
- área MASTER/SUPERADMIN;
- estrutura definitiva do vínculo exclusivo do administrador principal com a empresa;
- fluxo técnico de ativação e recuperação de acesso administrativo;
- modelo de armazenamento e renovação das autorizações de integrações externas;
- cobrança;
- inadimplência;
- suspensão;
- reativação;
- planos;
- permissões;
- recursos comerciais futuros.

Esses pontos permanecem como decisões arquiteturais pendentes e não devem
ser tratados como funcionalidades já definidas.

---

## 19. Resumo da arquitetura

```text
                    EMPRESA
                       │
          ┌────────────┴────────────┐
          │                         │
          ▼                         ▼
      WORDPRESS                SALÃO AGENDA
          │                         │
          │                         │
 Dados institucionais         Dados operacionais
 Conteúdo                     Serviços
 SEO                          Profissionais
 Identidade visual            Valores
 Contatos                     Horários
 Endereço                     Clientes
                              Agendamentos
                              Combos
          │                         │
          │                         │
          └────────── API ──────────┘
                       │
                       │
                 empresa_id
                       │
                       ▼
                MESMA EMPRESA
```

Regra principal:

> **O WordPress representa a empresa na web.**
>
> **O Salão Agenda administra a operação da empresa.**
>
> **Cada informação possui uma única fonte oficial e pode ser reutilizada
> pelo outro sistema através da integração.**

## Perfil público do profissional

O cadastro de profissionais deverá permitir que os dados profissionais sejam
utilizados tanto no painel administrativo quanto nos canais públicos integrados
à plataforma, incluindo o site da empresa.

### Dados de apresentação

O profissional poderá possuir:

- nome de exibição;
- cargo ou especialidade;
- micro currículo / apresentação profissional;
- foto de perfil;
- indicação se deve aparecer publicamente no site;
- slug público;
- ordem de exibição.

Os dados pessoais e de contato continuam pertencendo ao cadastro de `pessoas`.
Os campos acima representam informações específicas da atuação profissional.

### Foto de perfil

A foto do profissional deverá ser processada automaticamente pela aplicação.

O administrador não será responsável por otimizar manualmente a imagem antes
do envio.

#### Upload

Formatos aceitos:

- JPEG;
- PNG;
- WebP.

Tamanho máximo do arquivo original:

- 8 MB.

Dimensão mínima recomendada:

- 400 × 400 pixels.

A aplicação deverá validar o tipo real do arquivo e confirmar que o conteúdo
pode ser decodificado como uma imagem válida.

A extensão informada pelo usuário não deverá ser considerada suficiente para
validar o arquivo.

#### Processamento

Após o upload, a aplicação deverá:

1. validar a imagem;
2. considerar/corrigir a orientação da imagem quando necessário;
3. realizar recorte quadrado (proporção 1:1);
4. redimensionar a imagem;
5. converter o resultado para WebP;
6. aplicar compressão;
7. gerar nomes de arquivo controlados pela aplicação;
8. armazenar somente as versões necessárias para utilização.

O arquivo original não deverá ser mantido após o processamento, salvo se
surgir futuramente uma necessidade funcional específica.

### Versões da imagem

Inicialmente deverão ser geradas duas versões:

- 320 × 320 px — cards, listagens, agenda e componentes compactos;
- 800 × 800 px — perfil e apresentação detalhada do profissional.

Meta de peso:

- desejável: até 100 KB;
- limite recomendado: 150 KB por imagem processada.

O sistema deverá priorizar qualidade visual adequada sem armazenar imagens
desnecessariamente grandes.

### Armazenamento

As imagens não deverão ser armazenadas como BLOB no banco de dados.

O banco deverá manter somente a referência necessária para localização da
imagem.

Exemplo conceitual:

profissionais/
└── {empresa_id}/
    └── {profissional_id}/
        ├── perfil-320.webp
        └── perfil-800.webp

### Utilização no site

O Salão Agenda será a origem oficial das informações operacionais do
profissional.

A foto, micro currículo, especialidades e demais informações públicas
cadastradas na Agenda poderão ser disponibilizadas ao site por meio da API.

Não deverá existir necessidade de cadastrar novamente a foto ou o perfil
profissional no WordPress.

O site deverá utilizar a versão de imagem apropriada para o contexto de
exibição e aplicar práticas de carregamento adequadas, incluindo imagens
responsivas e lazy loading quando aplicável.

### Segurança

O upload deverá:

- validar MIME real;
- validar se o arquivo é uma imagem decodificável;
- limitar tamanho e dimensões;
- gerar um novo arquivo processado;
- não confiar no nome original do arquivo;
- impedir que o arquivo enviado seja interpretado como código executável.

### Requisitos do CRUD de profissionais

O CRUD deverá contemplar:

- listar profissionais;
- cadastrar profissional;
- editar profissional;
- ativar/desativar profissional;
- foto de perfil otimizada;
- nome de exibição;
- cargo/especialidade;
- micro currículo;
- vinculação com serviços;
- configuração de disponibilidade;
- intervalos;
- bloqueios;
- vínculo opcional com usuário de acesso;
- publicação ou não no site;
- slug público;
- ordem de exibição.