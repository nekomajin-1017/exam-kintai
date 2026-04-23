<div>
    {{-- 申請一覧のページ見出し。 --}}
    <h1 class="title">申請一覧</h1>

    {{-- タブUI（承認待ち/承認済み）の表示。 --}}
    @include('partials.layout.tab', [
        'tabRoute' => $tabRoute,
        'tab' => $tab,
    ])

    {{-- 申請一覧テーブル本体。管理者/一般で列や遷移先を切り替える。 --}}
    @include('partials.application_list_table', [
        'applications' => $applications,
        'isAdmin' => $isAdmin,
        'detailRouteName' => $detailRouteName,
    ])
</div>
