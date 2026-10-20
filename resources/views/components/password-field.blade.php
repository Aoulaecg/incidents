@props(['name' => 'password', 'label' => 'Mot de passe', 'autocomplete' => 'new-password'])
<div class="field-group">
    <label for="{{ $name }}" class="form-label">{{ $label }} <span aria-hidden="true">*</span></label>
    <div class="input-with-icon password-wrapper">
        <x-icon name="lock" class="field-icon" />
        <input id="{{ $name }}" name="{{ $name }}" type="password" class="form-control @error($name) is-invalid @enderror" autocomplete="{{ $autocomplete }}" required @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
        <button type="button" class="password-toggle" aria-label="Afficher {{ mb_strtolower($label) }}" aria-controls="{{ $name }}" aria-pressed="false" data-password-toggle hidden><x-icon name="eye" data-eye-show /><x-icon name="eye-off" data-eye-hide hidden /></button>
    </div>
    @error($name)<p id="{{ $name }}-error" class="field-error">{{ $message }}</p>@enderror
</div>
