<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Coachtech 勤怠管理アプリ</title>
    <link rel="stylesheet" href="{{ asset('css/common.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
    @livewireStyles
    @yield('css')
</head>
<body class="app-body">
    @include('partials.layout.header', [
        'headerVariant' => $headerVariant ?? 'user',
    ])
    <main class="main">
        @yield('content')
    </main>
    @livewireScripts
</body>
</html>
