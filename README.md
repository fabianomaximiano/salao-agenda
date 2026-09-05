# 📅 Salão Agenda

> Plataforma de agendamento e gestão para negócios que trabalham
> com serviços com hora marcada.

---

## 📑 Sumário

- [Sobre o projeto](#-sobre-o-projeto)
- [Segmentos atendidos](#-segmentos-atendidos)
- [Arquitetura](#️-arquitetura)
- [Perfis de acesso](#-perfis-de-acesso)
- [Modelo de dados](#️-modelo-de-dados)
- [API](#-api)
- [Tecnologias](#️-tecnologias)
- [Instalação](#-instalação)
- [Roadmap](#️-roadmap)

---

## 🎯 Sobre o projeto

Texto...

### Objetivos

- item;
- item;
- item.

---

## 🏢 Segmentos atendidos

| Segmento | Suporte |
|---|:---:|
| 💇 Cabelos | ✅ |
| 💅 Unhas | ✅ |
| 💈 Barbearia | ✅ |
| 🧖 Estética | ✅ |
| 🐾 Pet Shop | ✅ |

---

## 🏗️ Arquitetura

```text
┌──────────────────────┐
│      WORDPRESS       │
│ Marketing • SEO      │
└──────────┬───────────┘
           │
           ▼ API
┌──────────────────────┐
│ SISTEMA DE AGENDA    │
│ PHP • MySQL          │
└──────────────────────┘

[!IMPORTANT]
WordPress e sistema de agendamento são aplicações independentes.

👥 Perfis de acesso
👤 Cliente

...

✂️ Profissional

...

🛡️ Administrativo

...

🗄️ Modelo de dados

...

🚀 Instalação

...

🗺️ Roadmap
 Docker
 OAuth
 Schema v2
 Migração
 Cadastro de empresas
 Agenda

Aí sim estamos falando a mesma língua.

**Não quero transformar seu README em meia dúzia de parágrafos.** Quero manter toda a explicação que construímos, mas aplicar recursos próprios do Markdown/GitHub:

**sumário navegável**, tabelas, separadores, blockquotes, callouts `> [!NOTE]`, `> [!IMPORTANT]`, checkboxes, blocos `text`, blocos `http`, hierarquia correta `# → ## → ###`, destaques e espaçamento consistente.

Ou seja:

> **conteúdo completo + Markdown bem diagramado**, e não um documento corrido que parece ter sido exportado do Word.

Essa é a correção que precisamos fazer no `README.md`.