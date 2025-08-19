@props([
    'active' => false,
])

<li class=" {{ $active ? 'text-gray-800 font-extrabold' : '' }}">
    <a {{ $attributes->merge([
            'class' => 'block',
            '@click' => 'setNav = false'
        ]) }}>
            {{ $slot }}
    </a>
</li>
