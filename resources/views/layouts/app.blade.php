<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.document-head')
    </head>
    <body class="font-sans antialiased" data-workspace-role="{{ Auth::user()?->role }}">
        @php
            $usesSidebar = Auth::check();
            $isCustomer = Auth::user()?->isCustomer() ?? false;
        @endphp

        <x-page-loading />

        <div class="casa-page casa-app-page">
            @include('layouts.navigation')
            <x-toast-stack />

            <x-modal-host />

            <div @class([
                'min-h-screen',
                'pb-24 lg:pb-0' => $isCustomer,
                'lg:ps-72' => $usesSidebar,
            ])>
                <!-- Page Heading -->
                @isset($header)
                    <header data-page-header class="border-b border-casa-border/80 bg-casa-paper/90 backdrop-blur-xl">
                        <div class="mx-auto flex max-w-[90rem] flex-col gap-4 px-4 py-5 sm:px-6 lg:flex-row lg:items-end lg:justify-between lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main data-page-content class="mx-auto max-w-[90rem] px-4 py-5 sm:px-6 lg:px-8 lg:py-6">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
