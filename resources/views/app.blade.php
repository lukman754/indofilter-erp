<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Indofilter ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app">
        <div id="app-loading" style="display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:'Inter',sans-serif;color:#714B67;font-size:1.25rem;font-weight:600">
            Memuat...
        </div>
    </div>
    <script>
        window.addEventListener('error', function(e) {
            const el = document.getElementById('app-loading');
            if (el) el.textContent = 'Terjadi kesalahan: ' + (e.message || e.error?.message || 'unknown');
        });
    </script>
</body>
</html>
