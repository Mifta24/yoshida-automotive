@props(['steps'])
<ol class="y-flow">
    @foreach ($steps as [$icon, $name, $note])
        <li><span class="y-flow-ic"><x-site-icon :name="$icon" /></span><strong>{{ $name }}</strong><small>{{ $note }}</small></li>
    @endforeach
</ol>
