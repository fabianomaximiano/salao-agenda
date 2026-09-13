# Salão Agenda — Checklist Mestre do Projeto

> Documento vivo de acompanhamento do desenvolvimento do **Salão Agenda**.
>
> **Princípio:** consolidar fundação, dados, segurança, permissões e regras antes da operação completa da agenda.

## Legenda

- [x] Concluído / homologado
- [ ] Pendente
- [~] Iniciado / precisa revisão

---

## 1. Fundação técnica e ambiente

- [x] Docker
- [x] Nginx
- [x] PHP 8.2 FPM
- [x] MySQL 8.0
- [x] phpMyAdmin
- [x] Timezone `America/Sao_Paulo`
- [x] GD com JPEG, PNG e WebP
- [x] EXIF
- [x] Estrutura multi-tenant baseada em `empresa_id`
- [x] `empresa_id` obtido pelo contexto autenticado
- [ ] Revisar limite global de upload PHP × identidade visual
- [ ] Revisar `client_max_body_size` do Nginx antes da homologação final de uploads
- [ ] Revisão final para produção/HostGator

---

## 2. Autenticação e segurança

### Administrador

- [x] Login administrativo
- [x] Sessão administrativa
- [x] Onboarding com empresa/usuário/admin inicialmente inativos
- [x] Código de confirmação de 6 dígitos
- [x] Expiração e limite de tentativas
- [x] Cooldown e limite de códigos por hora
- [x] Token forte de ativação
- [x] Política de senha forte
- [x] Proteção contra brute force no login

### Profissional

- [x] Profissional pode existir sem login
- [x] Liberação de acesso pelo administrador
- [x] E-mail corporativo
- [x] Confirmação por código
- [x] Token de ativação
- [x] Definição de senha
- [x] Login profissional
- [x] Sessão profissional
- [x] Dashboard profissional

### Senhas

- [x] Recuperação de senha
- [x] Resposta neutra
- [x] Token forte e de uso único
- [x] Expiração, cooldown e limites
- [x] Alteração autenticada de senha
- [x] Proteção independente contra brute force
- [x] Regeneração da sessão
- [ ] Invalidação global de sessões antigas

---

## 3. Remodelagem do banco de dados — ETAPA CRÍTICA ATUAL

### Levantamento

- [ ] Levantar tabelas reais do MySQL envolvidas
- [ ] Comparar banco real × `schema.sql`
- [ ] Produzir mapa **MANTER / ALTERAR / CRIAR / REVISAR**
- [ ] Revisar relacionamentos e chaves estrangeiras
- [ ] Revisar índices pensando em agenda e relatórios

### Identidade, vínculo e autorização

- [ ] Revisar `usuarios`
- [ ] Revisar `administradores`
- [ ] Revisar `profissionais`
- [ ] Definir vínculo usuário ↔ empresa adequado aos novos perfis
- [ ] Modelar colaborador/recepção
- [ ] Modelar perfis
- [ ] Modelar permissões
- [ ] Garantir isolamento multi-tenant nas novas estruturas

### Atendimento por serviço

- [ ] Evoluir `agendamento_servicos`
- [ ] Status operacional por serviço/profissional
- [ ] `iniciado_em`
- [ ] `concluido_em`
- [ ] Revisar `valor`
- [ ] Revisar `duracao_minutos`
- [ ] Índices para profissional + período + status
- [ ] Definir compatibilização do status geral de `agendamentos`
- [ ] Criar migrations seguras
- [ ] Atualizar `schema.sql`
- [ ] Homologar migrations

### Performance — ponto de controle

- [ ] Consultas por profissional e intervalo
- [ ] Consultas por serviço e período
- [ ] Consultas por status
- [ ] Evitar agregações grandes no PHP
- [ ] Validar índices com volume representativo

---

## 4. Perfis, colaboradores e permissões

### Perfis

- [x] Administrador — conceito atual
- [x] Profissional — conceito atual
- [ ] Colaborador/recepção
- [ ] Permitir evolução para gerente/supervisor sem regras rígidas espalhadas pelo código

### Permissões configuráveis

- [ ] Visualizar agenda geral
- [ ] Criar agendamento
- [ ] Remarcar
- [ ] Cancelar
- [ ] Confirmar atendimento
- [ ] Iniciar atendimento
- [ ] Concluir atendimento
- [ ] Marcar não comparecimento
- [ ] Visualizar/cadastrar/editar clientes
- [ ] Visualizar valores
- [ ] Alterar valores
- [ ] Visualizar/cadastrar/editar profissionais
- [ ] Alterar horários dos profissionais
- [ ] Alterar horário da empresa
- [ ] Administrar exceções e dias especiais
- [ ] Acessar relatórios
- [ ] Visualizar informações financeiras
- [ ] Administrar usuários/permissões

### Segurança

- [ ] Interface esconder ações não autorizadas
- [ ] Backend/API validar todas as permissões
- [ ] Nunca confiar somente no botão oculto
- [ ] Avaliar carregamento das permissões na sessão
- [ ] Impedir acesso cruzado entre empresas

---

## 5. Empresa, identidade e serviços

- [x] Cadastro da empresa
- [x] Identidade visual
- [x] Categorias por empresa
- [x] Serviços
- [x] Sugestões de serviços por segmento
- [x] Horário de funcionamento da empresa
- [ ] Configurações gerais da agenda
- [ ] Visibilidade financeira para profissionais
- [ ] Configuração de permissões para colaboradores

---

## 6. Profissionais

- [x] Cadastro
- [x] Edição
- [x] Ativar/inativar
- [x] Serviços do profissional
- [x] Liberação de acesso
- [x] Foto profissional
- [x] Upload seguro e WebP P/M/G
- [x] Fallback com iniciais
- [x] Isolamento da foto por empresa
- [~] Horários semanais
- [ ] Separar configuração semanal da verdadeira `agenda.php`
- [ ] Criar `horarios-profissionais.php`
- [ ] Bloqueios individuais
- [ ] Folgas
- [ ] Férias
- [ ] Indisponibilidades pontuais

---

## 7. Configuração da agenda

### Funcionamento

- [x] Horário semanal da empresa
- [x] Mais de um período por dia
- [~] Horários semanais dos profissionais
- [ ] Validar horário profissional dentro do funcionamento da empresa

### Exceções e dias especiais

- [ ] Modelar estrutura de exceções
- [ ] Empresa fechada em data específica
- [ ] Feriados
- [ ] Recessos
- [ ] Horários especiais
- [ ] Motivo/descrição
- [ ] Interface administrativa
- [ ] API segura
- [ ] Homologação

### Bloqueios profissionais

- [ ] Bloqueio por intervalo
- [ ] Folga
- [ ] Férias
- [ ] Compromisso/indisponibilidade
- [ ] Motivo
- [ ] Permissões para alteração

### Cálculo de disponibilidade

- [ ] Empresa funciona no dia?
- [ ] Existe exceção na data?
- [ ] Profissional trabalha no período?
- [ ] Existe bloqueio individual?
- [ ] Existe agendamento conflitante?
- [ ] Serviço cabe no horário?
- [ ] Considerar duração
- [ ] Considerar intervalos
- [ ] Retornar slots realmente disponíveis

---

## 8. Agenda administrativa

> `agenda.php` será a agenda operacional da empresa, não uma configuração semanal.

### Cabeçalho

- [ ] Resumo do horário de funcionamento
- [ ] Botão **Horário de funcionamento**
- [ ] Botão **Horários dos profissionais**
- [ ] Botão **Exceções e dias especiais**
- [ ] Acesso aos bloqueios quando pertinente

### Calendário

- [ ] Visão mensal padrão
- [ ] Mês anterior/próximo
- [ ] Hoje
- [ ] Todos os dias do mês visíveis
- [ ] Dias fechados identificados
- [ ] Dias abertos conforme funcionamento
- [ ] Exceções sobrescrevem funcionamento normal
- [ ] Resumo de atendimentos por dia
- [ ] Clique no dia
- [ ] Agenda operacional diária

### Filtros

- [ ] Todos os profissionais
- [ ] Profissional específico
- [ ] Todos os serviços
- [ ] Serviço específico
- [ ] Status
- [ ] Combinação dos filtros

### Performance

- [ ] Mês carrega somente resumos
- [ ] Detalhes somente ao abrir o dia
- [ ] Agregações no banco quando adequado
- [ ] Testar com alto volume

### Responsividade

- [ ] 360 px
- [ ] 390 px
- [ ] 412 px
- [ ] 768 px
- [ ] Desktop

---

## 9. Clientes

- [ ] Revisar estrutura real
- [ ] Cadastro
- [ ] Edição
- [ ] Busca
- [ ] Histórico
- [ ] Contatos
- [ ] Isolamento por empresa
- [ ] Preservar histórico para recorrência/fidelização

---

## 10. Agendamentos

### Estrutura

- [ ] Criar agendamento
- [ ] Cliente
- [ ] Data/hora
- [ ] Um ou vários serviços
- [ ] Profissional por serviço
- [ ] Duração por serviço
- [ ] Valor por serviço
- [ ] Ordem quando necessária
- [ ] Validar disponibilidade

### Operações

- [ ] Confirmar
- [ ] Remarcar
- [ ] Cancelar
- [ ] Impedir conflitos
- [ ] Registrar histórico relevante

### Status geral

- [ ] Regra do status geral do agendamento
- [ ] Compatibilizar com status dos serviços
- [ ] Tratar conclusão parcial
- [ ] Tratar cancelamento parcial quando aplicável

---

## 11. Operação do atendimento

### Status por serviço

- [ ] `agendado`
- [ ] `confirmado`
- [ ] `em_atendimento`
- [ ] `concluido`
- [ ] `cancelado`
- [ ] `nao_compareceu`

### Ações

- [ ] Iniciar atendimento
- [ ] Concluir
- [ ] Cancelar
- [ ] Não compareceu
- [ ] Registrar início real
- [ ] Registrar conclusão real

### Regras

- [ ] Somente um `em_atendimento` por profissional
- [ ] Encerrar atual antes de iniciar o próximo
- [ ] Definir transições válidas
- [ ] Impedir transições inválidas no backend
- [ ] Admin opera conforme regras
- [ ] Profissional opera conforme permissões
- [ ] Recepção/colaborador opera conforme permissões concedidas

---

## 12. Agenda do profissional

> Mesma base da agenda empresarial, obrigatoriamente limitada ao profissional autenticado.

### Hoje

- [ ] Atendimentos do dia
- [ ] Próximo cliente
- [ ] Em atendimento
- [ ] Agendados/confirmados
- [ ] Concluídos
- [ ] Cancelados
- [ ] Não compareceram

### Períodos

- [ ] Hoje
- [ ] Mensal
- [ ] Trimestral
- [ ] Semestral
- [ ] Anual

### Mensal

- [ ] Calendário
- [ ] Quantidade por dia
- [ ] Navegação para o dia
- [ ] Status
- [ ] Serviços realizados

### Trimestral / semestral / anual

- [ ] Indicadores agregados em vez de calendários gigantes
- [ ] Total de serviços
- [ ] Concluídos
- [ ] Cancelados
- [ ] Não compareceram
- [ ] Distribuição por serviço
- [ ] Histórico por período

### Controle por serviço

- [ ] Agrupar por serviço
- [ ] Quantidade realizada
- [ ] Quantidade concluída
- [ ] Cancelamentos
- [ ] Não comparecimentos
- [ ] Valor produzido quando autorizado

### Valores

- [ ] Proprietário/admin define visibilidade
- [ ] Interface respeita permissão
- [ ] Backend respeita permissão

### Performance

- [ ] Mensal resumido
- [ ] Trimestral agregado
- [ ] Semestral agregado
- [ ] Anual agregado
- [ ] Não processar histórico inteiro no PHP

---

## 13. Dashboard administrativo

- [x] Dashboard existente
- [~] Onboarding atual
- [ ] Revisar checklist do onboarding
- [ ] Redefinir **Configure a agenda**
- [ ] Não considerar agenda configurada somente por `profissional_horarios`
- [ ] Definir critérios mínimos
- [ ] Resumo operacional do dia
- [ ] Total de atendimentos
- [ ] Concluídos
- [ ] Em atendimento
- [ ] Cancelados
- [ ] Não compareceram
- [ ] Resumo por profissional

---

## 14. Dashboard profissional

- [x] Dashboard existente
- [ ] Próximo atendimento
- [ ] Atendimento atual
- [ ] Resumo do dia
- [ ] Concluídos
- [ ] Cancelados
- [ ] Não compareceram
- [ ] Atalho para Minha Agenda
- [ ] Valores somente quando autorizados

---

## 15. Indicadores e relatórios

### Administrador

- [ ] Atendimentos por período
- [ ] Por profissional
- [ ] Por serviço
- [ ] Por status
- [ ] Taxa de conclusão
- [ ] Taxa de cancelamento
- [ ] Taxa de não comparecimento
- [ ] Valores
- [ ] Serviços mais realizados

### Profissional

- [ ] Produção individual
- [ ] Serviços realizados
- [ ] Concluídos
- [ ] Cancelados
- [ ] Não comparecimentos
- [ ] Valores quando autorizados

### Performance

- [ ] Agregações no MySQL
- [ ] Índices adequados
- [ ] Paginação no histórico
- [ ] Evitar relatórios anuais linha a linha em PHP
- [ ] Cache somente se medição mostrar necessidade

---

## 16. Avaliações

> Somente após a operação do atendimento estar homologada.

- [ ] Avaliar somente serviço concluído
- [ ] Vincular ao serviço/agendamento real
- [ ] Vincular profissional
- [ ] Vincular cliente
- [ ] Nota 1–5
- [ ] Comentário opcional
- [ ] Uma avaliação por serviço/profissional
- [ ] Média real
- [ ] Quantidade real
- [ ] Substituir placeholder dos cards

---

## 17. Google Calendar

> Somente depois da agenda interna e agendamentos estarem sólidos.

- [ ] Autenticação Google
- [ ] Vincular calendário ao profissional
- [ ] Salão Agenda permanece fonte de verdade
- [ ] Criar evento
- [ ] Atualizar ao remarcar
- [ ] Remover/cancelar quando necessário
- [ ] Vincular evento externo ao serviço agendado
- [ ] Tratar falhas sem bloquear a operação interna
- [ ] Estratégia de reprocessamento
- [ ] Futuro: ler compromissos pessoais como bloqueios

### Performance/resiliência

- [ ] Chamada externa não pode tornar a agenda lenta
- [ ] Avaliar sincronização desacoplada
- [ ] Registrar falhas
- [ ] Agenda interna funciona mesmo com Google indisponível

---

## 18. Fidelização, recorrência e crescimento — FUTURO

- [ ] Fidelidade baseada em atendimentos concluídos
- [ ] Recorrência
- [ ] Aniversários
- [ ] Indicações
- [ ] Recompensas configuráveis
- [ ] Histórico preservado
- [ ] Regras por empresa
- [ ] Comissões
- [ ] Metas
- [ ] Métricas avançadas
- [ ] Ações pós-atendimento

---

## 19. Segurança multi-tenant — transversal

- [ ] `empresa_id` sempre do contexto autenticado
- [ ] Nunca aceitar `empresa_id` arbitrário do cliente
- [ ] Validar propriedade de profissionais
- [ ] Validar propriedade de serviços
- [ ] Validar propriedade de clientes
- [ ] Validar propriedade de agendamentos
- [ ] Validar permissões no backend
- [ ] CSRF em operações mutáveis
- [ ] Prepared statements
- [ ] Escape de saída HTML
- [ ] Não revelar dados de outra empresa
- [ ] Testar tentativa de acesso cruzado

---

## 20. Performance — transversal

> Se qualquer decisão começar a comprometer banco, memória, tempo de resposta ou experiência mobile, **replanejar antes de continuar**.

- [ ] Índices orientados às consultas reais
- [ ] Consultas eficientes por intervalo
- [ ] Consultas por profissional
- [ ] Consultas por serviço
- [ ] Consultas por status
- [ ] Calendário mensal resumido
- [ ] Detalhes sob demanda
- [ ] Relatórios com agregações SQL
- [ ] Paginação
- [ ] Evitar N+1 queries
- [ ] Evitar dados desnecessários
- [ ] Medir antes de adicionar cache
- [ ] Monitorar integrações externas
- [ ] Testar com volume maior que o ambiente de desenvolvimento
- [ ] Prioridade mobile e performance

---

## 21. Homologação final

### Funcional

- [ ] Administrador
- [ ] Profissional
- [ ] Colaborador/recepção
- [ ] Permissões
- [ ] Empresa
- [ ] Serviços
- [ ] Profissionais
- [ ] Clientes
- [ ] Disponibilidade
- [ ] Agenda
- [ ] Agendamentos
- [ ] Operação
- [ ] Relatórios
- [ ] Avaliações
- [ ] Google Calendar

### Responsividade

- [ ] 360 px
- [ ] 390 px
- [ ] 412 px
- [ ] 768 px
- [ ] Desktop

### Segurança

- [ ] Multi-tenant
- [ ] CSRF
- [ ] Autorização
- [ ] IDOR
- [ ] Brute force
- [ ] Uploads
- [ ] Sessões
- [ ] Recuperação de senha

### Performance

- [ ] Agenda mensal
- [ ] Agenda diária
- [ ] Agenda profissional
- [ ] Consultas anuais
- [ ] Filtros combinados
- [ ] Dashboard
- [ ] Mobile
- [ ] Integrações externas

### Produção

- [ ] Ambiente HostGator
- [ ] Variáveis de ambiente
- [ ] Banco de produção
- [ ] HTTPS
- [ ] E-mail transacional
- [ ] Backups
- [ ] Logs
- [ ] Tratamento de erros
- [ ] Deploy
- [ ] Smoke test pós-deploy

---

# Ordem oficial de desenvolvimento a partir de agora

```text
1. Levantar banco REAL
        ↓
2. Desenhar remodelagem
        ↓
3. Aprovar modelo
        ↓
4. Migrations + índices
        ↓
5. Perfis e permissões
        ↓
6. Finalizar configuração da agenda
        ↓
7. Agenda administrativa mensal
        ↓
8. Clientes + agendamentos
        ↓
9. Operação dos atendimentos
        ↓
10. Agenda do profissional
        ↓
11. Indicadores e relatórios
        ↓
12. Avaliações
        ↓
13. Google Calendar
        ↓
14. Fidelização / recorrência / comissões
        ↓
15. Homologação completa
        ↓
16. Produção
```

---

# Próximo passo

## Remodelagem estrutural do banco

- [ ] Levantar tabelas reais envolvidas
- [ ] Comparar com `schema.sql`
- [ ] Produzir mapa **MANTER / ALTERAR / CRIAR / REVISAR**
- [ ] Avaliar impacto de performance
- [ ] Aprovar antes de executar qualquer migration

> **Não avançar a implementação visual da agenda antes desta etapa estar definida.**

---

## Resumo

O projeto já possui uma fundação relevante de autenticação, segurança, empresa, serviços e profissionais. A próxima grande fronteira é transformar essa base em um sistema operacional completo de agenda, atendimentos e autorização por perfil.

Sim: **o monstro cresceu.** 😂  
Mas agora ele está dividido em módulos, dependências e uma ordem clara de implementação.
