@props(['code', 'size' => 'h-6.5 w-6.5'])

@php
    $uid = 'flag-' . $code . '-' . substr(md5(random_int(0, PHP_INT_MAX)), 0, 6);
@endphp

<span class="inline-flex {{ $size }} shrink-0 overflow-hidden rounded-full border border-black/10">
    @switch($code)
        @case('TZ')
            <svg viewBox="0 0 40 40" class="h-full w-full" xmlns="http://www.w3.org/2000/svg">
                <clipPath id="{{ $uid }}"><circle cx="20" cy="20" r="20"/></clipPath>
                <g clip-path="url(#{{ $uid }})">
                    <rect width="40" height="40" fill="#1EB53A"/>
                    <polygon points="40,0 40,40 0,40" fill="#00A3DD"/>
                    <line x1="-4" y1="44" x2="44" y2="-4" stroke="#FCD116" stroke-width="11"/>
                    <line x1="-4" y1="44" x2="44" y2="-4" stroke="#000000" stroke-width="5"/>
                </g>
            </svg>
            @break
        @case('NG')
            <svg viewBox="0 0 40 40" class="h-full w-full" xmlns="http://www.w3.org/2000/svg">
                <clipPath id="{{ $uid }}"><circle cx="20" cy="20" r="20"/></clipPath>
                <g clip-path="url(#{{ $uid }})">
                    <rect width="40" height="40" fill="#ffffff"/>
                    <rect width="13.4" height="40" x="0" fill="#008751"/>
                    <rect width="13.4" height="40" x="26.6" fill="#008751"/>
                </g>
            </svg>
            @break
        @case('UG')
            <svg viewBox="0 0 40 40" class="h-full w-full" xmlns="http://www.w3.org/2000/svg">
                <clipPath id="{{ $uid }}"><circle cx="20" cy="20" r="20"/></clipPath>
                <g clip-path="url(#{{ $uid }})">
                    <rect width="40" height="6.67" y="0" fill="#000000"/>
                    <rect width="40" height="6.67" y="6.67" fill="#FCDC04"/>
                    <rect width="40" height="6.67" y="13.33" fill="#D90000"/>
                    <rect width="40" height="6.67" y="20" fill="#000000"/>
                    <rect width="40" height="6.67" y="26.67" fill="#FCDC04"/>
                    <rect width="40" height="6.67" y="33.33" fill="#D90000"/>
                    <circle cx="20" cy="20" r="7.5" fill="#ffffff"/>
                    <circle cx="20" cy="20" r="4" fill="#D90000"/>
                </g>
            </svg>
            @break
        @case('ZM')
            <svg viewBox="0 0 40 40" class="h-full w-full" xmlns="http://www.w3.org/2000/svg">
                <clipPath id="{{ $uid }}"><circle cx="20" cy="20" r="20"/></clipPath>
                <g clip-path="url(#{{ $uid }})">
                    <rect width="40" height="40" fill="#198A00"/>
                    <rect width="4.7" height="20" x="25.3" y="20" fill="#DE2010"/>
                    <rect width="4.7" height="20" x="30" y="20" fill="#000000"/>
                    <rect width="4.7" height="20" x="34.7" y="20" fill="#EF7D00"/>
                    <circle cx="33" cy="10" r="6" fill="#EF7D00"/>
                </g>
            </svg>
            @break
        @case('SS')
            <svg viewBox="0 0 40 40" class="h-full w-full" xmlns="http://www.w3.org/2000/svg">
                <clipPath id="{{ $uid }}"><circle cx="20" cy="20" r="20"/></clipPath>
                <g clip-path="url(#{{ $uid }})">
                    <rect width="40" height="13.33" y="0" fill="#000000"/>
                    <rect width="40" height="1.5" y="13.33" fill="#ffffff"/>
                    <rect width="40" height="10.34" y="14.83" fill="#D21034"/>
                    <rect width="40" height="1.5" y="25.17" fill="#ffffff"/>
                    <rect width="40" height="13.33" y="26.67" fill="#078930"/>
                    <polygon points="0,0 0,40 18,20" fill="#0F47AF"/>
                    <polygon points="7,17 8.2,20.2 11.6,20.2 8.9,22.2 9.9,25.4 7,23.4 4.1,25.4 5.1,22.2 2.4,20.2 5.8,20.2" fill="#FCDD09"/>
                </g>
            </svg>
            @break
    @endswitch
</span>
