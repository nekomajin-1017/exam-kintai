@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/lists.css') }}">
@endsection

@section('content')
    <h1 class="title">スタッフ一覧</h1>
    <x-list>
        <x-slot name="header">
            <th class="list-table-header-cell list-table-cell-nowrap">名前</th>
            <th class="list-table-header-cell">メールアドレス</th>
            <th class="list-table-header-cell">月次勤怠</th>
        </x-slot>
        <x-slot name="body">
            @foreach ($users as $user)
                <tr class="list-table-row">
                    <td class="list-table-cell list-table-cell-nowrap">{{ $user->name }}</td>
                    <td class="list-table-cell">{{ $user->email }}</td>
                    <td class="list-table-cell"><a class="detail-button" href="{{ route('admin.attendance.list', $user) }}">詳細</a></td>
                </tr>
            @endforeach
        </x-slot>
    </x-list>
@endsection
