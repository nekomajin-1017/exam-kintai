<div class="detail-page-content">
    <h1 class="title">{{ $title ?? '勤怠詳細' }}</h1>

    @if (!empty($formAction))
        <form class="application-form" method="post" action="{{ $formAction }}">
            @csrf
            @method($formMethod ?? 'PUT')
            @include('partials.attendance_detail_fields', [
                'fields' => $detailFields,
            ])
            @if (!empty($submitLabel))
                <div class="form-actions">
                    <button class="submit-button" type="submit" @if(!empty($submitDisabled)) disabled @endif>{{ $submitLabel }}</button>
                </div>
            @endif
        </form>
    @else
        <div class="application-form">
            @include('partials.attendance_detail_fields', [
                'fields' => $detailFields,
            ])
        </div>
    @endif

    @if (!empty($statusMessage))
        <p class="{{ $statusMessageClass ?? 'progress-message' }}">{{ $statusMessage }}</p>
    @endif
</div>
