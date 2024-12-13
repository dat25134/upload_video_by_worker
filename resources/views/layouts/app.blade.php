<!DOCTYPE html>
<html>
<head>
    <title>{{ config('app.name', 'Laravel') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Thêm các styles và scripts khác nếu cần -->
</head>
<body>
    @yield('content')
    
    <!-- Include Toast Component -->
    <x-toast-notification />
</body>
</html> 