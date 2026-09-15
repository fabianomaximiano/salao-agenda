# Salão Agenda --- Checklist Mestre do Projeto

> Documento vivo de acompanhamento do desenvolvimento do **Salão
> Agenda**.
>
> **Princípio:** consolidar fundação, dados, segurança, permissões e
> regras antes da operação completa da agenda.
>
> **Regra de atualização:** marcar como concluído somente o que foi
> implementado e homologado. Itens parcialmente prontos permanecem como
> `[~]`.

## Legenda

-   [x] Concluído / homologado
-   [ ] Pendente
-   \[\~\] Iniciado / precisa revisão ou depende da operação real

------------------------------------------------------------------------

## 1. Fundação técnica e ambiente

-   [x] Docker
-   [x] Nginx
-   [x] PHP 8.2 FPM
-   [x] MySQL 8.0
-   [x] phpMyAdmin
-   [x] Timezone `America/Sao_Paulo`
-   [x] GD com JPEG, PNG e WebP
-   [x] EXIF
-   [x] Estrutura multi-tenant baseada em `empresa_id`
-   [x] `empresa_id` obtido pelo contexto autenticado
-   [ ] Revisar limite global de upload PHP × identidade visual
-   [ ] Revisar `client_max_body_size` do Nginx antes da homologação
    final de uploads
-   [ ] Revisão final para produção/HostGator

------------------------------------------------------------------------

## 2. Autenticação e segurança

### Administrador

-   [x] Login administrativo
-   [x] Sessão administrativa
-   [x] Onboarding com empresa/usuário/admin inicialmente inativos
-   [x] Código de confirmação de 6 dígitos
-   [x] Expiração e limite de tentativas
-   [x] Cooldown e limite de códigos por hora
-   [x] Token forte de ativação
-   [x] Política de senha forte
-   [x] Proteção contra brute force no login

### Profissional

-   [x] Profissional pode existir sem login
-   [x] Liberação de acesso pelo administrador
-   [x] E-mail corporativo
-   [x] Confirmação por código
-   [x] Token de ativação
-   [x] Definição de senha
-   [x] Login profissional
-   [x] Sessão profissional
-   [x] Dashboard profissional

### Colaborador

-   [x] Colaborador pode ser cadastrado antes da liberação de acesso
-   [x] Liberação de acesso pelo administrador
-   [x] Confirmação por código
-   [x] Token de ativação
-   [x] Definição de senha
-   [x] Login pelo fluxo geral
-   [x] Sessão com contexto de colaborador
-   [x] Permissões refletidas no sistema
-   [x] Administração dos próprios dados

### Cliente

-   [x] Cadastro administrativo sem obrigar criação de login
-   [x] Login do cliente
-   [x] Sessão do cliente
-   [x] Dashboard do cliente
-   [x] Administração dos próprios dados

### Senhas

-   [x] Recuperação de senha
-   [x] Resposta neutra
-   [x] Token forte e de uso único
-   [x] Expiração, cooldown e limites
-   [x] Alteração autenticada de senha
-   [x] Proteção independente contra brute force
-   [x] Regeneração da sessão
-   [ ] Invalidação global de sessões antigas

------------------------------------------------------------------------

## 3. Banco de dados e modelo estrutural

> A etapa inicial de levantamento/remodelagem já avançou. O banco possui
> as estruturas necessárias para empresa, pessoas, profissionais,
> clientes, colaboradores, horários, exceções e evolução operacional dos
> serviços agendados. Revisões específicas continuam junto aos módulos
> que ainda serão implementados.

### Estruturas consolidadas

-   [x] Estrutura multi-tenant por `empresa_id`
-   [x] Estrutura de `pessoas`
-   [x] Estrutura de `administradores`
-   [x] Estrutura de `profissionais`
-   [x] Estrutura de `clientes`
-   [x] Estrutura de `colaboradores`
-   [x] Telefones de pessoas
-   [x] Endereço de pessoas
-   [x] Horários da empresa
-   [x] Horários dos profissionais
-   [x] Estrutura de exceções da empresa
-   [x] Estrutura de bloqueios profissionais existente
-   [x] Migrations estruturais executadas e homologadas nas etapas
    concluídas
-   [ ] Atualizar/reconciliar `schema.sql` com o banco atual
-   [ ] Revisar índices adicionais conforme as consultas reais da agenda
    e relatórios

### Identidade, vínculo e autorização

-   [x] Contextos de administrador, profissional, colaborador e cliente
    definidos
-   [x] Vínculo de profissional com usuário opcional
-   [x] Vínculo de cliente com usuário opcional
-   [x] Vínculo de colaborador com usuário opcional até liberação de
    acesso
-   [x] Isolamento por empresa nas áreas homologadas
-   [x] Permissões simples do colaborador modeladas
-   [ ] Revisar estruturas antigas de papéis/perfis somente se houver
    necessidade real
-   [ ] Evolução futura para gerente/supervisor somente se o produto
    exigir

### Atendimento por serviço

-   [x] Evoluir `agendamento_servicos`
-   [x] Status operacional por serviço/profissional modelado
-   [x] `iniciado_em`
-   [x] `concluido_em`
-   [x] Índices para profissional + período + status
-   [x] Criar migrations seguras
-   [x] Homologar migrations
-   [ ] Revisar `valor`
-   [ ] Revisar `duracao_minutos`
-   [ ] Definir compatibilização do status geral de `agendamentos`

### Performance --- ponto de controle

-   [ ] Consultas por profissional e intervalo
-   [ ] Consultas por serviço e período
-   [ ] Consultas por status
-   [ ] Evitar agregações grandes no PHP
-   [ ] Validar índices com volume representativo

------------------------------------------------------------------------

## 4. Perfis, colaboradores e permissões

### Perfis operacionais

-   [x] Administrador
-   [x] Profissional
-   [x] Colaborador/recepção
-   [x] Cliente
-   [ ] Permitir evolução para gerente/supervisor somente quando houver
    necessidade real

### Colaborador --- administração

-   [x] Cadastro pelo administrador
-   [x] Edição pelo administrador
-   [x] Ativar/inativar
-   [x] Cargo/função
-   [x] Permissões configuráveis por área
-   [x] Liberação de acesso separada do cadastro
-   [x] Reenvio de convite
-   [x] CPF protegido após cadastro
-   [x] E-mail protegido quando vinculado à conta
-   [x] Telefone / WhatsApp
-   [x] Endereço
-   [x] Consulta de CEP
-   [x] Permissões alteradas pelo administrador refletem no sistema

### Permissões atuais do colaborador

-   [x] Agenda
-   [x] Clientes
-   [x] Profissionais
-   [x] Serviços
-   [x] Financeiro
-   [x] Relatórios
-   [x] Configurações
-   [x] Interface esconde áreas sem permissão
-   [x] Backend consulta permissões do colaborador
-   [x] Permissões atuais são verificadas durante o uso do sistema

> As permissões acima representam **áreas**. Ações operacionais finas de
> agendamento ainda serão definidas quando o módulo de Agendamentos for
> implementado.

### Permissões operacionais futuras

-   [ ] Criar agendamento
-   [ ] Remarcar
-   [ ] Cancelar
-   [ ] Confirmar atendimento
-   [ ] Iniciar atendimento
-   [ ] Concluir atendimento
-   [ ] Marcar não comparecimento
-   [ ] Definir limites para alteração de horários
-   [ ] Definir limites para exceções e dias especiais
-   [ ] Definir limites financeiros quando o módulo existir

### Segurança

-   [x] Interface esconder ações não autorizadas nas áreas homologadas
-   [x] Backend/API validar permissões nas áreas homologadas
-   [x] Nunca confiar somente no botão oculto
-   [x] Impedir acesso cruzado entre empresas nas áreas homologadas
-   [ ] Homologar autorização completa das ações operacionais da agenda

------------------------------------------------------------------------

## 5. Empresa, identidade e serviços

### Dados da empresa

-   [x] Cadastro da empresa
-   [x] Administração dos dados da empresa
-   [x] Nome fantasia
-   [x] Segmento
-   [x] Razão social
-   [x] Documento protegido
-   [x] E-mail
-   [x] Telefone
-   [x] WhatsApp
-   [x] Endereço
-   [x] Consulta de CEP

### Estrutura comercial

-   [x] Identidade visual
-   [x] Categorias por empresa
-   [x] Serviços
-   [x] Sugestões de serviços por segmento
-   [x] Horário de funcionamento da empresa
-   [x] Configuração de permissões para colaboradores
-   [ ] Configurações gerais da agenda
-   [ ] Visibilidade financeira para profissionais

------------------------------------------------------------------------

## 6. Profissionais

### Administração

-   [x] Cadastro
-   [x] Edição
-   [x] Ativar/inativar
-   [x] Serviços do profissional
-   [x] Liberação de acesso
-   [x] Foto profissional
-   [x] Upload seguro e WebP P/M/G
-   [x] Fallback com iniciais
-   [x] Isolamento da foto por empresa
-   [x] Criar `horarios-profissionais.php`
-   [x] Visualização dos horários pelo profissional
-   \[\~\] Horários semanais --- estrutura e administração existentes;
    validar regras finais com disponibilidade
-   [ ] Bloqueios individuais
-   [ ] Folgas
-   [ ] Férias
-   [ ] Indisponibilidades pontuais

### Administração dos próprios dados

-   [x] Criar `meus-dados.php`
-   [x] Profissional administra os próprios dados pelo contexto
    autenticado
-   [x] Atualização de nome, nascimento e gênero
-   [x] Atualização de telefone / WhatsApp
-   [x] Atualização de endereço
-   [x] Consulta de CEP integrada
-   [x] Atualização de dados profissionais permitidos
-   [x] Atualização da foto profissional
-   [x] CPF protegido contra alteração
-   [x] E-mail de acesso protegido contra alteração
-   [x] Serviços, valores, duração e status permanecem sob administração
    da empresa

------------------------------------------------------------------------

## 7. Clientes

### Administração pela empresa

-   [x] Estrutura real revisada para o fluxo atual
-   [x] Cadastro administrativo
-   [x] Cadastro não cria login obrigatoriamente
-   [x] Edição
-   [x] Ativar/inativar
-   [x] Nome
-   [x] CPF
-   [x] Data de nascimento
-   [x] Gênero
-   [x] E-mail
-   [x] Telefone / WhatsApp
-   [x] Endereço
-   [x] Consulta de CEP
-   [x] Observações internas
-   [x] Status de acesso separado do status do cliente
-   [x] CPF protegido após cadastro
-   [x] E-mail protegido quando existe conta vinculada
-   [x] Isolamento por empresa
-   [ ] Busca
-   [ ] Histórico de atendimentos
-   [ ] Preservar/explorar histórico para recorrência e fidelização

### Área do cliente

-   [x] Dashboard do cliente
-   [x] Criar `meus-dados-cliente.php`
-   [x] Cliente administra os próprios dados pelo contexto autenticado
-   [x] Atualização de nome, nascimento e gênero
-   [x] Atualização de telefone / WhatsApp
-   [x] Atualização de endereço
-   [x] Consulta de CEP
-   [x] CPF protegido
-   [x] E-mail de acesso protegido
-   [x] Atualização refletida no ambiente do cliente
-   [ ] Meus agendamentos
-   [ ] Histórico operacional
-   [ ] Avaliações pendentes

------------------------------------------------------------------------

## 8. Colaboradores

### Administração pela empresa

-   [x] Cadastro
-   [x] Edição
-   [x] Ativar/inativar
-   [x] Cargo/função
-   [x] Telefone / WhatsApp
-   [x] Endereço
-   [x] Consulta de CEP
-   [x] Permissões por área
-   [x] Liberação de acesso
-   [x] Conta separada do cadastro administrativo
-   [x] CPF protegido
-   [x] E-mail de acesso protegido
-   [x] Status e acesso administrados pela empresa

### Área do colaborador

-   [x] Login
-   [x] Sessão com contexto de colaborador
-   [x] Dashboard
-   [x] Sidebar respeita permissões
-   [x] Criar `meus-dados-colaborador.php`
-   [x] Atualização de nome, nascimento e gênero
-   [x] Atualização de telefone / WhatsApp
-   [x] Atualização de endereço
-   [x] Consulta de CEP
-   [x] CPF protegido
-   [x] E-mail de acesso protegido
-   [x] Cargo apenas para consulta
-   [x] Cargo, permissões, status e acesso permanecem sob controle do
    administrador
-   [x] Remoção de permissões refletida imediatamente no sistema

------------------------------------------------------------------------

## 9. Configuração da agenda --- ETAPA ATUAL

> Os quatro atores principais e a administração de seus dados estão
> homologados. A próxima etapa é consolidar as regras que determinam
> quando um horário realmente está disponível.

### Funcionamento

-   [x] Horário semanal da empresa
-   [x] Mais de um período por dia
-   \[\~\] Horários semanais dos profissionais
-   [ ] Validar horário profissional dentro do funcionamento da empresa

### Exceções e dias especiais

-   [x] Modelar estrutura de exceções
-   [ ] Empresa fechada em data específica
-   [ ] Feriados
-   [ ] Recessos
-   [ ] Horários especiais
-   [ ] Motivo/descrição
-   [ ] Interface administrativa
-   [ ] API segura
-   [ ] Homologação

### Bloqueios profissionais

-   [ ] Bloqueio por intervalo
-   [ ] Folga
-   [ ] Férias
-   [ ] Compromisso/indisponibilidade
-   [ ] Motivo
-   [ ] Permissões para alteração

### Cálculo de disponibilidade

-   [ ] Empresa funciona no dia?
-   [ ] Existe exceção na data?
-   [ ] Profissional trabalha no período?
-   [ ] Existe bloqueio individual?
-   [ ] Existe agendamento conflitante?
-   [ ] Serviço cabe no horário?
-   [ ] Considerar duração
-   [ ] Considerar intervalos
-   [ ] Retornar slots realmente disponíveis

------------------------------------------------------------------------

## 10. Agenda administrativa

> `agenda.php` será a agenda operacional da empresa. A estrutura visual
> inicial existe, mas a operação real ainda depende da disponibilidade e
> dos agendamentos.

### Estrutura existente

-   [x] Shell/base da agenda mensal
-   [x] Navegação inicial de mês
-   [x] Estrutura inicial de filtros por profissional e serviço
-   \[\~\] Conectar a agenda aos dados operacionais reais

### Cabeçalho

-   [ ] Resumo do horário de funcionamento
-   [ ] Botão **Horário de funcionamento**
-   [ ] Botão **Horários dos profissionais**
-   [ ] Botão **Exceções e dias especiais**
-   [ ] Acesso aos bloqueios quando pertinente

### Calendário operacional

-   \[\~\] Visão mensal padrão
-   [x] Mês anterior/próximo
-   [x] Hoje
-   [x] Todos os dias do mês visíveis
-   [ ] Dias fechados identificados pelas regras reais
-   [ ] Dias abertos conforme funcionamento real
-   [ ] Exceções sobrescrevem funcionamento normal
-   [ ] Resumo real de atendimentos por dia
-   [ ] Clique no dia com operação real
-   [ ] Agenda operacional diária

### Filtros

-   [x] Todos os profissionais
-   [x] Profissional específico
-   [x] Todos os serviços
-   [x] Serviço específico
-   [ ] Status
-   \[\~\] Combinação dos filtros com dados reais

### Performance

-   [ ] Mês carrega somente resumos
-   [ ] Detalhes somente ao abrir o dia
-   [ ] Agregações no banco quando adequado
-   [ ] Testar com alto volume

### Responsividade

-   [ ] 360 px
-   [ ] 390 px
-   [ ] 412 px
-   [ ] 768 px
-   [ ] Desktop

------------------------------------------------------------------------

## 11. Agendamentos --- DEIXAR APÓS A DISPONIBILIDADE

### Estrutura

-   [ ] Criar agendamento
-   [ ] Cliente
-   [ ] Data/hora
-   [ ] Um ou vários serviços
-   [ ] Profissional por serviço
-   [ ] Duração por serviço
-   [ ] Valor por serviço
-   [ ] Ordem quando necessária
-   [ ] Validar disponibilidade

### Operações

-   [ ] Confirmar
-   [ ] Remarcar
-   [ ] Cancelar
-   [ ] Impedir conflitos
-   [ ] Registrar histórico relevante

### Status geral

-   [ ] Regra do status geral do agendamento
-   [ ] Compatibilizar com status dos serviços
-   [ ] Tratar conclusão parcial
-   [ ] Tratar cancelamento parcial quando aplicável

### Fila de Encaixe

> Funcionalidade documentada em `docs/fila-de-encaixe.md`. Implementar
> junto ao módulo de Agendamentos, não antes.

-   [x] Problema de negócio documentado
-   [x] Conceito e fluxo documentados
-   [x] Papéis envolvidos documentados
-   [x] Primeira versão delimitada
-   [ ] Estrutura de dados
-   [ ] Cadastro na fila
-   [ ] Compatibilidade entre vaga e interessados
-   [ ] Integração com cancelamento/reagendamento
-   [ ] Interface operacional
-   [ ] Homologação

------------------------------------------------------------------------

## 12. Operação do atendimento

### Status por serviço

-   [ ] `agendado`
-   [ ] `confirmado`
-   [ ] `em_atendimento`
-   [ ] `concluido`
-   [ ] `cancelado`
-   [ ] `nao_compareceu`

### Ações

-   [ ] Iniciar atendimento
-   [ ] Concluir
-   [ ] Cancelar
-   [ ] Não compareceu
-   [ ] Registrar início real
-   [ ] Registrar conclusão real

### Regras

-   [ ] Somente um `em_atendimento` por profissional
-   [ ] Encerrar atual antes de iniciar o próximo
-   [ ] Definir transições válidas
-   [ ] Impedir transições inválidas no backend
-   [ ] Admin opera conforme regras
-   [ ] Profissional opera no escopo da própria agenda
-   [ ] Colaborador opera conforme permissão concedida

------------------------------------------------------------------------

## 13. Agenda do profissional

> Mesma base operacional da agenda empresarial, obrigatoriamente
> limitada ao profissional autenticado.

### Base atual

-   [x] Acesso à Minha agenda
-   [x] Filtro limitado ao profissional autenticado
-   [x] Acesso a Meus horários
-   \[\~\] Conectar agenda profissional aos agendamentos reais

### Hoje

-   \[\~\] Estrutura de atendimentos do dia no dashboard
-   [x] Próximo atendimento no dashboard
-   [ ] Em atendimento
-   \[\~\] Agendados/confirmados
-   [x] Concluídos no resumo atual
-   [ ] Cancelados
-   [ ] Não compareceram

### Períodos

-   [ ] Hoje
-   [ ] Mensal
-   [ ] Trimestral
-   [ ] Semestral
-   [ ] Anual

### Mensal

-   \[\~\] Calendário base
-   [ ] Quantidade real por dia
-   [ ] Navegação para o dia operacional
-   [ ] Status reais
-   [ ] Serviços realizados

### Trimestral / semestral / anual

-   [ ] Indicadores agregados em vez de calendários gigantes
-   [ ] Total de serviços
-   [ ] Concluídos
-   [ ] Cancelados
-   [ ] Não compareceram
-   [ ] Distribuição por serviço
-   [ ] Histórico por período

### Controle por serviço

-   [ ] Agrupar por serviço
-   [ ] Quantidade realizada
-   [ ] Quantidade concluída
-   [ ] Cancelamentos
-   [ ] Não comparecimentos
-   [ ] Valor produzido quando autorizado

### Valores

-   [ ] Proprietário/admin define visibilidade
-   [ ] Interface respeita permissão
-   [ ] Backend respeita permissão

### Performance

-   [ ] Mensal resumido
-   [ ] Trimestral agregado
-   [ ] Semestral agregado
-   [ ] Anual agregado
-   [ ] Não processar histórico inteiro no PHP

------------------------------------------------------------------------

## 14. Dashboard administrativo

-   [x] Dashboard existente
-   \[\~\] Onboarding atual
-   [ ] Revisar checklist do onboarding
-   [ ] Redefinir **Configure a agenda**
-   [ ] Não considerar agenda configurada somente por
    `profissional_horarios`
-   [ ] Definir critérios mínimos
-   [ ] Resumo operacional real do dia
-   [ ] Total de atendimentos
-   [ ] Concluídos
-   [ ] Em atendimento
-   [ ] Cancelados
-   [ ] Não compareceram
-   [ ] Resumo por profissional

------------------------------------------------------------------------

## 15. Dashboard profissional

-   [x] Dashboard existente
-   [x] Novo visual homologado
-   [x] Foto e cargo no destaque
-   [x] Navegação específica do profissional na sidebar
-   [x] Sidebar profissional: **Visão geral → Dashboard**
-   [x] Sidebar profissional: **Agenda → Minha agenda / Meus horários**
-   [x] Sidebar profissional: **Conta → Meus dados**
-   [x] **Alterar senha** mantido no cabeçalho, sem duplicação na
    sidebar
-   [x] Dashboard profissional aponta para `dashboard-profissional.php`
-   [x] Próximo atendimento
-   [x] Resumo do dia
-   [x] Concluídos no resumo atual
-   [x] Atalho para Minha Agenda
-   [ ] Atendimento atual
-   [ ] Cancelados
-   [ ] Não compareceram
-   [ ] Valores somente quando autorizados
-   \[\~\] Substituir/validar indicadores com operação real dos
    agendamentos

------------------------------------------------------------------------

## 16. Dashboard / área do colaborador

-   [x] Dashboard existente
-   [x] Contexto autenticado de colaborador
-   [x] Sidebar conforme permissões
-   [x] Remoção de permissão refletida no menu
-   [x] Meus dados
-   [x] Alterar senha no cabeçalho
-   [ ] Evoluir indicadores quando a agenda operacional estiver pronta

------------------------------------------------------------------------

## 17. Dashboard / área do cliente

-   [x] Dashboard existente
-   [x] Meus dados
-   [x] Nome atualizado refletido no ambiente
-   [x] Alterar senha
-   [ ] Meus agendamentos
-   [ ] Histórico
-   [ ] Avaliações pendentes

------------------------------------------------------------------------

## 18. Indicadores e relatórios

### Administrador

-   [ ] Atendimentos por período
-   [ ] Por profissional
-   [ ] Por serviço
-   [ ] Por status
-   [ ] Taxa de conclusão
-   [ ] Taxa de cancelamento
-   [ ] Taxa de não comparecimento
-   [ ] Valores
-   [ ] Serviços mais realizados

### Profissional

-   [ ] Produção individual
-   [ ] Serviços realizados
-   [ ] Concluídos
-   [ ] Cancelados
-   [ ] Não comparecimentos
-   [ ] Valores quando autorizados

### Performance

-   [ ] Agregações no MySQL
-   [ ] Índices adequados
-   [ ] Paginação no histórico
-   [ ] Evitar relatórios anuais linha a linha em PHP
-   [ ] Cache somente se medição mostrar necessidade

------------------------------------------------------------------------

## 19. Avaliações

> Somente após a operação do atendimento estar homologada.

-   [ ] Avaliar somente serviço concluído
-   [ ] Vincular ao serviço/agendamento real
-   [ ] Vincular profissional
-   [ ] Vincular cliente
-   [ ] Nota 1--5
-   [ ] Comentário opcional
-   [ ] Uma avaliação por serviço/profissional
-   [ ] Média real
-   [ ] Quantidade real
-   [ ] Substituir placeholder dos cards

------------------------------------------------------------------------

## 20. Google Calendar

> Somente depois da agenda interna e agendamentos estarem sólidos.

-   [ ] Autenticação Google
-   [ ] Vincular calendário ao profissional
-   [ ] Salão Agenda permanece fonte de verdade
-   [ ] Criar evento
-   [ ] Atualizar ao remarcar
-   [ ] Remover/cancelar quando necessário
-   [ ] Vincular evento externo ao serviço agendado
-   [ ] Tratar falhas sem bloquear a operação interna
-   [ ] Estratégia de reprocessamento
-   [ ] Futuro: ler compromissos pessoais como bloqueios

### Performance/resiliência

-   [ ] Chamada externa não pode tornar a agenda lenta
-   [ ] Avaliar sincronização desacoplada
-   [ ] Registrar falhas
-   [ ] Agenda interna funciona mesmo com Google indisponível

------------------------------------------------------------------------

## 21. Fidelização, recorrência e crescimento --- FUTURO

-   [ ] Fidelidade baseada em atendimentos concluídos
-   [ ] Recorrência
-   [ ] Aniversários
-   [ ] Indicações
-   [ ] Recompensas configuráveis
-   [ ] Histórico preservado
-   [ ] Regras por empresa
-   [ ] Comissões
-   [ ] Metas
-   [ ] Métricas avançadas
-   [ ] Ações pós-atendimento

------------------------------------------------------------------------

## 22. Segurança multi-tenant --- transversal

-   [x] `empresa_id` sempre do contexto autenticado nas áreas
    homologadas
-   [x] Nunca aceitar `empresa_id` arbitrário do cliente
-   [x] Propriedade de profissionais validada nas áreas homologadas
-   [x] Propriedade de clientes validada nas áreas homologadas
-   [x] Propriedade de colaboradores validada nas áreas homologadas
-   [x] Prepared statements
-   [x] Escape de saída HTML
-   [x] CSRF nas operações mutáveis implementadas/homologadas
-   [ ] Validar propriedade de agendamentos na operação futura
-   [ ] Homologar permissões de todas as ações da agenda
-   [ ] Não revelar dados de outra empresa em todos os módulos futuros
-   [ ] Teste transversal final de tentativa de acesso cruzado
-   [ ] Testes finais de IDOR

------------------------------------------------------------------------

## 23. Performance --- transversal

> Se qualquer decisão começar a comprometer banco, memória, tempo de
> resposta ou experiência mobile, **replanejar antes de continuar**.

-   [ ] Índices orientados às consultas reais
-   [ ] Consultas eficientes por intervalo
-   [ ] Consultas por profissional
-   [ ] Consultas por serviço
-   [ ] Consultas por status
-   [ ] Calendário mensal resumido
-   [ ] Detalhes sob demanda
-   [ ] Relatórios com agregações SQL
-   [ ] Paginação
-   [ ] Evitar N+1 queries
-   [ ] Evitar dados desnecessários
-   [ ] Medir antes de adicionar cache
-   [ ] Monitorar integrações externas
-   [ ] Testar com volume maior que o ambiente de desenvolvimento
-   [ ] Prioridade mobile e performance

------------------------------------------------------------------------

## 24. Identidade e contatos da empresa --- RADAR

> Evolução futura da camada visual/institucional. Não misturar com a
> etapa operacional atual.

-   [ ] Preparar header para receber identidade dinâmica da empresa
-   [ ] Preparar footer para receber identidade dinâmica da empresa
-   [ ] Exibir logo da empresa
-   [ ] Exibir telefone
-   [ ] Exibir WhatsApp
-   [ ] Exibir e-mail
-   [ ] Exibir links para as principais redes sociais
-   [ ] Definir redes sociais suportadas inicialmente
-   [ ] Permitir administração desses dados pelo administrador
-   [ ] Reutilizar os mesmos dados de contato de forma consistente no
    sistema
-   [ ] Definir fallback quando logo ou dados de contato não estiverem
    cadastrados
-   [ ] Revisar responsividade do header e footer

------------------------------------------------------------------------

## 25. Homologação final

### Funcional

-   [x] Administração dos dados da empresa
-   [x] Administração dos profissionais
-   [x] Autoadministração dos dados do profissional
-   [x] Administração dos clientes
-   [x] Autoadministração dos dados do cliente
-   [x] Administração dos colaboradores
-   [x] Autoadministração dos dados do colaborador
-   [x] Permissões atuais do colaborador
-   [ ] Disponibilidade
-   [ ] Agenda operacional
-   [ ] Agendamentos
-   [ ] Operação dos atendimentos
-   [ ] Fila de Encaixe
-   [ ] Relatórios
-   [ ] Avaliações
-   [ ] Google Calendar

### Responsividade

-   [ ] 360 px
-   [ ] 390 px
-   [ ] 412 px
-   [ ] 768 px
-   [ ] Desktop

### Segurança

-   [ ] Multi-tenant --- homologação transversal final
-   [ ] CSRF --- revisão transversal final
-   [ ] Autorização --- revisão transversal final
-   [ ] IDOR
-   [ ] Brute force --- revisão final
-   [ ] Uploads
-   [ ] Sessões
-   [ ] Recuperação de senha --- revisão final

### Performance

-   [ ] Agenda mensal
-   [ ] Agenda diária
-   [ ] Agenda profissional
-   [ ] Consultas anuais
-   [ ] Filtros combinados
-   [ ] Dashboard
-   [ ] Mobile
-   [ ] Integrações externas

### Produção

-   [ ] Ambiente HostGator
-   [ ] Variáveis de ambiente
-   [ ] Banco de produção
-   [ ] HTTPS
-   [ ] E-mail transacional
-   [ ] Backups
-   [ ] Logs
-   [ ] Tratamento de erros
-   [ ] Deploy
-   [ ] Smoke test pós-deploy

------------------------------------------------------------------------

# Ordem oficial de desenvolvimento a partir de agora

``` text
1. Consolidar configuração da agenda
   - horários profissionais
   - exceções e dias especiais
   - bloqueios profissionais
        ↓
2. Implementar cálculo real de disponibilidade
        ↓
3. Consolidar agenda administrativa mensal/diária
        ↓
4. Implementar agendamentos
        ↓
5. Implementar operação dos atendimentos
        ↓
6. Implementar Fila de Encaixe
        ↓
7. Consolidar agenda operacional do profissional
        ↓
8. Completar área do cliente com agendamentos/histórico
        ↓
9. Indicadores e relatórios
        ↓
10. Avaliações
        ↓
11. Google Calendar
        ↓
12. Fidelização / recorrência / comissões
        ↓
13. Homologação completa
        ↓
14. Produção
```

------------------------------------------------------------------------

# Próximo passo

## Configuração da agenda e disponibilidade

Antes de entrar na operação completa de agendamentos:

-   [ ] Homologar regras finais dos horários dos profissionais
-   [ ] Implementar exceções e dias especiais
-   [ ] Implementar bloqueios/indisponibilidades dos profissionais
-   [ ] Definir e implementar cálculo central de disponibilidade
-   [ ] Garantir que disponibilidade respeite empresa + profissional +
    serviço + duração + bloqueios + exceções

> **Agendamentos permanecem depois da consolidação da disponibilidade.**

------------------------------------------------------------------------

## Resumo atual

A fundação de autenticação e a padronização da administração de dados
dos principais atores estão consolidadas:

-   **Empresa:** dados administráveis e homologados.
-   **Profissional:** administração pela empresa + área própria
    homologadas.
-   **Cliente:** administração pela empresa + área própria homologadas.
-   **Colaborador:** administração pela empresa + área própria +
    permissões homologadas.
-   **Fila de Encaixe:** conceito documentado para implementação futura
    junto aos Agendamentos.

A próxima fronteira é transformar horários, exceções e bloqueios em uma
**regra central de disponibilidade confiável**. Somente depois disso a
operação completa de Agendamentos deve ser construída.
