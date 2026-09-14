(function () {
    'use strict';

    var form = document.getElementById('meusDadosProfissionalForm');
    if (!form) {
        return;
    }

    function somenteDigitos(valor) {
        return String(valor || '').replace(/\D/g, '');
    }

    function formatarTelefone(valor) {
        var digitos = somenteDigitos(valor).slice(0, 11);

        if (digitos.length <= 10) {
            return digitos
                .replace(/^(\d{0,2})(\d{0,4})(\d{0,4}).*$/, function (_, ddd, parte1, parte2) {
                    var resultado = ddd ? '(' + ddd : '';
                    resultado += ddd.length === 2 ? ') ' : '';
                    resultado += parte1;
                    resultado += parte2 ? '-' + parte2 : '';
                    return resultado;
                });
        }

        return digitos.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
    }

    var telefone = document.getElementById('telefone');
    if (telefone) {
        telefone.value = formatarTelefone(telefone.value);
        telefone.addEventListener('input', function () {
            telefone.value = formatarTelefone(telefone.value);
        });
    }

    var cep = document.getElementById('cep');
    if (cep) {
        var digitosCep = somenteDigitos(cep.value).slice(0, 8);
        if (digitosCep.length > 5) {
            cep.value = digitosCep.slice(0, 5) + '-' + digitosCep.slice(5);
        } else {
            cep.value = digitosCep;
        }
    }

    if (window.ConsultaCep && typeof window.ConsultaCep.iniciar === 'function') {
        window.ConsultaCep.iniciar();
    }

    var foto = document.getElementById('foto');
    var preview = document.getElementById('fotoProfissionalPreview');

    if (foto && preview) {
        foto.addEventListener('change', function () {
            var arquivo = foto.files && foto.files[0] ? foto.files[0] : null;
            var label = foto.nextElementSibling;

            if (label && arquivo) {
                label.textContent = arquivo.name;
            }

            if (!arquivo || !arquivo.type.match(/^image\/(jpeg|png|webp)$/)) {
                return;
            }

            var url = URL.createObjectURL(arquivo);
            preview.innerHTML = '';

            var img = document.createElement('img');
            img.src = url;
            img.alt = 'Prévia da minha foto';
            img.onload = function () {
                URL.revokeObjectURL(url);
            };
            preview.appendChild(img);
        });
    }

    form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });
}());
