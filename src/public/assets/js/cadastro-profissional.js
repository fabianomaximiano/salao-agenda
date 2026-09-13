(function () {
    'use strict';

    function atualizarServico(toggle) {
        var card = toggle.closest('.cadastro-profissional-servico');

        if (!card) {
            return;
        }

        var area = card.querySelector('.js-servico-personalizacao');

        if (!area) {
            return;
        }

        var campos = area.querySelectorAll('input');
        var habilitado = toggle.checked;

        area.classList.toggle('is-disabled', !habilitado);

        campos.forEach(function (campo) {
            campo.disabled = !habilitado;
        });
    }

    document.querySelectorAll('.js-servico-toggle').forEach(function (toggle) {
        atualizarServico(toggle);

        toggle.addEventListener('change', function () {
            atualizarServico(toggle);
        });
    });

    var form = document.getElementById('cadastroProfissionalForm');
    var fotoInput = document.getElementById('foto');
    var fotoPreview = document.getElementById('fotoProfissionalPreview');

    if (fotoInput && fotoPreview) {
        fotoInput.addEventListener('change', function () {
            var arquivo = fotoInput.files && fotoInput.files[0];
            var label = fotoInput.nextElementSibling;

            if (!arquivo) {
                return;
            }

            if (!/^image\/(jpeg|png|webp)$/.test(arquivo.type) || arquivo.size > 5 * 1024 * 1024) {
                fotoInput.value = '';
                fotoInput.classList.add('is-invalid');
                if (label && label.classList.contains('custom-file-label')) {
                    label.textContent = 'Selecionar foto';
                }
                return;
            }

            fotoInput.classList.remove('is-invalid');
            if (label && label.classList.contains('custom-file-label')) {
                label.textContent = arquivo.name;
            }

            var reader = new FileReader();
            reader.addEventListener('load', function () {
                fotoPreview.innerHTML = '';
                var img = document.createElement('img');
                img.src = String(reader.result);
                img.alt = 'Prévia da nova foto do profissional';
                fotoPreview.appendChild(img);
            });
            reader.readAsDataURL(arquivo);
        });
    }

    if (form) {
        form.addEventListener('submit', function () {
            var botao = document.getElementById('btnSalvarProfissional');

            if (botao) {
                botao.disabled = true;
                botao.textContent = 'Salvando...';
            }
        });
    }
}());
