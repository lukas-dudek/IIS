@if(session('status'))
    <div class="alert alert-success" role="status">{{ session('status') }}</div>
@endif

@if($errors->has('general'))
    <div class="alert alert-danger" role="alert">{{ $errors->first('general') }}</div>
@endif

@if($errors->any() && !$errors->has('general'))
    <div class="alert alert-danger" role="alert">
        <p class="fw-semibold mb-2">Formulář obsahuje chyby:</p>
        <ul class="mb-0">
            @foreach($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
