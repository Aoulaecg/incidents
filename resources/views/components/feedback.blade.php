@if (session('status'))
    <div class="alert alert-success status-message d-flex align-items-start gap-2" role="status"><x-icon name="check" /><span>{{ session('status') }}</span></div>
@endif
@if ($errors->any())
    <div class="alert alert-danger status-message" role="alert" tabindex="-1" data-error-summary>
        <strong>Veuillez vérifier les informations suivantes :</strong>
        <ul class="mb-0 mt-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
