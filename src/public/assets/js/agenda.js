document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('agendaFilters');
    var profissional = document.getElementById('agendaProfissional');
    var servico = document.getElementById('agendaServico');

    if (form && profissional) {
        profissional.addEventListener('change', function () {
            form.submit();
        });
    }

    if (form && servico) {
        servico.addEventListener('change', function () {
            form.submit();
        });
    }

    document.querySelectorAll('[data-agenda-date]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();

            var data = link.getAttribute('data-agenda-date');

            if (!data) {
                return;
            }

            // A navegação diária será ativada quando o módulo de atendimentos
            // estiver conectado ao novo modelo operacional.
            link.setAttribute('title', 'Dia ' + data + ' preparado para a próxima etapa da agenda');
        });
    });
});
