<div>
    {{-- 勤怠詳細フォーム全体は partial に委譲し、ここはデータ受け渡し専用。 --}}
    @include('partials.attendance_detail_page', [
        'detailFields' => $detailFields,
        'readonly' => $readonly,
        'plainReadonly' => $plainReadonly,
        'formAction' => $formAction,
        'formMethod' => $formMethod,
        'submitLabel' => $submitLabel,
        'submitDisabled' => $submitDisabled,
        'statusMessage' => $statusMessage,
        'statusMessageClass' => $statusMessageClass,
        'title' => $title,
    ])
</div>
