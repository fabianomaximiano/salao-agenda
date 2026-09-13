(function () {
    'use strict';

    function somenteDigitos(valor, limite) {
        return String(valor || '')
            .replace(/\D/g, '')
            .slice(0, limite);
    }

    function mascaraCpf(valor) {
        var digitos = somenteDigitos(valor, 11);

        if (digitos.length > 9) {
            return digitos.replace(/^(\d{3})(\d{3})(\d{3})(\d{0,2}).*$/, '$1.$2.$3-$4');
        }

        if (digitos.length > 6) {
            return digitos.replace(/^(\d{3})(\d{3})(\d{0,3}).*$/, '$1.$2.$3');
        }

        if (digitos.length > 3) {
            return digitos.replace(/^(\d{3})(\d{0,3}).*$/, '$1.$2');
        }

        return digitos;
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

    function aplicarMascara(campo, formatador) {
        if (!campo) {
            return;
        }

        campo.value = formatador(campo.value);

        campo.addEventListener('input', function () {
            campo.value = formatador(campo.value);
        });

        campo.addEventListener('blur', function () {
            campo.value = formatador(campo.value);
        });
    }

    aplicarMascara(document.getElementById('cpf'), mascaraCpf);
    aplicarMascara(document.getElementById('telefone'), mascaraTelefone);
}());
