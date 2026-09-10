(function () {
    'use strict';

    var form = document.getElementById('cadastroEmpresaForm');

    if (!form) {
        return;
    }

    var tipoDocumento = document.getElementById('tipo_documento');
    var documento = document.getElementById('documento');

    var adminCpf = document.getElementById('admin_cpf');
    var adminTelefone = document.getElementById('admin_telefone');

    var telefoneEmpresa = document.getElementById('telefone_empresa');
    var whatsappEmpresa = document.getElementById('whatsapp_empresa');

    var cep = document.getElementById('cep');
    var cepFeedback = document.getElementById('cepFeedback');

    var logradouro = document.getElementById('logradouro');
    var bairro = document.getElementById('bairro');
    var cidade = document.getElementById('cidade');
    var estado = document.getElementById('estado');
    var numero = document.getElementById('numero');


    function somenteNumeros(valor) {
        return valor.replace(/\D/g, '');
    }


    function mascaraCPF(valor) {
        valor = somenteNumeros(valor).slice(0, 11);

        valor = valor.replace(
            /(\d{3})(\d)/,
            '$1.$2'
        );

        valor = valor.replace(
            /(\d{3})(\d)/,
            '$1.$2'
        );

        valor = valor.replace(
            /(\d{3})(\d{1,2})$/,
            '$1-$2'
        );

        return valor;
    }


    function mascaraCNPJ(valor) {
        valor = somenteNumeros(valor).slice(0, 14);

        valor = valor.replace(
            /^(\d{2})(\d)/,
            '$1.$2'
        );

        valor = valor.replace(
            /^(\d{2})\.(\d{3})(\d)/,
            '$1.$2.$3'
        );

        valor = valor.replace(
            /\.(\d{3})(\d)/,
            '.$1/$2'
        );

        valor = valor.replace(
            /(\d{4})(\d)/,
            '$1-$2'
        );

        return valor;
    }


    function mascaraTelefone(valor) {
        valor = somenteNumeros(valor).slice(0, 11);

        if (valor.length <= 10) {
            valor = valor.replace(
                /^(\d{2})(\d)/,
                '($1) $2'
            );

            valor = valor.replace(
                /(\d{4})(\d)/,
                '$1-$2'
            );

            return valor;
        }

        valor = valor.replace(
            /^(\d{2})(\d)/,
            '($1) $2'
        );

        valor = valor.replace(
            /(\d{5})(\d)/,
            '$1-$2'
        );

        return valor;
    }


    function mascaraCep(valor) {
        valor = somenteNumeros(valor).slice(0, 8);

        return valor.replace(
            /^(\d{5})(\d)/,
            '$1-$2'
        );
    }


    function validarCPF(cpf) {
        cpf = somenteNumeros(cpf);

        if (cpf.length !== 11) {
            return false;
        }

        if (/^(\d)\1+$/.test(cpf)) {
            return false;
        }

        var soma = 0;
        var resto;

        for (var i = 1; i <= 9; i++) {
            soma += (
                parseInt(cpf.substring(i - 1, i), 10) *
                (11 - i)
            );
        }

        resto = (soma * 10) % 11;

        if (resto === 10 || resto === 11) {
            resto = 0;
        }

        if (
            resto !==
            parseInt(cpf.substring(9, 10), 10)
        ) {
            return false;
        }

        soma = 0;

        for (var j = 1; j <= 10; j++) {
            soma += (
                parseInt(cpf.substring(j - 1, j), 10) *
                (12 - j)
            );
        }

        resto = (soma * 10) % 11;

        if (resto === 10 || resto === 11) {
            resto = 0;
        }

        return (
            resto ===
            parseInt(cpf.substring(10, 11), 10)
        );
    }


    function validarCNPJ(cnpj) {
        cnpj = somenteNumeros(cnpj);

        if (cnpj.length !== 14) {
            return false;
        }

        if (/^(\d)\1+$/.test(cnpj)) {
            return false;
        }

        function calcularDigito(base, pesos) {
            var soma = 0;

            for (var i = 0; i < pesos.length; i++) {
                soma += (
                    parseInt(base[i], 10) *
                    pesos[i]
                );
            }

            var resto = soma % 11;

            return resto < 2 ? 0 : 11 - resto;
        }

        var base = cnpj.substring(0, 12);

        var primeiro = calcularDigito(
            base,
            [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
        );

        var segundo = calcularDigito(
            base + primeiro,
            [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
        );

        return (
            parseInt(cnpj[12], 10) === primeiro &&
            parseInt(cnpj[13], 10) === segundo
        );
    }


    function validarDocumentoEmpresa() {
        if (!documento.value.trim()) {
            documento.setCustomValidity(
                'Informe o documento.'
            );
            return;
        }

        if (tipoDocumento.value === 'cpf') {
            if (!validarCPF(documento.value)) {
                documento.setCustomValidity(
                    'CPF inválido.'
                );
            } else {
                documento.setCustomValidity('');
            }

            return;
        }

        if (tipoDocumento.value === 'cnpj') {
            if (!validarCNPJ(documento.value)) {
                documento.setCustomValidity(
                    'CNPJ inválido.'
                );
            } else {
                documento.setCustomValidity('');
            }

            return;
        }

        documento.setCustomValidity(
            'Selecione CPF ou CNPJ.'
        );
    }


    function validarCpfAdministrador() {
        if (!adminCpf.value.trim()) {
            adminCpf.setCustomValidity(
                'Informe o CPF.'
            );
            return;
        }

        if (!validarCPF(adminCpf.value)) {
            adminCpf.setCustomValidity(
                'CPF inválido.'
            );
        } else {
            adminCpf.setCustomValidity('');
        }
    }


    function definirFeedbackCep(mensagem, tipo) {
        if (!cepFeedback) {
            return;
        }

        cepFeedback.textContent = mensagem;

        cepFeedback.classList.remove(
            'text-muted',
            'text-danger',
            'text-success'
        );

        if (tipo === 'erro') {
            cepFeedback.classList.add('text-danger');
            return;
        }

        if (tipo === 'sucesso') {
            cepFeedback.classList.add('text-success');
            return;
        }

        cepFeedback.classList.add('text-muted');
    }


    function definirCamposLocalizacaoEditaveis(editaveis) {
        cidade.readOnly = !editaveis;
        estado.readOnly = !editaveis;
    }


    function limparEndereco() {
        logradouro.value = '';
        bairro.value = '';
        cidade.value = '';
        estado.value = '';
    }


    function consultarCep() {
        var cepNumerico = somenteNumeros(cep.value);

        if (cepNumerico.length === 0) {
            cep.setCustomValidity('');
            definirFeedbackCep('', '');
            limparEndereco();
            definirCamposLocalizacaoEditaveis(false);
            return;
        }

        if (cepNumerico.length !== 8) {
            cep.setCustomValidity('CEP inválido.');

            definirFeedbackCep(
                'Informe os 8 números do CEP.',
                'erro'
            );

            return;
        }

        cep.setCustomValidity('');

        definirFeedbackCep(
            'Buscando endereço...',
            ''
        );

        fetch(
            'https://viacep.com.br/ws/' +
            cepNumerico +
            '/json/'
        )
            .then(function (response) {
                if (!response.ok) {
                    throw new Error(
                        'Não foi possível consultar o CEP.'
                    );
                }

                return response.json();
            })
            .then(function (dados) {
                if (dados.erro) {
                    limparEndereco();
                    definirCamposLocalizacaoEditaveis(true);

                    cep.setCustomValidity(
                        'CEP não encontrado.'
                    );

                    definirFeedbackCep(
                        'CEP não encontrado. Preencha o endereço manualmente.',
                        'erro'
                    );

                    return;
                }

                cep.setCustomValidity('');

                logradouro.value = dados.logradouro || '';
                bairro.value = dados.bairro || '';
                cidade.value = dados.localidade || '';
                estado.value = dados.uf || '';

                definirCamposLocalizacaoEditaveis(false);

                definirFeedbackCep(
                    'Endereço encontrado.',
                    'sucesso'
                );

                if (numero) {
                    numero.focus();
                }
            })
            .catch(function (erro) {
                console.error(
                    'Erro ViaCEP:',
                    erro
                );

                definirCamposLocalizacaoEditaveis(true);

                cep.setCustomValidity('');

                definirFeedbackCep(
                    'Não foi possível consultar o CEP. ' +
                    'Preencha o endereço manualmente.',
                    'erro'
                );
            });
    }


    if (tipoDocumento && documento) {
        tipoDocumento.addEventListener(
            'change',
            function () {
                documento.value = '';
                documento.setCustomValidity('');

                if (tipoDocumento.value === 'cpf') {
                    documento.placeholder =
                        '000.000.000-00';
                    documento.maxLength = 14;
                } else if (
                    tipoDocumento.value === 'cnpj'
                ) {
                    documento.placeholder =
                        '00.000.000/0000-00';
                    documento.maxLength = 18;
                } else {
                    documento.placeholder =
                        'Selecione o tipo de documento';
                    documento.maxLength = 18;
                }

                validarDocumentoEmpresa();
            }
        );


        documento.addEventListener(
            'input',
            function () {
                if (tipoDocumento.value === 'cpf') {
                    documento.value =
                        mascaraCPF(documento.value);
                }

                if (tipoDocumento.value === 'cnpj') {
                    documento.value =
                        mascaraCNPJ(documento.value);
                }

                validarDocumentoEmpresa();
            }
        );
    }


    if (adminCpf) {
        adminCpf.addEventListener(
            'input',
            function () {
                adminCpf.value =
                    mascaraCPF(adminCpf.value);

                validarCpfAdministrador();
            }
        );
    }


    [
        telefoneEmpresa,
        whatsappEmpresa,
        adminTelefone
    ].forEach(function (campo) {
        if (!campo) {
            return;
        }

        campo.addEventListener(
            'input',
            function () {
                campo.value =
                    mascaraTelefone(campo.value);
            }
        );
    });


    if (cep) {
        cep.addEventListener(
            'input',
            function () {
                cep.value = mascaraCep(cep.value);

                cep.setCustomValidity('');

                definirFeedbackCep('', '');

                if (
                    somenteNumeros(cep.value).length === 8
                ) {
                    consultarCep();
                }
            }
        );

        cep.addEventListener(
            'blur',
            function () {
                if (
                    somenteNumeros(cep.value).length > 0
                ) {
                    consultarCep();
                }
            }
        );
    }

    form.addEventListener(
        'submit',
        function (event) {
            validarDocumentoEmpresa();
            validarCpfAdministrador();

            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();

                var primeiroInvalido =
                    form.querySelector(':invalid');

                if (primeiroInvalido) {
                    primeiroInvalido.focus();
                }
            }

            form.classList.add('was-validated');
        },
        false
    );

})();