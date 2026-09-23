(() => {
    'use strict';

    const app = document.getElementById('agendamentoRecepcaoApp');
    if (!app) return;

    const api = app.dataset.api;
    const form = document.getElementById('formAgendamentoRecepcao');
    const mensagem = document.getElementById('recepcaoMensagem');
    const cliente = document.getElementById('cliente_id');
    const servico = document.getElementById('servico_id');
    const profissional = document.getElementById('profissional_id');
    const data = document.getElementById('data');
    const hora = document.getElementById('hora');
    const botao = document.getElementById('btnConfirmarAgendamento');

    const resumo = {
        cliente: document.getElementById('resumoCliente'),
        servico: document.getElementById('resumoServico'),
        profissional: document.getElementById('resumoProfissional'),
        data: document.getElementById('resumoData'),
        horario: document.getElementById('resumoHorario'),
        valor: document.getElementById('resumoValor')
    };

    const textoSelecionado = (select) => {
        const option = select.options[select.selectedIndex];
        return option && option.value ? option.textContent.trim() : '—';
    };

    const moeda = (valor) => new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(Number(valor || 0));

    const dataBR = (valor) => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(valor)) return '—';
        const [ano, mes, dia] = valor.split('-');
        return `${dia}/${mes}/${ano}`;
    };

    const mostrarMensagem = (texto, tipo = 'danger') => {
        mensagem.textContent = texto;
        mensagem.className = `alert alert-${tipo}`;
        mensagem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    const limparMensagem = () => {
        mensagem.textContent = '';
        mensagem.className = 'alert d-none';
    };

    const preencherSelect = (select, itens, placeholder, valor, texto, extras = null) => {
        select.innerHTML = '';
        const inicial = document.createElement('option');
        inicial.value = '';
        inicial.textContent = placeholder;
        select.appendChild(inicial);

        itens.forEach((item) => {
            const option = document.createElement('option');
            option.value = item[valor];
            option.textContent = item[texto];
            if (extras) extras(option, item);
            select.appendChild(option);
        });
    };

    const atualizarResumo = () => {
        resumo.cliente.textContent = textoSelecionado(cliente);
        resumo.servico.textContent = textoSelecionado(servico);
        resumo.profissional.textContent = textoSelecionado(profissional);
        resumo.data.textContent = dataBR(data.value);
        resumo.horario.textContent = hora.value || '—';

        const optionProfissional = profissional.options[profissional.selectedIndex];
        resumo.valor.textContent = optionProfissional && optionProfissional.dataset.preco
            ? moeda(optionProfissional.dataset.preco)
            : '—';

        botao.disabled = !(cliente.value && servico.value && profissional.value && data.value && hora.value);
    };

    const requisitar = async (url, opcoes = {}) => {
        const resposta = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            ...opcoes
        });
        const dados = await resposta.json().catch(() => ({ ok: false, mensagem: 'Resposta inválida do servidor.' }));
        if (!resposta.ok || !dados.ok) throw new Error(dados.mensagem || 'Não foi possível concluir a operação.');
        return dados;
    };

    const carregarServicos = async () => {
        servico.disabled = true;
        try {
            const dados = await requisitar(`${api}?acao=servicos`);
            preencherSelect(servico, dados.servicos || [], 'Selecione o serviço', 'id', 'nome');
            servico.disabled = false;
        } catch (erro) {
            preencherSelect(servico, [], 'Não foi possível carregar os serviços', 'id', 'nome');
            mostrarMensagem(erro.message);
        }
    };

    const carregarProfissionais = async () => {
        profissional.disabled = true;
        data.disabled = true;
        hora.disabled = true;
        preencherSelect(profissional, [], 'Carregando profissionais...', 'id', 'nome_completo');
        preencherSelect(hora, [], 'Selecione serviço, profissional e data', 'hora', 'hora');
        data.value = '';

        if (!servico.value) {
            preencherSelect(profissional, [], 'Selecione primeiro o serviço', 'id', 'nome_completo');
            atualizarResumo();
            return;
        }

        try {
            const dados = await requisitar(`${api}?acao=profissionais&servico_id=${encodeURIComponent(servico.value)}`);
            preencherSelect(
                profissional,
                dados.profissionais || [],
                'Selecione o profissional',
                'id',
                'nome_completo',
                (option, item) => {
                    option.dataset.preco = item.preco;
                    option.dataset.duracao = item.duracao_minutos;
                }
            );
            profissional.disabled = false;
        } catch (erro) {
            preencherSelect(profissional, [], 'Não foi possível carregar os profissionais', 'id', 'nome_completo');
            mostrarMensagem(erro.message);
        }
        atualizarResumo();
    };

    const prepararData = () => {
        data.value = '';
        hora.disabled = true;
        preencherSelect(hora, [], 'Selecione a data', 'hora', 'hora');
        data.disabled = !profissional.value;
        atualizarResumo();
    };

    const carregarHorarios = async () => {
        hora.disabled = true;
        preencherSelect(hora, [], 'Carregando horários...', 'hora', 'hora');

        if (!servico.value || !profissional.value || !data.value) {
            preencherSelect(hora, [], 'Selecione serviço, profissional e data', 'hora', 'hora');
            atualizarResumo();
            return;
        }

        try {
            const query = new URLSearchParams({
                acao: 'horarios',
                servico_id: servico.value,
                profissional_id: profissional.value,
                data: data.value
            });
            const dados = await requisitar(`${api}?${query.toString()}`);
            const horarios = dados.horarios || [];
            preencherSelect(
                hora,
                horarios,
                horarios.length ? 'Selecione o horário' : 'Nenhum horário disponível',
                'hora',
                'hora'
            );
            hora.disabled = horarios.length === 0;
        } catch (erro) {
            preencherSelect(hora, [], 'Não foi possível carregar os horários', 'hora', 'hora');
            mostrarMensagem(erro.message);
        }
        atualizarResumo();
    };

    cliente.addEventListener('change', atualizarResumo);
    servico.addEventListener('change', () => {
        limparMensagem();
        carregarProfissionais();
    });
    profissional.addEventListener('change', () => {
        limparMensagem();
        prepararData();
    });
    data.addEventListener('change', () => {
        limparMensagem();
        carregarHorarios();
    });
    hora.addEventListener('change', atualizarResumo);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        limparMensagem();
        atualizarResumo();
        if (botao.disabled) return;

        botao.disabled = true;
        const textoOriginal = botao.textContent;
        botao.textContent = 'Confirmando...';

        try {
            const formData = new FormData(form);
            formData.set('acao', 'confirmar');
            const dados = await requisitar(api, { method: 'POST', body: formData });

            const token = form.querySelector('input[name="csrf_token"]');
            if (dados.csrf_token && token) token.value = dados.csrf_token;

            mostrarMensagem(`${dados.mensagem} Agendamento #${dados.agendamento_id}.`, 'success');
            form.reset();
            profissional.disabled = true;
            data.disabled = true;
            hora.disabled = true;
            preencherSelect(profissional, [], 'Selecione primeiro o serviço', 'id', 'nome_completo');
            preencherSelect(hora, [], 'Selecione serviço, profissional e data', 'hora', 'hora');
            atualizarResumo();
        } catch (erro) {
            mostrarMensagem(erro.message);
            botao.disabled = false;
        } finally {
            botao.textContent = textoOriginal;
            atualizarResumo();
        }
    });

    carregarServicos();
    atualizarResumo();
})();
