@extends('layouts.app')

@section('title', 'Nová událost')

@section('content')
    <h1 class="h4 mb-3">{{ $event === null ? 'Nová událost' : 'Úprava události' }}</h1>

    @if($event !== null && $event->status === 'rejected' && $event->rejection_reason)
        <div class="alert alert-warning">
            <p class="mb-1 fw-semibold">Událost byla zamítnuta.</p>
            <p class="mb-0">{{ $event->rejection_reason }}</p>
        </div>
    @endif

    <form method="post"
          action="{{ $event === null ? route('events.store') : route('events.update', $event) }}"
          enctype="multipart/form-data" class="card card-body" novalidate>
        @csrf

        <div class="row g-3">
            <div class="col-md-8">
                <label for="title" class="form-label">Název události <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title"
                       value="{{ $title }}" required maxlength="140">
                @error('title')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4">
                <label for="capacity" class="form-label">Kapacita <span class="text-muted">(nepovinné)</span></label>
                <input type="number" min="1" class="form-control @error('capacity') is-invalid @enderror" id="capacity"
                       name="capacity" value="{{ $capacity }}">
                <div class="form-text">Prázdné pole znamená neomezenou kapacitu.</div>
                @error('capacity')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="starts_at" class="form-label">Začátek <span class="text-danger">*</span></label>
                <input type="datetime-local" class="form-control @error('starts_at') is-invalid @enderror" id="starts_at"
                       name="starts_at" value="{{ $startsAt }}" required>
                <div class="form-text">Formát dd. mm. rrrr hh:mm (Europe/Prague)</div>
                @error('starts_at')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="ends_at" class="form-label">Konec <span class="text-danger">*</span></label>
                <input type="datetime-local" class="form-control @error('ends_at') is-invalid @enderror" id="ends_at"
                       name="ends_at" value="{{ $endsAt }}" required>
                <div class="form-text">Musí být po začátku.</div>
                @error('ends_at')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="venue_id" class="form-label">Místo konání <span class="text-danger">*</span></label>
                <select class="form-select @error('venue_id') is-invalid @enderror" id="venue_id" name="venue_id" required>
                    <option value="">Vyberte místo</option>
                    @foreach($venues as $venue)
                        <option value="{{ $venue->id }}" @selected((int) $venueId === (int) $venue->id)>
                            {{ $venue->name }} — {{ $venue->city }}
                        </option>
                    @endforeach
                </select>
                @error('venue_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">
                    Chybí vám místo? <a href="{{ route('venues.propose') }}" target="_blank" rel="noopener">Navrhněte nové</a>.
                </div>
            </div>
            <div class="col-md-6">
                <label for="category_id" class="form-label">Kategorie <span class="text-danger">*</span></label>
                <select class="form-select @error('category_id') is-invalid @enderror" id="category_id" name="category_id"
                        required>
                    <option value="">Vyberte kategorii</option>
                    @foreach($categories as $option)
                        <option value="{{ $option['id'] }}" @selected((int) $categoryId === (int) $option['id'])>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
                @error('category_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">
                    Chybí vám kategorie? <a href="{{ route('categories.propose') }}" target="_blank" rel="noopener">Navrhněte novou</a>.
                </div>
            </div>

            <div class="col-12">
                <label for="description" class="form-label">Popis <span class="text-danger">*</span></label>
                <textarea class="form-control @error('description') is-invalid @enderror" id="description"
                          name="description" rows="5" required maxlength="5000">{{ $description }}</textarea>
                @error('description')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <label for="cover" class="form-label">Fotografie události <span class="text-muted">(nepovinné)</span></label>
                <input type="file" class="form-control @error('cover') is-invalid @enderror" id="cover" name="cover"
                       accept="image/jpeg,image/png,image/webp">
                <div class="form-text">JPEG, PNG nebo WebP, nejvýše 2 MB. Zobrazí se v seznamu událostí.</div>
                @error('cover')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                @if($event?->cover_path)
                    <p class="small text-muted mt-2 mb-0">Nahráním nové fotografie nahradíte současnou.</p>
                @endif
            </div>

            <div class="col-12">
                <label for="payment_note" class="form-label">Platební instrukce <span class="text-muted">(nepovinné)</span></label>
                <textarea class="form-control @error('payment_note') is-invalid @enderror" id="payment_note"
                          name="payment_note" rows="2" maxlength="2000"
                          placeholder="Například číslo účtu a způsob platby">{{ $paymentNote }}</textarea>
                <div class="form-text">Zobrazí se účastníkům u placených vstupenek.</div>
                @error('payment_note')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <hr class="my-4">

        <h2 class="h6">Typy vstupenek</h2>
        <p class="small text-muted">
            Cena 0 znamená vstup zdarma a registrace se potvrdí okamžitě. Cena se zadává například
            <span class="text-nowrap">150</span> nebo <span class="text-nowrap">150,50</span>.
        </p>

        @error('tickets')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th scope="col">Název</th>
                    <th scope="col" style="width: 10rem">Cena (Kč)</th>
                    <th scope="col" style="width: 10rem">Kapacita</th>
                </tr>
                </thead>
                <tbody>
                @foreach($tickets as $index => $ticket)
                    <tr>
                        <td>
                            <input type="hidden" name="tickets[{{ $index }}][id]" value="{{ $ticket['id'] }}">
                            <input type="text" class="form-control @error('ticket_name_' . $index) is-invalid @enderror"
                                   name="tickets[{{ $index }}][name]" value="{{ $ticket['name'] }}" maxlength="60"
                                   aria-label="Název typu vstupenky">
                            @error('ticket_name_' . $index)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </td>
                        <td>
                            <input type="text" inputmode="decimal"
                                   class="form-control @error('ticket_price_' . $index) is-invalid @enderror"
                                   name="tickets[{{ $index }}][price]" value="{{ $ticket['price'] }}"
                                   aria-label="Cena">
                            @error('ticket_price_' . $index)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </td>
                        <td>
                            <input type="number" min="1"
                                   class="form-control @error('ticket_capacity_' . $index) is-invalid @enderror"
                                   name="tickets[{{ $index }}][capacity]" value="{{ $ticket['capacity'] }}"
                                   aria-label="Kapacita typu">
                            @error('ticket_capacity_' . $index)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </td>
                    </tr>
                @endforeach
                <tr>
                    <td><input type="text" class="form-control" name="tickets[{{ count($tickets) }}][name]"
                               placeholder="Nový typ vstupenky" maxlength="60" aria-label="Nový typ vstupenky"></td>
                    <td><input type="text" inputmode="decimal" class="form-control"
                               name="tickets[{{ count($tickets) }}][price]" placeholder="0"
                               aria-label="Cena nového typu"></td>
                    <td><input type="number" min="1" class="form-control"
                               name="tickets[{{ count($tickets) }}][capacity]" aria-label="Kapacita nového typu"></td>
                </tr>
                </tbody>
            </table>
        </div>

        <p class="small text-muted">* povinné pole</p>

        <div class="d-flex flex-wrap gap-2">
            <button type="submit" name="submit" value="0" class="btn btn-outline-secondary">Uložit koncept</button>
            <button type="submit" name="submit" value="1" class="btn btn-primary">Odeslat ke schválení</button>
            <a class="btn btn-link" href="{{ $event !== null ? route('events.show', $event->slug) : route('events.mine') }}">Zrušit</a>
        </div>
    </form>
@endsection
