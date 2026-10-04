/**
 * Paginas de error (resources/views/errors/*.blade.php): cuenta atras
 * visible antes de volver al inicio.
 *
 * Archivo externo porque la CSP (script-src 'self') bloquea los <script>
 * en linea; antes el contador se quedaba fijo en "3". La redireccion real
 * la hace igualmente <meta http-equiv="refresh">, asi que funciona aunque
 * este script no cargue.
 */
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('countdown');
    if (!el) return;

    var seconds = parseInt(el.textContent, 10) || 3;
    var timer = setInterval(function () {
        seconds--;
        el.textContent = Math.max(seconds, 0);
        if (seconds <= 0) clearInterval(timer);
    }, 1000);
});
