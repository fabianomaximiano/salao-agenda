(() => {
    "use strict";

    const c = window.AUTOAGENDAMENTO || {};
    const al = document.querySelector("#agendamentoAlerta");

    function aviso(m, t = "danger") {
        if (!al) {
            alert(m);
            return;
        }

        al.className = `alert alert-${t}`;
        al.textContent = m;
        al.classList.remove("d-none");
    }

    async function get(q) {
        const u = new URL(c.apiUrl, location.href);

        Object.entries(q).forEach(([k, v]) => {
            u.searchParams.set(k, v);
        });

        const r = await fetch(u);
        const j = await r.json();

        if (!r.ok || !j.ok) {
            throw Error(j.mensagem || "Erro.");
        }

        return j;
    }

    async function post(q) {
        const r = await fetch(c.apiUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8"
            },
            body: new URLSearchParams(q)
        });

        const j = await r.json();

        if (!r.ok || !j.ok) {
            throw Error(j.mensagem || "Erro.");
        }

        if (j.csrf_token) {
            c.csrfToken = j.csrf_token;
        }

        return j;
    }

    document.querySelectorAll(".js-cancelar-agendamento").forEach((b) => {
        b.onclick = async () => {
            if (!confirm("Cancelar este agendamento? O horário ficará disponível novamente.")) {
                return;
            }

            b.disabled = true;

            try {
                const j = await post({
                    acao: "cancelar",
                    csrf_token: c.csrfToken,
                    agendamento_id: b.dataset.agendamentoId
                });

                aviso(j.mensagem, "success");
                setTimeout(() => location.reload(), 500);
            } catch (e) {
                aviso(e.message);
                b.disabled = false;
            }
        };
    });

    const s = document.querySelector("#servicoId");
    const p = document.querySelector("#profissionalId");
    const d = document.querySelector("#dataAgendamento");
    const hs = document.querySelector("#horariosDisponiveis");
    const bt = document.querySelector("#confirmarAgendamento");

    if (!s || !p || !d || !hs || !bt) {
        return;
    }

    const rs = document.querySelector("#resumoServico");
    const rp = document.querySelector("#resumoProfissional");
    const rd = document.querySelector("#resumoData");
    const rh = document.querySelector("#resumoHorario");
    const rv = document.querySelector("#resumoValor");

    const rid = Number(c.reagendarId || 0);

    const contextoInicial = (
        c.contextoInicial &&
        typeof c.contextoInicial === "object"
    )
        ? c.contextoInicial
        : {};

    const servicoInicialId = Number(contextoInicial.servicoId || 0);
    let profissionalInicialId = Number(contextoInicial.profissionalId || 0);

    let hora = "";

    const moeda = (v) => Number(v || 0).toLocaleString("pt-BR", {
        style: "currency",
        currency: "BRL"
    });

    function atualizarResumoProfissional() {
        rp.textContent = p.value
            ? (p.options[p.selectedIndex]?.text || "—")
            : "—";

        rv.textContent = p.value
            ? moeda(p.options[p.selectedIndex]?.dataset.preco)
            : "—";
    }

    async function profissionais(id, sel = 0) {
        const j = await get({
            acao: "profissionais",
            servico_id: id
        });

        p.innerHTML = '<option value="">Selecione um profissional</option>';

        j.profissionais.forEach((x) => {
            const o = new Option(
                x.nome_completo,
                x.id,
                false,
                Number(x.id) === Number(sel)
            );

            o.dataset.preco = x.preco;
            p.add(o);
        });

        p.disabled = !j.profissionais.length;
    }

    async function horarios() {
        hora = "";
        bt.disabled = true;
        rh.textContent = "—";
        rd.textContent = d.value
            ? new Date(`${d.value}T12:00`).toLocaleDateString("pt-BR")
            : "—";

        if (!s.value || !p.value || !d.value) {
            return;
        }

        hs.innerHTML = '<span class="text-muted">Consultando...</span>';

        try {
            const q = {
                acao: "horarios",
                servico_id: s.value,
                profissional_id: p.value,
                data: d.value
            };

            if (rid) {
                q.reagendar_id = rid;
            }

            const j = await get(q);

            hs.innerHTML = j.horarios.length
                ? ""
                : '<span class="text-muted">Nenhum horário disponível nesta data.</span>';

            j.horarios.forEach((x) => {
                const b = document.createElement("button");

                b.type = "button";
                b.className = "btn btn-outline-primary agendamento-horario";
                b.textContent = x.hora;

                b.onclick = () => {
                    hs.querySelectorAll("button").forEach((y) => {
                        y.classList.remove("ativo");
                    });

                    b.classList.add("ativo");
                    hora = x.hora;
                    rh.textContent = hora;
                    bt.disabled = false;
                };

                hs.appendChild(b);
            });
        } catch (e) {
            hs.innerHTML = "";
            aviso(e.message);
        }
    }

    async function carregarProfissionaisDoServico(
        servicoId,
        profissionalPreferidoId = 0
    ) {
        p.disabled = true;
        d.disabled = true;

        rp.textContent = "—";
        rd.textContent = "—";
        rh.textContent = "—";
        rv.textContent = "—";

        hora = "";
        bt.disabled = true;

        hs.innerHTML = '<span class="text-muted">Escolha serviço, profissional e data.</span>';

        if (!servicoId) {
            p.innerHTML = '<option value="">Escolha o serviço</option>';
            return;
        }

        await profissionais(servicoId, profissionalPreferidoId);

        if (profissionalPreferidoId && p.value) {
            atualizarResumoProfissional();
            d.disabled = false;
        }
    }

    s.onchange = async () => {
        rs.textContent = s.value
            ? (s.options[s.selectedIndex]?.text || "—")
            : "—";

        try {
            await carregarProfissionaisDoServico(
                s.value,
                profissionalInicialId
            );
        } catch (e) {
            aviso(e.message);
        }
    };

    p.onchange = () => {
        atualizarResumoProfissional();

        d.disabled = !p.value;

        if (d.value && p.value) {
            horarios();
        }
    };

    d.onchange = horarios;

    bt.onclick = async () => {
        bt.disabled = true;

        const original = bt.textContent;
        bt.textContent = rid
            ? "Reagendando..."
            : "Confirmando...";

        const q = {
            acao: rid ? "reagendar" : "confirmar",
            csrf_token: c.csrfToken,
            servico_id: s.value,
            profissional_id: p.value,
            data: d.value,
            hora
        };

        if (rid) {
            q.agendamento_id = rid;
        }

        try {
            const j = await post(q);

            aviso(j.mensagem, "success");

            setTimeout(() => {
                location.href = "meus-agendamentos.php";
            }, 700);
        } catch (e) {
            aviso(e.message);
            bt.disabled = false;
            bt.textContent = original;
            horarios();
        }
    };

    (async () => {
        try {
            const j = await get({
                acao: "servicos"
            });

            s.innerHTML = '<option value="">Selecione um serviço</option>';

            j.servicos.forEach((x) => {
                s.add(new Option(x.nome, x.id));
            });

            /*
             * Reagendamento mantém prioridade absoluta sobre qualquer
             * contexto externo recebido anteriormente.
             */
            if (rid) {
                const z = await get({
                    acao: "detalhe_reagendamento",
                    agendamento_id: rid
                });

                const a = z.agendamento;

                s.value = String(a.servico_id);
                rs.textContent = s.options[s.selectedIndex]?.text || "—";

                await profissionais(
                    a.servico_id,
                    a.profissional_id
                );

                atualizarResumoProfissional();

                d.disabled = false;
                d.value = a.data;

                await horarios();
                return;
            }

            /*
             * Contexto vindo do site.
             *
             * Serviço + profissional:
             * - seleciona o serviço;
             * - carrega os profissionais daquele serviço;
             * - pré-seleciona o profissional validado;
             * - libera a escolha da data.
             *
             * Somente serviço:
             * - seleciona o serviço;
             * - carrega os profissionais;
             * - cliente escolhe o profissional.
             *
             * Somente profissional:
             * - o fluxo continua começando pelo serviço;
             * - o ID do profissional fica preservado em memória;
             * - quando o cliente escolher um serviço compatível,
             *   o profissional será pré-selecionado automaticamente.
             */
            if (servicoInicialId) {
                const servicoExiste = Array.from(s.options).some(
                    (option) => Number(option.value) === servicoInicialId
                );

                if (servicoExiste) {
                    s.value = String(servicoInicialId);
                    rs.textContent = s.options[s.selectedIndex]?.text || "—";

                    await carregarProfissionaisDoServico(
                        servicoInicialId,
                        profissionalInicialId
                    );
                }
            }
        } catch (e) {
            aviso(e.message);
        }
    })();
})();
