@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/lists.css') }}">
@endsection

@section('content')
    <h1 class="title">スタッフ一覧</h1>
    <div class="list-table-container">
        <table class="list-table">
            <thead class="list-table-header">
                <tr class="list-table-header-row">
                    <th class="list-table-header-cell list-table-cell-nowrap">名前</th>
                    <th class="list-table-header-cell">メールアドレス</th>
                    <th class="list-table-header-cell">月次勤怠</th>
                </tr>
            </thead>
            <tbody class="list-table-body">
                @foreach ($users as $user)
                    <tr class="list-table-row">
                        <td class="list-table-cell list-table-cell-nowrap">{{ $user->name }}</td>
                        <td class="list-table-cell">{{ $user->email }}</td>
                        <td class="list-table-cell"><a class="detail-button" href="{{ route('admin.attendance.list', $user) }}">詳細</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
