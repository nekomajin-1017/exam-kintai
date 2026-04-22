@php
    $headerVariant = $headerVariant ?? 'user';
    $hideNav = request()->routeIs('login', 'register', 'admin.login');
    $showNav = auth()->check() && ! $hideNav;
@endphp

<header class="header">
    <div class="header-inner">
        <img class="logo-img img-fluid" src="{{ asset('img/COACHTECHヘッダーロゴ.png') }}" alt="COACHTECH">
        @if ($showNav)
            <nav>
                <ul class="nav-list">
                    @if ($headerVariant === 'admin')
                        <li class="header-nav-item">
                            <a class="nav-link" href="{{ route('admin.dashboard') }}">勤怠一覧</a>
                        </li>
                        <li class="header-nav-item">
                            <a class="nav-link" href="{{ route('admin.staff_list') }}">スタッフ一覧</a>
                        </li>
                        <li class="header-nav-item">
                            <a class="nav-link" href="{{ route('stamp_correction_requests.list') }}">申請一覧</a>
                        </li>
                    @else
                        <li class="header-nav-item">
                            <a class="nav-link" href="{{ route('attendance.index') }}">勤怠</a>
                        </li>
                        <li class="header-nav-item">
                            <a class="nav-link" href="{{ route('attendance.list') }}">勤怠一覧</a>
                        </li>
                        <li class="header-nav-item">
                            <a class="nav-link" href="{{ route('stamp_correction_requests.list') }}">申請</a>
                        </li>
                    @endif

                    <li class="header-nav-item">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="nav-link logout-btn" type="submit">ログアウト</button>
                        </form>
                    </li>
                </ul>
            </nav>
        @endif
        @yield('header')
    </div>
</header>
