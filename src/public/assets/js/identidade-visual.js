(function () {
    'use strict';

    var form = document.getElementById('identidadeVisualForm');
    var logoInput = document.getElementById('logo');
    var previewLogo = document.getElementById('previewLogo');

    function corValida(valor) {
        return /^#[0-9A-F]{6}$/.test(valor);
    }

    function configurarCor(nome, previewId) {
        var texto = document.getElementById(nome);
        var picker = document.getElementById(nome + '_picker');
        var preview = document.getElementById(previewId);

        if (!texto || !picker || !preview) {
            return;
        }

        function atualizarPreview() {
            var valor = texto.value.trim().toUpperCase();
            texto.value = valor;

            if (valor === '') {
                texto.classList.remove('is-invalid');
                preview.style.backgroundColor = 'transparent';
                return;
            }

            if (corValida(valor)) {
                texto.classList.remove('is-invalid');
                picker.value = valor;
                preview.style.backgroundColor = valor;
            } else {
                preview.style.backgroundColor = 'transparent';
            }
        }

        texto.addEventListener('input', atualizarPreview);
        texto.addEventListener('blur', function () {
            var valor = texto.value.trim().toUpperCase();
            texto.value = valor;
            texto.classList.toggle('is-invalid', valor !== '' && !corValida(valor));
        });

        picker.addEventListener('input', function () {
            texto.value = picker.value.toUpperCase();
            texto.classList.remove('is-invalid');
            atualizarPreview();
        });

        atualizarPreview();
    }

    configurarCor('cor_primaria', 'previewCorPrimaria');
    configurarCor('cor_secundaria', 'previewCorSecundaria');

    if (logoInput && previewLogo) {
        logoInput.addEventListener('change', function () {
            var arquivo = logoInput.files && logoInput.files[0];

            if (!arquivo) {
                return;
            }

            if (!/^image\/(jpeg|png|webp)$/.test(arquivo.type)) {
                logoInput.value = '';
                return;
            }

            var reader = new FileReader();
            reader.addEventListener('load', function () {
                previewLogo.innerHTML = '';
                var img = document.createElement('img');
                img.src = String(reader.result);
                img.alt = 'Prévia do novo logo';
                previewLogo.appendChild(img);
            });
            reader.readAsDataURL(arquivo);
        });
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            var campos = [
                document.getElementById('cor_primaria'),
                document.getElementById('cor_secundaria')
            ];
            var invalido = false;

            campos.forEach(function (campo) {
                if (!campo) {
                    return;
                }

                var valor = campo.value.trim().toUpperCase();
                campo.value = valor;
                var erro = valor !== '' && !corValida(valor);
                campo.classList.toggle('is-invalid', erro);
                invalido = invalido || erro;
            });

            if (invalido) {
                event.preventDefault();
            }
        });
    }
})();
