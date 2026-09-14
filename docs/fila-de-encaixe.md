# Fila de Encaixe

## Objetivo

A **Fila de Encaixe** será um recurso do futuro módulo de
**Agendamentos** para ajudar a empresa a reaproveitar horários que
ficaram disponíveis, principalmente em períodos de alta procura.

O problema ocorre quando um horário já reservado volta a ficar livre por
situações como:

-   cancelamento antecipado pelo cliente;
-   reagendamento;
-   desistência;
-   impossibilidade de comparecimento informada pelo cliente;
-   outra liberação do horário.

A finalidade da Fila de Encaixe é permitir que a empresa identifique
rapidamente clientes interessados e compatíveis com o horário liberado,
reduzindo a perda de capacidade e de faturamento.

------------------------------------------------------------------------

## Conceito principal

A Fila de Encaixe **não substitui a agenda**.

Ela funciona como uma lista de interesse associada à disponibilidade
futura.

Fluxo básico:

``` text
Cliente possui agendamento
        ↓
Horário é liberado
        ↓
Agenda volta a considerar o período disponível
        ↓
Sistema consulta a Fila de Encaixe
        ↓
Identifica clientes compatíveis
        ↓
Administrador/colaborador entra em contato
        ↓
Cliente aceita
        ↓
Novo agendamento ocupa o horário
```

------------------------------------------------------------------------

## Quem poderá administrar

A gestão operacional da Fila de Encaixe seguirá as mesmas regras de
acesso definidas para a agenda.

### Administrador

Pode visualizar e administrar toda a fila da empresa.

### Colaborador

Pode visualizar e administrar a fila quando possuir a permissão de
**agenda** habilitada.

### Profissional

Poderá visualizar ou atuar nos encaixes relacionados à própria agenda
conforme as regras operacionais que forem definidas no módulo de
Agendamentos.

### Cliente

Não administra a fila da empresa.

No futuro poderá solicitar interesse em um encaixe, informando sua
disponibilidade e preferências.

------------------------------------------------------------------------

## Informações mínimas da solicitação de encaixe

Uma entrada na fila deverá permitir representar, no mínimo:

-   cliente;
-   serviço desejado;
-   profissional preferido, quando houver;
-   data ou período desejado;
-   faixa de horário disponível;
-   telefone/WhatsApp para contato;
-   data e hora em que entrou na fila;
-   situação da solicitação.

O profissional poderá ser opcional para permitir situações como:

> "Quero corte amanhã à tarde com qualquer profissional disponível."

------------------------------------------------------------------------

## Compatibilidade com um horário liberado

Quando surgir uma vaga, o sistema deverá considerar fatores como:

-   empresa;
-   serviço solicitado;
-   duração necessária;
-   profissional apto a executar o serviço;
-   preferência por profissional, quando informada;
-   data;
-   faixa de horário aceita pelo cliente;
-   situação ativa da solicitação.

Exemplo:

``` text
Horário liberado
15:00 às 16:00
Profissional: Ana
Duração disponível: 60 minutos

Fila compatível:
1. Fernanda — serviço compatível — Ana — 13h às 16h
2. Carla — serviço compatível — qualquer profissional — após 15h
```

------------------------------------------------------------------------

## Liberação de horário

Ao cancelar ou reagendar um agendamento, o sistema deverá separar duas
responsabilidades:

1.  registrar corretamente o que aconteceu com o agendamento original;
2.  devolver o intervalo à disponibilidade da agenda quando aplicável.

A liberação do horário poderá então acionar a consulta da Fila de
Encaixe.

Exemplo de operação:

``` text
Cancelar agendamento

Motivo:
- Cliente desistiu
- Cliente informou que não poderá comparecer
- Estabelecimento cancelou
- Outro

[Cancelar e liberar horário]
```

Após a operação:

``` text
Horário 15:00 liberado.

3 clientes da Fila de Encaixe são compatíveis.

[Ver interessados]
```

------------------------------------------------------------------------

## Não comparecimento

**Não compareceu** é diferente de um cancelamento antecipado.

Quando o cliente simplesmente falta, o agendamento deverá manter o
registro operacional de não comparecimento.

A possibilidade de reaproveitar parte do horário dependerá das regras
que ainda serão definidas no módulo de Agendamentos.

Portanto, nesta documentação não assumimos automaticamente que todo
`não_compareceu` gera um novo encaixe.

------------------------------------------------------------------------

## Primeira versão

A primeira versão deverá ser simples e operacional.

O sistema:

1.  registra clientes interessados em encaixe;
2.  identifica solicitações compatíveis quando surgir uma vaga;
3.  apresenta os interessados ao usuário que administra a agenda;
4.  permite que a recepção/empresa faça o contato;
5.  após aceite, cria um novo agendamento normalmente.

### Fora da primeira versão

Inicialmente não será necessário:

-   envio automático de WhatsApp;
-   disparo simultâneo para vários clientes;
-   reserva temporária automática do horário;
-   aceite automático por link;
-   sistema complexo de prioridade;
-   automações externas.

Essas possibilidades poderão ser avaliadas depois que o fluxo manual
estiver homologado.

------------------------------------------------------------------------

## Regra importante

Um horário liberado **não pertence automaticamente ao primeiro cliente
da fila**.

A fila deve ajudar a localizar interessados compatíveis. A confirmação
do encaixe deverá gerar um **novo agendamento**, respeitando todas as
validações normais da agenda.

Isso evita que a Fila de Encaixe crie reservas inconsistentes ou burle
regras de disponibilidade.

------------------------------------------------------------------------

## Integração futura com Agendamentos

A Fila de Encaixe será desenvolvida somente quando o módulo operacional
de Agendamentos for implementado.

Visão prevista:

``` text
Agendamentos
├── Agendar
├── Confirmar
├── Reagendar
├── Iniciar atendimento
├── Concluir
├── Cancelar
├── Não compareceu
└── Fila de Encaixe
```

Ela deverá utilizar as mesmas regras centrais de:

-   disponibilidade;
-   horários da empresa;
-   horários do profissional;
-   bloqueios;
-   exceções;
-   duração dos serviços;
-   profissional/serviço;
-   isolamento por empresa (`empresa_id`);
-   permissões do usuário.

------------------------------------------------------------------------

## Valor operacional

A Fila de Encaixe não é apenas uma conveniência de agenda.

Ela existe para resolver um problema de negócio:

> **transformar uma vaga inesperada em uma nova oportunidade de
> atendimento.**

Em datas e horários de alta procura, isso permite que a empresa reaja
rapidamente a cancelamentos e aproveite melhor a capacidade disponível.

------------------------------------------------------------------------

## Status

**Planejado / documentado.**

Não implementar agora.

A implementação será feita junto ao módulo de **Agendamentos**, depois
da conclusão e padronização das áreas de Administrador, Profissional,
Cliente e Colaborador.
