@props([
    'active' => false,
])

<li class=" {{ $active ? 'text-gray-800 dark:underline font-extrabold' : '' }}">
    <a {{ $attributes->merge([
            'class' => 'block',
            '@click' => 'setNav = false'
        ]) }}>
            {{ $slot }}
    </a>
</li>
