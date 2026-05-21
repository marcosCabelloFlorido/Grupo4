/* theme-init.js — Aplica el tema ANTES del renderizado para evitar flash
   Incluir en el <head> de cada página, ANTES de los CSS */
(function() {
    try {
        var saved = localStorage.getItem('valtasy-theme');
        // Si no hay tema guardado, usar 'light' como predeterminado
        var theme = (saved === 'dark' || saved === 'light') ? saved : 'light';
        document.documentElement.setAttribute('data-theme', theme);
    } catch(e) {
        document.documentElement.setAttribute('data-theme', 'light');
    }
})();
