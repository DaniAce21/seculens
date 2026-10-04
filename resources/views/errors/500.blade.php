<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Sin redireccion automatica: si el inicio tambien falla, se entraria en un bucle de recargas. --}}
    <title>500 - Error interno del servidor | SecuLens</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <div class="error-wrapper">
        <div class="error-container">
            <div class="error-code">500</div>
            <h1 class="error-title">Error interno del servidor</h1>
            <p class="error-message">
                Ha ocurrido un error inesperado en el servidor.
                Estamos trabajando para solucionarlo.
            </p>
            <div class="error-actions">
                <a href="{{ url('/') }}" class="btn btn-primary">
                    <i class="bi bi-house-door-fill"></i>
                    Ir a página principal
                </a>
            </div>
        </div>
    </div>
</body>
</html>
