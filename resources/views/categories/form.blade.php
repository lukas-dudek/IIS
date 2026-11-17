@extends('layouts.app')

@section('title', $category === null ? 'Návrh nové kategorie' : 'Úprava návrhu kategorie')

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('proposals.index') }}">Moje návrhy</a></li>
            <li class="breadcrumb-item active" aria-current="page">
                {{ $category === null ? 'Nová kategorie' : $category->name }}
            </li>
        </ol>
    </nav>

    <h1 class="h4 mb-3">{{ $category === null ? 'Návrh nové kategorie' : 'Úprava návrhu kategorie' }}</h1>

    @if($category !== null && $category->rejection_reason)
        <div class="alert alert-warning">
            <span class="fw-semibold">Návrh byl zamítnut:</span> {{ $category->rejection_reason }}
        </div>
    @endif

    <form method="post"
          action="{{ $category === null ? route('categories.store') : route('categories.update-proposal', $category) }}"
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
                <div class="form-text">Kategorie může být nejvýše 4 úrovně hluboko.</div>
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
            <button type="submit" class="btn btn-primary">Odeslat návrh</button>
            <a class="btn btn-link" href="{{ route('proposals.index') }}">Zrušit</a>
        </div>
    </form>
@endsection
