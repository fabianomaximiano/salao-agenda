(function () {
    'use strict';

    function somenteDigitos(valor, limite) {
        return String(valor || '')
            .replace(/\D/g, '')
            .slice(0, limite);
    }

    function mascaraTelefone(valor) {
        var digitos = somenteDigitos(valor, 11);

        if (digitos.length > 10) {
            return digitos.replace(/^(\d{2})(\d{5})(\d{0,4}).*$/, '($1) $2-$3');
        }

        if (digitos.length > 6) {
            return digitos.replace(/^(\d{2})(\d{4})(\d{0,4}).*$/, '($1) $2-$3');
        }

        if (digitos.length > 2) {
            return digitos.replace(/^(\d{2})(\d{0,5}).*$/, '($1) $2');
        }

        if (digitos.length > 0) {
            return '(' + digitos;
        }

        return '';
    }

    var telefone = document.getElementById('telefone');

    if (telefone) {
        telefone.value = mascaraTelefone(telefone.value);

        telefone.addEventListener('input', function () {
            telefone.value = mascaraTelefone(telefone.value);
        });

        telefone.addEventListener('blur', function () {
            telefone.value = mascaraTelefone(telefone.value);
        });
    }

    var estado = document.getElementById('estado');

    if (estado) {
        estado.addEventListener('input', function () {
            estado.value = estado.value
                .replace(/[^a-zA-Z]/g, '')
                .slice(0, 2)
                .toUpperCase();
        });
    }

    if (window.ConsultaCep && typeof window.ConsultaCep.iniciar === 'function') {
        window.ConsultaCep.iniciar();
    }

    var formulario = document.querySelector('.colaborador-dados-form');

    if (formulario) {
        formulario.addEventListener('submit', function (event) {
            if (!formulario.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }

            formulario.classList.add('was-validated');
        });
    }
}());
