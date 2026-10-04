/**
 * Informe de seguridad: boton "Imprimir / Guardar PDF".
 *
 * Archivo externo porque la CSP de la app bloquea los onclick en linea.
 */
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('printReport');
    if (btn) {
        btn.addEventListener('click', function () {
            window.print();
        });
    }
});
