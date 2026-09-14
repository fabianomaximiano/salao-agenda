(function(){
'use strict';
var form=document.getElementById('loginClienteForm');
var senha=document.getElementById('senha');
var toggle=document.getElementById('senhaToggle');

if(toggle&&senha){
    toggle.addEventListener('click',function(){
        var mostrando=senha.type==='text';
        senha.type=mostrando?'password':'text';
        toggle.setAttribute('aria-pressed',mostrando?'false':'true');
        toggle.setAttribute('aria-label',mostrando?'Mostrar senha':'Ocultar senha');
        toggle.querySelector('span').textContent=mostrando?'◉':'⊘';
        senha.focus();
    });
}

if(!form)return;

form.addEventListener('submit',function(e){
    if(!form.checkValidity()){
        e.preventDefault();
        e.stopPropagation();
        var x=form.querySelector(':invalid');
        if(x)x.focus();
    }
    form.classList.add('was-validated');
},false);
})();