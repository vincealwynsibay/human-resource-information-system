<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <script>
        // Apply the saved theme before the first paint, so a dark-mode user never sees a white flash.
        try { const t = localStorage.getItem('hris-theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
