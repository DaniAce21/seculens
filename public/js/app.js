/**
 * SecuLens - Frontend interactions.
 * Solo maneja presentación: sidebar responsive, confirmaciones,
 * y feedback visual. Toda la autorización vive en el servidor.
 */
document.addEventListener('DOMContentLoaded', function () {

    // Auto-ocultar alerts después de 5 segundos
    document.querySelectorAll('.alert').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-8px)';
            setTimeout(function () { alert.remove(); }, 400);
        }, 5000);
    });

    // Confirmar acciones destructivas
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!window.confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    // Hover sutil en filas de tabla (feedback visual)
    document.querySelectorAll('.table tbody tr').forEach(function (row) {
        row.addEventListener('mouseenter', function () {
            row.style.transition = 'background 0.15s ease';
        });
    });

    // Contador animado en stat-cards
    document.querySelectorAll('.stat-value').forEach(function (el) {
        var target = parseInt(el.textContent, 10);
        if (isNaN(target) || target === 0) return;

        var duration = 800;
        var start = 0;
        var startTime = null;

        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            var progress = Math.min((timestamp - startTime) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.round(start + (target - start) * eased);
            if (progress < 1) requestAnimationFrame(step);
        }

        requestAnimationFrame(step);
    });

});
