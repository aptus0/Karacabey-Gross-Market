<div {{ $attributes->merge(['class' => 'rounded-lg border bg-card text-card-foreground shadow-sm']) }}>
    @if(isset($header) || isset($title) || isset($description))
    <div class="flex flex-col space-y-1.5 p-6">
        @if(isset($title))
            <h3 class="font-semibold leading-none tracking-tight">{{ $title }}</h3>
        @endif
        @if(isset($description))
            <p class="text-sm text-muted-foreground">{{ $description }}</p>
        @endif
        {{ $header ?? '' }}
    </div>
    @endif
    <div class="p-6 pt-0">
        {{ $slot }}
    </div>
    @if(isset($footer))
    <div class="flex items-center p-6 pt-0">
        {{ $footer }}
    </div>
    @endif
</div>
