<div class="w-full overflow-auto">
    <table {{ $attributes->merge(['class' => 'w-full caption-bottom text-sm']) }}>
        @if(isset($header))
        <thead class="[&_tr]:border-b bg-muted/50">
            {{ $header }}
        </thead>
        @endif
        <tbody class="[&_tr:last-child]:border-0">
            {{ $slot }}
        </tbody>
    </table>
</div>
