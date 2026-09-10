(function (window) {
    'use strict';

    var API_BASE =
        'https://servicodados.ibge.gov.br/api/v1/localidades';

    var cacheMunicipios = {};

    var selectEstado = null;
    var selectCidade = null;

    var promessaEstados = null;


    function carregarEstados() {
        if (promessaEstados) {
            return promessaEstados;
        }

        selectEstado.disabled = true;

        selectEstado.innerHTML =
            '<option value="">Carregando estados...</option>';

        promessaEstados = fetch(
            API_BASE + '/estados?orderBy=nome'
        )
            .then(function (response) {
                if (!response.ok) {
                    throw new Error(
                        'Não foi possível carregar os estados.'
                    );
                }

                return response.json();
            })
            .then(function (estados) {
                selectEstado.innerHTML =
                    '<option value="">Selecione o estado</option>';

                estados.forEach(function (estado) {
                    var option =
                        document.createElement('option');

                    option.value = estado.sigla;

                    option.textContent =
                        estado.sigla +
                        ' - ' +
                        estado.nome;

                    selectEstado.appendChild(option);
                });

                selectEstado.disabled = false;

                return estados;
            })
            .catch(function (erro) {
                /*
                 * Permite uma nova tentativa futura.
                 */
                promessaEstados = null;

                selectEstado.innerHTML =
                    '<option value="">Erro ao carregar estados</option>';

                selectEstado.disabled = false;

                throw erro;
            });

        return promessaEstados;
    }


    function preencherMunicipios(municipios) {
        selectCidade.innerHTML =
            '<option value="">Selecione a cidade</option>';

        municipios.forEach(function (municipio) {
            var option =
                document.createElement('option');

            option.value = municipio.nome;
            option.textContent = municipio.nome;

            selectCidade.appendChild(option);
        });

        selectCidade.disabled = false;
    }


    function carregarMunicipios(uf) {
        if (!uf) {
            selectCidade.innerHTML =
                '<option value="">' +
                'Selecione primeiro o estado' +
                '</option>';

            selectCidade.disabled = true;

            return Promise.resolve([]);
        }

        selectCidade.disabled = true;

        selectCidade.innerHTML =
            '<option value="">Carregando cidades...</option>';

        if (cacheMunicipios[uf]) {
            preencherMunicipios(
                cacheMunicipios[uf]
            );

            return Promise.resolve(
                cacheMunicipios[uf]
            );
        }

        return fetch(
            API_BASE +
            '/estados/' +
            encodeURIComponent(uf) +
            '/municipios?orderBy=nome'
        )
            .then(function (response) {
                if (!response.ok) {
                    throw new Error(
                        'Não foi possível carregar as cidades.'
                    );
                }

                return response.json();
            })
            .then(function (municipios) {
                cacheMunicipios[uf] =
                    municipios;

                preencherMunicipios(
                    municipios
                );

                return municipios;
            })
            .catch(function (erro) {
                selectCidade.innerHTML =
                    '<option value="">' +
                    'Erro ao carregar cidades' +
                    '</option>';

                selectCidade.disabled = false;

                throw erro;
            });
    }


    function selecionar(uf, cidade) {
        return carregarEstados()
            .then(function () {
                selectEstado.value = uf;

                return carregarMunicipios(uf);
            })
            .then(function () {
                if (!cidade) {
                    return;
                }

                selectCidade.value = cidade;

                if (
                    selectCidade.value !== cidade
                ) {
                    console.warn(
                        'Cidade não encontrada:',
                        cidade
                    );
                }
            });
    }


    function iniciar(config) {
        selectEstado =
            document.querySelector(
                config.estado
            );

        selectCidade =
            document.querySelector(
                config.cidade
            );

        if (!selectEstado || !selectCidade) {
            return Promise.resolve();
        }

        selectCidade.disabled = true;

        selectEstado.addEventListener(
            'change',
            function () {
                carregarMunicipios(
                    selectEstado.value
                ).catch(function (erro) {
                    console.error(
                        'Erro ao carregar municípios:',
                        erro
                    );
                });
            }
        );

        return carregarEstados();
    }


    window.LocalizacaoBrasil = {
        iniciar: iniciar,
        selecionar: selecionar,
        carregarMunicipios: carregarMunicipios
    };

})(window);