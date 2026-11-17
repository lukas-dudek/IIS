@extends('layouts.app')

@section('title', $category === null ? 'Nová kategorie' : 'Úprava kategorie')

@section('content')
    @include('moderation.nav')

    <h1 class="h4 mb-3">{{ $category === null ? 'Nová schválená kategorie' : 'Úprava kategorie' }}</h1>

    <form method="post"
          action="{{ $category === null ? route('moderation.categories.store') : route('moderation.categories.update', $category) }}"
          class="card card-body" novalidate>
        @csrf

        <div class="row g-3">
            <div class="col-md-6">
                <label for="name" class="form-label">Název <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                       value="{{ $name }}" required maxlength="80">
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="parent_id" class="form-label">Rodičovská kategorie <span class="text-muted">(nepovinné)</span></label>
                <select class="form-select @error('parent_id') is-invalid @enderror" id="parent_id" name="parent_id">
                    <option value="">Kořen stromu</option>
                    @foreach($options as $option)
                        <option value="{{ $option['id'] }}" @selected((int) $parentId === (int) $option['id'])>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">Maximální hloubka stromu jsou 4 úrovně.</div>
                @error('parent_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-12">
                <label for="description" class="form-label">Popis <span class="text-muted">(nepovinné)</span></label>
                <textarea class="form-control @error('description') is-invalid @enderror" id="description"
                          name="description" rows="3" maxlength="2000">{{ $description }}</textarea>
                @error('description')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <p class="small text-muted mt-3">* povinné pole</p>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Uložit</button>
            <a class="btn btn-link"
               href="{{ $category === null ? route('moderation.categories') : route('moderation.category', $category) }}">
                Zrušit
            </a>
        </div>
    </form>
@endsection
