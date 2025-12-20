<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sesión caducada</title>
  <!-- CSS de Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f4f6f9;
      color: #333;
    }
    .error-card {
      max-width: 480px;
      margin: 80px auto;
    }
  </style>
</head>
<body>
  <div class="card error-card shadow-sm">
    <div class="card-body text-center">
      <h1 class="card-title display-5 mb-3">Sesión caducada</h1>
      <p class="card-text lead mb-4">
        Tu sesión ha caducado o no es válida.<br>
        Por favor <a href="{{ route('login') }}" class="fw-semibold">inicia sesión de nuevo</a>.
      </p>
      <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">Volver atrás</a>
    </div>
  </div>
  <!-- Paquete Bootstrap JS (opcional) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
