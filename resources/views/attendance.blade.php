@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/attendance.css') }}">
@endsection

@section('content')
    @if ($statusCode === 'working')
        <livewire:current-date-time status="出勤中" />

        <div class="stamp-actions">
            <form class="stamp-action-form" action="{{ route('attendance.check_out') }}" name="stamp-form" method="post">
                @csrf
                <button class="stamp-button bg-dark" type="submit" value="check_out">退勤</button>
            </form>

            <form class="stamp-action-form" action="{{ route('attendance.break_in') }}" name="stamp-form" method="post">
                @csrf
                <button class="stamp-button bg-light" type="submit" value="break_in">休憩入</button>
            </form>
        </div>
    @elseif ($statusCode === 'on_break')
        <livewire:current-date-time status="休憩中" />

        <form class="stamp-action-form" action="{{ route('attendance.break_out') }}" name="stamp-form" method="post">
            @csrf
            <button class="stamp-button bg-light" type="submit" value="break_out">休憩戻</button>
        </form>
    @elseif ($statusCode === 'finished')
        <livewire:current-date-time status="退勤済" />
        <p class="status-message">お疲れ様でした。</p>
    @else
        <livewire:current-date-time status="勤務外" />

        <form class="stamp-action-form" action="{{ route('attendance.check_in') }}" name="stamp-form" method="post">
            @csrf
            <button class="stamp-button bg-dark" type="submit" value="check_in">出勤</button>
        </form>
    @endif
@endsection
