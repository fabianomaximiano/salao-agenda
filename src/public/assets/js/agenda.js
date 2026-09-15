document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('agendaFilters');
    var profissional = document.getElementById('agendaProfissional');
    var servico = document.getElementById('agendaServico');

    if (form && profissional) profissional.addEventListener('change', function () { form.submit(); });
    if (form && servico) servico.addEventListener('change', function () { form.submit(); });

    var modal = document.getElementById('agendaSpecialModal');
    var specialForm = document.getElementById('agendaSpecialForm');
    if (!modal || !specialForm) return;

    var dateInput = document.getElementById('agendaSpecialDate');
    var dateLabel = document.getElementById('agendaSpecialDateLabel');
    var description = document.getElementById('agendaSpecialDescription');
    var periodsBox = document.getElementById('agendaSpecialPeriods');
    var times = specialForm.querySelectorAll('input[type="time"]');

    function updatePeriods() {
        var selected = specialForm.querySelector('input[name="acao"]:checked');
        var visible = selected && selected.value === 'horario_especial';
        periodsBox.hidden = !visible;
        times[0].required = !!visible;
        times[1].required = !!visible;
    }

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('agenda-modal-open');
    }

    document.querySelectorAll('[data-special-date]').forEach(function (button) {
        button.addEventListener('click', function () {
            var periods = [];
            try { periods = JSON.parse(button.dataset.specialPeriods || '[]'); } catch (e) {}

            dateInput.value = button.dataset.specialDate || '';
            dateLabel.textContent = button.dataset.specialLabel || '';
            description.value = button.dataset.specialDescription || '';
            times.forEach(function (input) { input.value = ''; });

            if (periods[0]) {
                times[0].value = periods[0].inicio || '';
                times[1].value = periods[0].fim || '';
            }
            if (periods[1]) {
                times[2].value = periods[1].inicio || '';
                times[3].value = periods[1].fim || '';
            }

            if (button.dataset.specialType === 'fechado') {
                document.getElementById('specialClosed').checked = true;
            } else if (button.dataset.specialType === 'horario_especial') {
                document.getElementById('specialHours').checked = true;
            } else {
                document.getElementById('specialNormal').checked = true;
            }

            updatePeriods();
            modal.hidden = false;
            document.body.classList.add('agenda-modal-open');
        });
    });

    specialForm.querySelectorAll('input[name="acao"]').forEach(function (radio) {
        radio.addEventListener('change', updatePeriods);
    });

    document.querySelectorAll('[data-special-close]').forEach(function (button) {
        button.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) closeModal();
    });
});
