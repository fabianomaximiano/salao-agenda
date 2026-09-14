(function () {
    'use strict';

    if (window.ConsultaCep) {
        window.ConsultaCep.iniciar();
    }

    var form = document.getElementById('meusDadosClienteForm');
    var telefone = document.getElementById('telefone');
    var estado = document.getElementById('estado');

    function somenteDigitos(valor) {
        return String(valor || '').replace(/\D/g, '');
    }

    function mascararTelefone(valor) {
        var numeros = somenteDigitos(valor).slice(0, 11);

        if (numeros.length <= 10) {
            return numeros
                .replace(/(\d{2})(\d)/, '($1) $2')
                .replace(/(\d{4})(\d)/, '$1-$2');
        }

        return numeros
            .replace(/(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{5})(\d)/, '$1-$2');
    }

    if (telefone) {
        telefone.addEventListener('input', function () {
            telefone.value = mascararTelefone(telefone.value);
        });
    }

    if (estado) {
        estado.addEventListener('input', function () {
            estado.value = estado.value
                .replace(/[^a-zA-Z]/g, '')
                .slice(0, 2)
                .toUpperCase();
        });
    }

    if (!form) {
        return;
    }

    form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();

            var invalido = form.querySelector(':invalid');

            if (invalido) {
                invalido.focus();
            }
        }

        form.classList.add('was-validated');
    }, false);
})();
