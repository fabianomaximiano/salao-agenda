document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var form = document.getElementById('dadosEmpresaForm');
    var telefone = document.getElementById('telefone_empresa');
    var whatsapp = document.getElementById('whatsapp_empresa');
    var cep = document.getElementById('cep');

    function numeros(valor) {
        return valor.replace(/\D/g, '');
    }

    function telefoneMascara(valor) {
        var n = numeros(valor).slice(0, 11);

        if (n.length <= 10) {
            return n.replace(/^(\d{0,2})(\d{0,4})(\d{0,4}).*/, function (_, ddd, p1, p2) {
                var r = ddd ? '(' + ddd : '';
                if (ddd.length === 2) r += ') ';
                r += p1;
                if (p2) r += '-' + p2;
                return r;
            });
        }

        return n.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
    }

    [telefone, whatsapp].forEach(function (campo) {
        if (!campo) return;
        campo.value = telefoneMascara(campo.value);
        campo.addEventListener('input', function () {
            campo.value = telefoneMascara(campo.value);
        });
    });

    if (cep) {
        var n = numeros(cep.value).slice(0, 8);
        cep.value = n.length > 5 ? n.slice(0, 5) + '-' + n.slice(5) : n;
    }

    if (window.ConsultaCep) {
        window.ConsultaCep.iniciar();
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    }
});
