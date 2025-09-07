<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex justify-between items-center">
            <!-- Left side: Company name -->
            <div>
                <h2 class="text-lg font-bold text-gray-800">{{ $companyName }}</h2>
                <p class="text-xs text-gray-400 mt-1">Software built by {{ $companyName }} Pvt. Ltd.</p>
            </div>

            <!-- Right side: Company website -->
            <div>
                <a href="{{ $companyWeb }}" target="_blank"
                    class="flex items-center gap-2 text-sm font-semibold text-blue-600 hover:underline border border-blue-600 p-3 rounded-xl">
                    <x-heroicon-o-globe-alt class="w-5 h-5" /> <!-- Web icon -->
                    Visit our website
                </a>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
