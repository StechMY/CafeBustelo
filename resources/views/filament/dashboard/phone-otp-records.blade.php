<div class="space-y-4">
    <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800">
        {{ __('filament.phone_otp_records_notice') }}
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-3 py-2">{{ __('filament.phone') }}</th>
                    <th class="px-3 py-2">{{ __('filament.purpose') }}</th>
                    <th class="px-3 py-2">{{ __('filament.otp_code') }}</th>
                    <th class="px-3 py-2">{{ __('filament.status') }}</th>
                    <th class="px-3 py-2">{{ __('filament.expires_at') }}</th>
                    <th class="px-3 py-2">{{ __('filament.created_at') }}</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($records as $record)
                    <tr>
                        <td class="px-3 py-2 font-bold text-gray-900">
                            {{ $record->phone }}
                        </td>

                        <td class="px-3 py-2">
                            {{ $record->purpose }}
                        </td>

                        <td class="px-3 py-2">
                            @if ($record->decrypted_code)
                                <span class="rounded-lg bg-gray-900 px-2 py-1 font-mono text-sm font-black text-white">
                                    {{ $record->decrypted_code }}
                                </span>
                            @else
                                <span class="text-gray-400">
                                    {{ __('filament.not_available') }}
                                </span>
                            @endif
                        </td>

                        <td class="px-3 py-2">
                            @php
                                $status = $record->status_label;
                            @endphp

                            <span @class([
                                'rounded-full px-2 py-1 text-xs font-bold',
                                'bg-green-100 text-green-700' => $status === 'Valid',
                                'bg-gray-100 text-gray-700' => $status === 'Used',
                                'bg-red-100 text-red-700' => $status === 'Expired',
                            ])>
                                {{ __('filament.otp_status_' . strtolower($status)) }}
                            </span>
                        </td>

                        <td class="px-3 py-2 text-gray-600">
                            {{ $record->expires_at?->format('Y-m-d H:i:s') ?? '-' }}
                        </td>

                        <td class="px-3 py-2 text-gray-600">
                            {{ $record->created_at?->format('Y-m-d H:i:s') ?? '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-gray-500">
                            {{ __('filament.no_records_found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>