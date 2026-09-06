(function () {
    'use strict';

    var form = document.getElementById('loginForm');

    if (!form) {
        return;
    }

    form.addEventListener(
        'submit',
        function (event) {

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