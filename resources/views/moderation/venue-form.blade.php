@extends('layouts.app')

@section('title', $venue === null ? 'Nové místo' : 'Úprava místa')

@section('content')
    @include('moderation.nav')

    <h1 class="h4 mb-3">{{ $venue === null ? 'Nové schválené místo' : 'Úprava místa' }}</h1>

    <form method="post"
          action="{{ $venue === null ? route('moderation.venues.store') : route('moderation.venues.update', $venue) }}"
          enctype="multipart/form-data" class="card card-body" novalidate>
        @csrf

        <div class="row g-3">
            <div class="col-md-8">
                <label for="name" class="form-label">Název <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                       value="{{ $name }}" required maxlength="120">
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4">
                <label for="postal_code" class="form-label">PSČ <span class="text-muted">(nepovinné)</span></label>
                <input type="text" class="form-control @error('postal_code') is-invalid @enderror" id="postal_code"
                       name="postal_code" value="{{ $postalCode }}" placeholder="123 45" maxlength="10">
                @error('postal_code')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="street" class="form-label">Ulice a číslo <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('street') is-invalid @enderror" id="street" name="street"
                       value="{{ $street }}" required maxlength="120">
                @error('street')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="city" class="form-label">Město <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('city') is-invalid @enderror" id="city" name="city"
                       value="{{ $city }}" required maxlength="80">
                @error('city')
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
            <div class="col-12">
                <label for="photo" class="form-label">Fotografie <span class="text-muted">(nepovinné)</span></label>
                <input type="file" class="form-control @error('photo') is-invalid @enderror" id="photo" name="photo"
                       accept="image/jpeg,image/png,image/webp">
                <div class="form-text">JPEG, PNG nebo WebP, nejvýše 2 MB.</div>
                @error('photo')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <p class="small text-muted mt-3">* povinné pole</p>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Uložit</button>
            <a class="btn btn-link"
               href="{{ $venue === null ? route('moderation.venues') : route('moderation.venue', $venue) }}">Zrušit</a>
        </div>
    </form>
@endsection
