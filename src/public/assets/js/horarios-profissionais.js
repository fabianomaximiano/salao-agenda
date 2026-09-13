document.addEventListener('DOMContentLoaded', function () {
    var selector = document.getElementById('profissionalSelector');
    var selectorForm = document.getElementById('profissionalSelectorForm');
    var copyButton = document.getElementById('copiarHorarioEmpresa');
    var horariosEmpresa = window.SALAO_HORARIOS_EMPRESA || {};

    function updateDay(dayElement) {
        var toggle = dayElement.querySelector('.hp-day-toggle');
        var status = dayElement.querySelector('.hp-day-status');
        var inputs = dayElement.querySelectorAll('.hp-time-input');
        var companyClosed = dayElement.classList.contains('is-company-closed');
        var enabled = toggle && toggle.checked && !companyClosed;

        dayElement.classList.toggle('is-enabled', enabled);

        if (status) {
            status.textContent = enabled ? 'Disponível' : 'Indisponível';
        }

        inputs.forEach(function (input) {
            input.disabled = !enabled;

            if (!enabled) {
                input.value = '';
            }
        });
    }

    if (selector && selectorForm) {
        selector.addEventListener('change', function () {
            selectorForm.submit();
        });
    }

    document.querySelectorAll('.hp-day').forEach(function (dayElement) {
        var toggle = dayElement.querySelector('.hp-day-toggle');

        if (!toggle) {
            return;
        }

        toggle.addEventListener('change', function () {
            updateDay(dayElement);
        });

        updateDay(dayElement);
    });

    if (copyButton) {
        copyButton.addEventListener('click', function () {
            document.querySelectorAll('.hp-day').forEach(function (dayElement) {
                var day = dayElement.getAttribute('data-day');
                var toggle = dayElement.querySelector('.hp-day-toggle');
                var inputsInicio = dayElement.querySelectorAll('input[name^="inicio"]');
                var inputsFim = dayElement.querySelectorAll('input[name^="fim"]');
                var periodos = horariosEmpresa[day] || horariosEmpresa[Number(day)] || [];

                if (!toggle || toggle.disabled) {
                    return;
                }

                toggle.checked = periodos.length > 0;
                updateDay(dayElement);

                inputsInicio.forEach(function (input, index) {
                    input.value = periodos[index] ? periodos[index].inicio : '';
                });

                inputsFim.forEach(function (input, index) {
                    input.value = periodos[index] ? periodos[index].fim : '';
                });
            });
        });
    }
});
