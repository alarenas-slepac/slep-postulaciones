<a class="slep-menu-link @if ($link['active']) active @endif" href="{{ route($link['route']) }}"
    @if ($link['active']) aria-current="page" @endif>
    <i class="bi {{ $link['icon'] }}" aria-hidden="true"></i>
    <span>{{ $link['label'] }}</span>
</a>
