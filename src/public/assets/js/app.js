(function () {
    'use strict';

    var menuButton = document.getElementById('appMobileMenu');
    var overlay = document.getElementById('appOverlay');

    function abrirMenu() {
        document.body.classList.add('sidebar-open');
    }

    function fecharMenu() {
        document.body.classList.remove('sidebar-open');
    }

    if (menuButton) {
        menuButton.addEventListener('click', function () {

            if (document.body.classList.contains('sidebar-open')) {
                fecharMenu();
            } else {
                abrirMenu();
            }

        });
    }

    if (overlay) {
        overlay.addEventListener('click', fecharMenu);
    }

    document.addEventListener('keydown', function (event) {

        if (event.key === 'Escape') {
            fecharMenu();
        }

    });

})();