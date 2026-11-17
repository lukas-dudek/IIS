@foreach($nodes as $node)
    <option value="{{ $node->id }}" @selected((int) $selected === (int) $node->id)>{{ str_repeat('— ', $depth - 1) }}{{ $node->name }}</option>
    @if($node->children->isNotEmpty())
        @include('partials.category-options', ['nodes' => $node->children, 'depth' => $depth + 1, 'selected' => $selected])
    @endif
@endforeach
