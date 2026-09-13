# Roadmap de Fidelização, Promoções e Recorrência

## 1. Objetivo deste documento

Este documento registra as ideias, princípios e decisões de produto relacionadas a **fidelização, promoções, indicação e recorrência** da plataforma.

Esses recursos **não fazem parte do núcleo operacional atual** e não devem atrasar a entrega das funções principais de cadastro, profissionais, clientes, disponibilidade e agenda.

O objetivo agora é preservar as decisões importantes para que, no momento certo, esses recursos possam ser implementados de forma consistente, multissegmento e sem retrabalho estrutural.

---

## 2. Princípio central

A plataforma deve atender **diversos segmentos de negócios baseados em agendamento e atendimento**, sem criar regras específicas para salão de beleza, barbearia, clínica, estética, pet shop ou qualquer outro nicho.

A regra principal é:

> **O sistema deve trabalhar com regras configuráveis, e não com regras fixas de um segmento específico.**

Exemplo:

- incorreto: “a cada 10 cortes, o cliente ganha 1 corte grátis”;
- correto: “a cada X atendimentos/serviços elegíveis concluídos, o cliente recebe Y benefício”.

Com isso, cada empresa poderá adaptar a mesma funcionalidade ao próprio negócio.

---

## 3. Prioridade atual do produto

A prioridade atual continua sendo o núcleo operacional:

1. cadastro e gestão da empresa;
2. serviços;
3. profissionais;
4. horários e disponibilidade;
5. clientes;
6. agendamentos;
7. operação diária;
8. experiência responsiva em celular.

Os recursos deste documento entram **depois que o fluxo operacional estiver funcionando e validado**.

---

# 4. Primeira fase de fidelização

A primeira versão deve ser simples, fácil de entender e fácil de operar.

## 4.1 Fidelidade por quantidade de atendimentos

Exemplo comercial:

> **10 cortes = 1 grátis**

Internamente, a regra deve ser genérica.

Exemplos possíveis:

- 10 cortes → 1 corte grátis;
- 5 banhos → 1 banho grátis;
- 6 escovas → desconto no próximo atendimento;
- 8 sessões → benefício definido pela empresa.

### Configurações futuras da regra

A empresa poderá definir:

- nome da campanha;
- quantidade necessária;
- quais serviços contam;
- se qualquer serviço conta;
- benefício oferecido;
- validade do benefício;
- quantidade máxima de usos;
- período de vigência;
- ativo/inativo.

### Regra essencial

Somente atendimentos considerados **concluídos** devem contar para fidelidade.

Não devem contar automaticamente:

- cancelados;
- faltas;
- agendamentos ainda futuros;
- atendimentos excluídos/inválidos;
- benefícios utilizados como cortesia, quando configurado para não contar.

---

## 4.2 Aniversário do cliente

O sistema deverá permitir identificar aniversariantes e oferecer benefícios definidos pela empresa.

Exemplos:

- desconto no mês do aniversário;
- serviço adicional;
- brinde;
- cupom;
- benefício válido por alguns dias.

### Informações necessárias

No cadastro do cliente, deverá existir:

- data de nascimento;
- preferência/consentimento de comunicação, quando aplicável.

A data de nascimento não deve ser obrigatória para o funcionamento normal do cadastro.

---

## 4.3 Indique e Ganhe

O sistema deverá permitir registrar a relação entre:

- cliente indicador;
- cliente indicado.

O benefício não deve ser liberado apenas porque alguém informou uma indicação.

A condição inicial sugerida é:

> o benefício do indicador é liberado quando o indicado concluir o primeiro atendimento elegível.

### Pontos que deverão ser configuráveis futuramente

- benefício de quem indicou;
- benefício do indicado;
- condição para validação;
- validade;
- quantidade máxima de indicações;
- campanhas ativas;
- serviços elegíveis.

---

# 5. Evoluções futuras

Depois da validação da primeira fase, o sistema poderá evoluir para recursos mais inteligentes.

## 5.1 Recorrência por serviço

Alguns serviços possuem frequência natural de retorno.

Exemplos:

- corte;
- coloração;
- manutenção;
- barba;
- banho;
- procedimentos estéticos;
- sessões recorrentes.

A plataforma deverá permitir configurar uma sugestão de retorno sem conhecer o significado do serviço.

Exemplo:

```text
Serviço: Corte
Retorno sugerido: 30 dias
```

Outro estabelecimento poderá ter:

```text
Serviço: Banho
Retorno sugerido: 15 dias
```

A regra pertence ao serviço/empresa, não ao segmento do sistema.

---

## 5.2 Clientes sem retorno

O sistema poderá identificar clientes que não retornaram dentro de determinado período.

Exemplos:

- cliente sem atendimento há 30 dias;
- cliente sem atendimento há 60 dias;
- cliente sem atendimento há 90 dias;
- cliente com recorrência prevista vencida.

Isso permitirá campanhas de reativação.

---

## 5.3 Frequência de atendimento

A plataforma poderá calcular:

- número de atendimentos;
- intervalo médio entre atendimentos;
- data do último atendimento;
- data prevista de retorno;
- serviços mais utilizados;
- profissional mais utilizado;
- valor médio gasto, caso o módulo financeiro exista;
- frequência recente versus frequência histórica.

Essas informações devem preferencialmente ser **calculadas a partir do histórico**, evitando duplicação desnecessária de dados.

---

## 5.4 Promoções segmentadas

No futuro, a empresa poderá selecionar públicos com base em condições.

Exemplos:

- aniversariantes da semana;
- clientes que não retornam há 60 dias;
- clientes que já utilizaram determinado serviço;
- clientes que concluíram X atendimentos;
- clientes com benefício pendente;
- clientes sem próximo agendamento;
- clientes com recorrência vencida.

---

## 5.5 Pacotes

Poderá existir no futuro a venda de pacotes.

Exemplos:

- 4 sessões;
- 8 atendimentos;
- pacote mensal;
- pacote de manutenção;
- combinação de serviços.

Será necessário controlar:

- quantidade adquirida;
- quantidade utilizada;
- saldo;
- validade;
- serviços aceitos;
- histórico de consumo;
- cancelamentos e ajustes.

Pacotes não devem ser misturados prematuramente com fidelidade. São conceitos relacionados, porém diferentes.

---

# 6. Fundamentos que precisam ser preservados desde já

Mesmo sem desenvolver fidelização agora, algumas decisões do núcleo operacional devem permitir essa evolução.

## 6.1 Histórico de atendimentos

O histórico não deve ser descartado.

Cada agendamento deve permitir conhecer:

- cliente;
- profissional;
- serviço(s);
- data e hora;
- status;
- conclusão;
- cancelamento;
- eventual falta;
- alterações relevantes.

Esse histórico será a principal fonte para:

- fidelidade;
- recorrência;
- reativação;
- relatórios;
- promoções;
- comportamento de clientes.

---

## 6.2 Status confiável do agendamento

A plataforma deve diferenciar claramente situações como:

- agendado;
- confirmado;
- em atendimento;
- concluído;
- cancelado;
- não compareceu.

A fidelidade futura depende dessa informação.

Um agendamento criado não pode ser tratado automaticamente como um atendimento realizado.

---

## 6.3 Serviços identificáveis

Os serviços devem permanecer identificáveis historicamente, mesmo que sejam:

- renomeados;
- desativados;
- alterados posteriormente.

Mudanças futuras não podem destruir a interpretação de atendimentos passados.

---

## 6.4 Benefícios precisam ter histórico

Quando essa fase for implementada, o sistema não deve apenas calcular “o cliente tem direito”.

Deverá existir histórico para saber:

- quando o benefício foi conquistado;
- por qual regra;
- quando foi utilizado;
- em qual atendimento;
- quando expirou;
- se foi cancelado;
- se houve ajuste manual;
- quem realizou o ajuste.

Isso evita inconsistências e fraudes.

---

# 7. Modelo conceitual futuro

Não representa ainda o schema definitivo do banco.

A ideia conceitual é separar:

```text
REGRAS
  ↓
EVENTOS / ATENDIMENTOS
  ↓
PROGRESSO DO CLIENTE
  ↓
BENEFÍCIOS GERADOS
  ↓
UTILIZAÇÃO DO BENEFÍCIO
```

Exemplo:

```text
Regra:
10 atendimentos elegíveis = 1 benefício

Cliente:
9 atendimentos concluídos
        ↓
realiza o 10º
        ↓
benefício gerado
        ↓
benefício disponível
        ↓
cliente utiliza
        ↓
benefício marcado como utilizado
```

---

# 8. Regras configuráveis, não hardcoded

Recursos de fidelização devem ser configuráveis pela empresa.

Evitar código como:

```text
se serviço = corte e quantidade = 10
```

Preferir conceito como:

```text
regra:
tipo = quantidade_atendimentos
quantidade = 10
serviços_elegíveis = [...]
benefício = ...
```

Essa decisão permitirá reutilizar o mesmo mecanismo em diferentes segmentos.

---

# 9. Uso do histórico versus campos duplicados

Sempre que possível, informações como:

- último atendimento;
- número total de atendimentos;
- frequência;
- serviços mais usados;

devem ser obtidas do histórico.

Campos redundantes só devem ser adicionados quando houver necessidade real de desempenho e com uma estratégia clara de sincronização.

---

# 10. Comunicação com clientes

Promoção e fidelização inevitavelmente poderão gerar comunicação com clientes.

A arquitetura futura deve permitir separar:

- geração da oportunidade;
- benefício;
- envio da comunicação.

Exemplo:

```text
Sistema identifica aniversariante
        ↓
gera oportunidade/benefício
        ↓
empresa decide comunicar
        ↓
canal escolhido
```

Isso evita acoplar fidelização diretamente a WhatsApp, e-mail ou outro canal específico.

---

# 11. Consentimento e privacidade

O sistema deve prever tratamento responsável dos dados do cliente.

Quando recursos de comunicação forem implementados, será necessário considerar:

- consentimento/preferências de contato;
- possibilidade de não receber comunicações promocionais;
- uso adequado da data de nascimento;
- registro do motivo da comunicação;
- proteção dos dados entre empresas.

Nenhuma empresa poderá acessar clientes, campanhas ou benefícios pertencentes a outro tenant.

---

# 12. Regras antifraude e consistência

Antes da implementação definitiva, deverão ser tratados cenários como:

- cancelar um atendimento que já gerou benefício;
- alterar um serviço depois da conclusão;
- benefício usado e depois atendimento estornado;
- cliente duplicado;
- indicação circular;
- cliente indicando a si próprio;
- múltiplas indicações para o mesmo cliente;
- ajustes manuais pelo administrador;
- exclusão/desativação de serviço;
- reabertura de atendimento concluído.

Esses casos não precisam ser desenvolvidos agora, mas não podem ser ignorados na modelagem futura.

---

# 13. Métricas futuras

Quando o produto estiver maduro, os recursos de fidelização poderão gerar indicadores úteis.

Exemplos:

- clientes ativos;
- clientes recuperados;
- taxa de retorno;
- benefícios conquistados;
- benefícios utilizados;
- indicações realizadas;
- indicações convertidas em atendimento;
- aniversariantes atendidos após campanha;
- clientes recorrentes;
- intervalo médio entre atendimentos;
- campanhas com melhor resultado.

Essas métricas devem servir ao proprietário para tomar decisões simples, e não transformar o sistema em uma ferramenta analítica excessivamente complexa.

---

# 14. Princípios de UX

Os mecanismos devem ser fáceis de entender tanto para a empresa quanto para o cliente.

Evitar:

- regras difíceis de explicar;
- dezenas de configurações obrigatórias;
- telas complexas;
- necessidade de treinamento pesado.

Objetivo:

> o proprietário deve conseguir criar uma regra simples de fidelidade em poucos minutos.

Exemplo ideal:

```text
A cada [10] atendimentos do serviço [Corte]
o cliente ganha [1 Corte Grátis]
validade do benefício: [60 dias]
```

---

# 15. Fases sugeridas

## Fase A — Núcleo operacional

Status: **prioridade atual**

- empresa;
- serviços;
- profissionais;
- clientes;
- disponibilidade;
- agenda;
- conclusão de atendimentos;
- histórico confiável;
- experiência responsiva.

---

## Fase B — Fidelização simples

Implementar somente depois do núcleo validado.

- fidelidade X atendimentos → benefício;
- aniversário;
- indique e ganhe;
- histórico de benefícios;
- utilização de benefícios.

---

## Fase C — Relacionamento

Após uso real e feedback.

- clientes sem retorno;
- recorrência sugerida;
- lembrete de manutenção;
- segmentação básica;
- oportunidades de reativação.

---

## Fase D — Evolução comercial

Somente se os clientes do produto demonstrarem necessidade.

- campanhas;
- pacotes;
- regras combinadas;
- automações;
- relatórios de fidelização;
- recursos mais avançados de recorrência.

---

# 16. O que não fazer agora

Para proteger o escopo atual:

- não desenvolver campanhas;
- não criar automações promocionais;
- não construir motor complexo de regras;
- não desenvolver pontos;
- não criar carteira digital;
- não desenvolver pacote avançado;
- não integrar disparos de marketing;
- não antecipar recursos que ainda não foram validados.

O trabalho atual deve apenas manter o banco e as regras operacionais preparados para receber essas funcionalidades depois.

---

# 17. Critério para evolução

Novos recursos devem surgir principalmente de:

1. uso real do produto;
2. feedback dos estabelecimentos;
3. problemas recorrentes observados;
4. demanda comercial;
5. impacto sobre retenção e receita;
6. esforço de implementação versus valor entregue.

A plataforma deve crescer conforme o sucesso do produto, evitando excesso de funcionalidades antes da validação.

---

# 18. Visão de produto

A agenda é o ponto inicial, não necessariamente o limite do produto.

Com histórico suficiente, a plataforma poderá ajudar a empresa a responder perguntas como:

- quais clientes estão deixando de voltar?
- quem deveria retornar nas próximas semanas?
- quais clientes são mais frequentes?
- quem está perto de ganhar um benefício?
- quem faz aniversário?
- quais indicações realmente se converteram em clientes?
- quais serviços geram maior recorrência?
- quais ações fizeram clientes retornarem?

O objetivo futuro é que a plataforma não apenas organize horários, mas também ajude o estabelecimento a **manter relacionamento, estimular retorno e aumentar recorrência**, sem perder a simplicidade operacional.

---

## 19. Decisão atual

Neste momento:

> **documentar, preservar a arquitetura e não desenvolver.**

Primeiro o núcleo operacional deve funcionar muito bem.

Depois, com o produto em uso, fidelização e recorrência serão evoluídas de forma incremental e orientada por resultados.
