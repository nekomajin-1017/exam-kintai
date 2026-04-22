<div class="calendar-pagination-container">
    <a class="calendar-pagination-button" href="{{ $previousUrl }}">{{ "←" . $previousLabel }}</a>
    <p class="calendar-pagination-center">{{ $currentLabel }}</p>
    <a class="calendar-pagination-button" href="{{ $nextUrl }}">{{ $nextLabel . "→" }}</a>
</div>
