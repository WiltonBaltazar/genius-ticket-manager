<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Check-in — {{ config('app.name', 'Genius Behind the Brands') }}</title>
    @vite(['resources/css/app.css', 'resources/js/checkin.tsx'])
</head>
<body>
    <div id="checkin-root"></div>
</body>
</html>
