(function () {
    'use strict';

    function atualizarServico(toggle) {
        var card = toggle.closest('.cadastro-profissional-servico');

        if (!card) {
            return;
        }

        var area = card.querySelector('.js-servico-personalizacao');

        if (!area) {
            return;
        }

        var campos = area.querySelectorAll('input');
        var habilitado = toggle.checked;

        area.classList.toggle('is-disabled', !habilitado);

        campos.forEach(function (campo) {
            campo.disabled = !habilitado;
        });
    }

    document.querySelectorAll('.js-servico-toggle').forEach(function (toggle) {
        atualizarServico(toggle);

        toggle.addEventListener('change', function () {
            atualizarServico(toggle);
        });
    });

    var form = document.getElementById('cadastroProfissionalForm');

    if (form) {
        form.addEventListener('submit', function () {
            var botao = document.getElementById('btnSalvarProfissional');

            if (botao) {
                botao.disabled = true;
                botao.textContent = 'Salvando...';
            }
        });
    }
}());
