@props(['label', 'note' => null, 'src' => null, 'icon' => 'car'])
<figure class="y-tile">
    <span class="y-tile-photo" @if ($src) style="background-image: url('{{ $src }}')" @endif>@unless ($src)<x-site-icon :name="$icon" />@endunless</span>
    <figcaption><strong>{!! nl2br(e($label)) !!}</strong>@if ($note)<small>{{ $note }}</small>@endif</figcaption>
</figure>
