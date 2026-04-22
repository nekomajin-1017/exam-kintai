@props([
    'name',
    'label' => '',
    'type' => 'text',
    'value' => null,
    'useOld' => true,
])

@php
    $fieldValue = $useOld ? old($name, $value) : $value;
    $errorMessage = $errors->first($name);
@endphp

<div class="form-group">
    @if($label !== '')
        <label class="form-label" for="{{ $name }}">{{ $label }}</label>
    @endif

    <input
        id="{{ $name }}"
        class="form-control"
        name="{{ $name }}"
        type="{{ $type }}"
        @if(! is_null($fieldValue) && $type !== 'password')
            value="{{ $fieldValue }}"
        @endif
        {{ $attributes }}
    >

    @if($errorMessage)
        <p class="field-error">{{ $errorMessage }}</p>
    @endif
</div>
