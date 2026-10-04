<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="3;url={{ url('/') }}">
    <title>419 - Página expirada | SecuLens</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <div class="error-wrapper">
        <div class="error-container">
            <div class="error-code">419</div>
            <h1 class="error-title">Página expirada</h1>
            <p class="error-message">
                Tu sesión ha expirado. Por favor, vuelve a cargar la página.
                Serás redirigido automáticamente a la página principal.
            </p>
            <div class="error-actions">
                <a href="{{ url('/') }}" class="btn btn-primary">
                    <i class="bi bi-house-door-fill"></i>
                    Ir a página principal
                </a>
            </div>
            <p class="error-redirect">
                Redirigiendo en <span id="countdown">3</span> segundos...
            </p>
        </div>
    </div>
    <script src="{{ asset('js/error-redirect.js') }}"></script>
</body>
</html>
