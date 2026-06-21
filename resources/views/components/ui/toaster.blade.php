<div
    x-data="toaster"
    @toast.window="add($event.detail)"
    class="fixed bottom-0 right-0 z-[100] flex max-h-screen w-full flex-col-reverse p-4 sm:bottom-0 sm:right-0 sm:top-auto sm:flex-col md:max-w-[420px]"
>
    <template x-for="toast in toasts" :key="toast.id">
        <x-ui.toast />
    </template>

    @if (session()->has('status'))
        <div x-init="add({ title: 'Success', description: '{{ session('status') }}', variant: 'default' })"></div>
    @endif
    @if (session()->has('success'))
        <div x-init="add({ title: 'Success', description: '{{ session('success') }}', variant: 'default' })"></div>
    @endif
    @if (session()->has('error'))
        <div x-init="add({ title: 'Error', description: '{{ session('error') }}', variant: 'destructive' })"></div>
    @endif
    @if ($errors->any())
        <div x-init="add({ title: 'Validation Error', description: 'Please check the form for errors.', variant: 'destructive' })"></div>
    @endif
</div>
