@props([
    'active' => false,
    'icon' => null,
])

<li class="border-opacity-30 py-2 {{ $active ? 'bg-gray-300' : '' }}">
    <a {{ $attributes->merge([
            'class' => 'block',
            '@click' => 'setNav = false'
        ]) }}>
        <div class="flex items-center justify-between px-3">
            <div class="flex items-center space-x-3">
                @if($icon)
                    <x-dynamic-component 
                        :component="$icon" 
                        class="w-5 h-5 {{ $active ? 'text-blue-600' : 'text-gray-600' }}" 
                    />
                @endif
                <p class="font-semibold text-base {{ $active ? 'text-blue-600' : 'text-gray-600' }}">
                    {{ $slot }}
                </p>
            </div>
        </div>
    </a>
</li>
