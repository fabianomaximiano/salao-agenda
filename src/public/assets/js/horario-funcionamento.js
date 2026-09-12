document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-dia-toggle]').forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            var dia = this.getAttribute('data-dia-toggle');
            var area = document.querySelector('[data-dia-periodos="' + dia + '"]');
            var card = this.closest('.horario-dia');
            var status = card ? card.querySelector('.horario-dia-status') : null;
            var inicio1 = document.getElementById('inicio_' + dia + '_1');
            var fim1 = document.getElementById('fim_' + dia + '_1');

            if (!area) {
                return;
            }

            area.hidden = !this.checked;

            if (card) {
                card.classList.toggle('is-open', this.checked);
            }

            if (status) {
                status.textContent = this.checked ? 'Aberto' : 'Fechado';
            }

            if (inicio1) {
                inicio1.required = this.checked;
            }

            if (fim1) {
                fim1.required = this.checked;
            }
        });
    });

    document.querySelectorAll('[data-limpar-periodo]').forEach(function (botao) {
        botao.addEventListener('click', function () {
            var dia = this.getAttribute('data-limpar-periodo');
            var inicio = document.getElementById('inicio_' + dia + '_2');
            var fim = document.getElementById('fim_' + dia + '_2');

            if (inicio) {
                inicio.value = '';
            }

            if (fim) {
                fim.value = '';
            }
        });
    });
});
