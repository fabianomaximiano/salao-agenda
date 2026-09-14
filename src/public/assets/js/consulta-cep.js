(function (window, document) {
    'use strict';

    function somenteNumeros(valor) {
        return String(valor || '').replace(/\D/g, '');
    }

    function mascaraCep(valor) {
        valor = somenteNumeros(valor).slice(0, 8);

        return valor.replace(
            /^(\d{5})(\d)/,
            '$1-$2'
        );
    }

    function iniciar(opcoes) {
        opcoes = opcoes || {};

        var cep = document.getElementById(opcoes.cepId || 'cep');
        var feedback = document.getElementById(
            opcoes.feedbackId || 'cepFeedback'
        );
        var logradouro = document.getElementById(
            opcoes.logradouroId || 'logradouro'
        );
        var bairro = document.getElementById(
            opcoes.bairroId || 'bairro'
        );
        var cidade = document.getElementById(
            opcoes.cidadeId || 'cidade'
        );
        var estado = document.getElementById(
            opcoes.estadoId || 'estado'
        );
        var numero = document.getElementById(
            opcoes.numeroId || 'numero'
        );
        var complemento = document.getElementById(
            opcoes.complementoId || 'complemento'
        );

        var consultaAtual = 0;
        var cepEmConsulta = '';

        if (!cep || !logradouro || !bairro || !cidade || !estado) {
            return;
        }

        function definirFeedback(mensagem, tipo) {
            if (!feedback) {
                return;
            }

            feedback.textContent = mensagem;

            feedback.classList.remove(
                'text-muted',
                'text-danger',
                'text-success'
            );

            if (tipo === 'erro') {
                feedback.classList.add('text-danger');
                return;
            }

            if (tipo === 'sucesso') {
                feedback.classList.add('text-success');
                return;
            }

            feedback.classList.add('text-muted');
        }

        function definirLocalizacaoEditavel(editavel) {
            cidade.readOnly = !editavel;
            estado.readOnly = !editavel;
        }

        function limparEndereco(limparDadosComplementares) {
            logradouro.value = '';
            bairro.value = '';
            cidade.value = '';
            estado.value = '';

            if (limparDadosComplementares) {
                if (numero) {
                    numero.value = '';
                }

                if (complemento) {
                    complemento.value = '';
                }
            }
        }

        function consultar() {
            var cepNumerico = somenteNumeros(cep.value);

            if (cepNumerico.length === 0) {
                consultaAtual++;
                cepEmConsulta = '';

                cep.setCustomValidity('');
                definirFeedback('', '');
                limparEndereco(true);
                definirLocalizacaoEditavel(false);
                return;
            }

            if (cepNumerico.length !== 8) {
                consultaAtual++;
                cepEmConsulta = '';

                cep.setCustomValidity('CEP inválido.');

                definirFeedback(
                    'Informe os 8 números do CEP.',
                    'erro'
                );

                return;
            }

            if (cepEmConsulta === cepNumerico) {
                return;
            }

            var idConsulta = ++consultaAtual;
            cepEmConsulta = cepNumerico;

            limparEndereco(true);
            definirLocalizacaoEditavel(false);

            cep.setCustomValidity(
                'Aguarde a consulta do CEP.'
            );

            definirFeedback(
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
                    if (
                        idConsulta !== consultaAtual ||
                        cepNumerico !== somenteNumeros(cep.value)
                    ) {
                        return;
                    }

                    cepEmConsulta = '';

                    if (dados.erro) {
                        limparEndereco(true);
                        definirLocalizacaoEditavel(true);

                        cep.setCustomValidity(
                            'CEP não encontrado.'
                        );

                        definirFeedback(
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

                    definirLocalizacaoEditavel(false);

                    definirFeedback(
                        'Endereço encontrado.',
                        'sucesso'
                    );

                    if (numero) {
                        numero.focus();
                    }
                })
                .catch(function (erro) {
                    if (
                        idConsulta !== consultaAtual ||
                        cepNumerico !== somenteNumeros(cep.value)
                    ) {
                        return;
                    }

                    cepEmConsulta = '';

                    console.error(
                        'Erro ViaCEP:',
                        erro
                    );

                    limparEndereco(true);
                    definirLocalizacaoEditavel(true);

                    cep.setCustomValidity('');

                    definirFeedback(
                        'Não foi possível consultar o CEP. ' +
                        'Preencha o endereço manualmente.',
                        'erro'
                    );
                });
        }

        cep.addEventListener(
            'input',
            function () {
                consultaAtual++;
                cepEmConsulta = '';

                cep.value = mascaraCep(cep.value);

                cep.setCustomValidity('');
                definirFeedback('', '');

                limparEndereco(true);
                definirLocalizacaoEditavel(false);

                if (
                    somenteNumeros(cep.value).length === 8
                ) {
                    consultar();
                }
            }
        );

        cep.addEventListener(
            'keydown',
            function (event) {
                if (event.key !== 'Enter') {
                    return;
                }

                event.preventDefault();
                consultar();
            }
        );

        cep.addEventListener(
            'blur',
            function () {
                if (
                    somenteNumeros(cep.value).length > 0
                ) {
                    consultar();
                }
            }
        );
    }

    window.ConsultaCep = {
        iniciar: iniciar
    };

})(window, document);
