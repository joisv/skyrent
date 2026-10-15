<div>
    @if (session()->has('message'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-300 text-emerald-800 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <svg class="w-6 h-6 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-medium text-sm">{{ session('message') }}</span>
            </div>
        </div>
    @endif

    {{-- Hero Card: Status Rilis Aktif --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <div class="p-3 rounded-xl bg-orange-100 dark:bg-orange-900/40 text-orange-600 dark:text-orange-400">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Distribusi Pembaruan APK SkyMobile</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Kelola berkas APK biner dan metadata rilis resmi untuk terminal kasir & aplikasi mitra.</p>
                    </div>
                </div>

                @if ($latestActive)
                    <div class="mt-4 flex flex-wrap items-center gap-3 text-sm">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-100 text-emerald-800 font-semibold text-xs dark:bg-emerald-900/50 dark:text-emerald-300">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Rilis Aktif: v{{ $latestActive->version_name }} (Build {{ $latestActive->build_number }})
                        </span>
                        <span class="text-gray-500 dark:text-gray-400">•</span>
                        <span class="text-gray-600 dark:text-gray-300">Ukuran: {{ number_format($latestActive->apk_size / 1048576, 2) }} MB</span>
                        <span class="text-gray-500 dark:text-gray-400">•</span>
                        <span class="font-mono text-xs text-gray-500 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded" title="{{ $latestActive->apk_checksum_sha256 }}">
                            SHA-256: {{ substr($latestActive->apk_checksum_sha256, 0, 16) }}...
                        </span>
                    </div>
                @else
                    <div class="mt-4 p-3 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700 rounded-lg text-amber-800 dark:text-amber-300 text-sm">
                        Belum ada rilis APK aktif yang dipublikasikan. Aplikasi SkyMobile yang terpasang tidak akan menerima pembaruan.
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-3">
                @if ($latestActive)
                    <a href="{{ route('api.v1.app.download', ['buildNumber' => $latestActive->build_number]) }}" target="_blank"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-semibold transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Unduh APK Aktif
                    </a>
                @endif

                <button wire:click="openCreateModal"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-700 text-white font-semibold text-sm shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Unggah Rilis Baru
                </button>
            </div>
        </div>
    </div>

    {{-- Tautan Unduh Publik Resmi (Bisa Dibagikan) --}}
    @if ($latestActive)
        <div class="bg-gradient-to-r from-orange-50 via-white to-amber-50 dark:from-gray-800 dark:via-gray-800 dark:to-gray-800/90 rounded-2xl border border-orange-200 dark:border-gray-700 p-5 mb-6"
             x-data="{ copiedLatest: false }">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 text-sm font-bold text-orange-950 dark:text-orange-300">
                        <svg class="w-4 h-4 text-orange-600 dark:text-orange-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                        Tautan Unduh Publik (Bisa Dibagikan ke Tim / Mitra)
                    </div>
                    <p class="text-xs text-gray-600 dark:text-gray-400">
                        Tautan ini selalu mengarah ke berkas APK rilis aktif terbaru secara langsung tanpa perlu login.
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                    <div class="relative flex-1 sm:w-96">
                        <input type="text" readonly
                            value="{{ route('api.v1.app.download', ['buildNumber' => 'latest']) }}"
                            class="w-full px-3.5 py-2 text-xs font-mono bg-white dark:bg-gray-700 border border-orange-300 dark:border-gray-600 rounded-xl text-gray-800 dark:text-gray-200 select-all focus:outline-none focus:ring-2 focus:ring-orange-500 shadow-inner">
                    </div>

                    <button type="button"
                        @click="
                            navigator.clipboard.writeText('{{ route('api.v1.app.download', ['buildNumber' => 'latest']) }}');
                            copiedLatest = true;
                            setTimeout(() => copiedLatest = false, 2500);
                        "
                        class="inline-flex items-center justify-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-xl text-white transition shadow-sm shrink-0"
                        :class="copiedLatest ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-orange-600 hover:bg-orange-700'">
                        <span x-show="!copiedLatest" class="inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                            </svg>
                            Salin Tautan APK
                        </span>
                        <span x-show="copiedLatest" x-cloak class="inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Tautan Berhasil Disalin!
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Tabel Riwayat Rilis --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-5 border-b border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <h4 class="font-bold text-gray-900 dark:text-gray-100">Riwayat Rilis SkyMobile</h4>
            <div class="w-full sm:w-72">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari versi, build, catatan..."
                    class="w-full px-3.5 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-orange-500 focus:border-orange-500">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs uppercase font-semibold text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-5 py-3.5">Versi & Build</th>
                        <th class="px-5 py-3.5">Tanggal Unggah</th>
                        <th class="px-5 py-3.5">Ukuran</th>
                        <th class="px-5 py-3.5">Integritas (SHA-256)</th>
                        <th class="px-5 py-3.5">Pembaruan Wajib</th>
                        <th class="px-5 py-3.5">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($releases as $rel)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                            <td class="px-5 py-4">
                                <div class="font-bold text-gray-900 dark:text-gray-100 text-base">v{{ $rel->version_name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Build Number: <span class="font-mono font-semibold">{{ $rel->build_number }}</span></div>
                                @if ($rel->release_notes)
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-1 italic">"{{ $rel->release_notes }}"</div>
                                @endif
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-xs">
                                <div>{{ $rel->created_at->format('d M Y, H:i') }} WIB</div>
                                <div class="text-gray-400">{{ $rel->creator?->name ?? 'Administrator' }}</div>
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap font-medium text-xs">
                                {{ number_format($rel->apk_size / 1048576, 2) }} MB
                            </td>
                            <td class="px-5 py-4 font-mono text-xs text-gray-500 max-w-xs truncate" title="{{ $rel->apk_checksum_sha256 }}">
                                {{ substr($rel->apk_checksum_sha256, 0, 16) }}...
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-xs">
                                @if ($rel->min_supported_build)
                                    <span class="inline-flex px-2 py-0.5 rounded bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 font-semibold">
                                        Build &lt; {{ $rel->min_supported_build }}
                                    </span>
                                @else
                                    <span class="text-gray-400">Opsional</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap">
                                <button type="button" wire:click="toggleActive({{ $rel->id }})"
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold transition {{ $rel->is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300 hover:bg-emerald-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400 hover:bg-gray-200' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $rel->is_active ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                                    {{ $rel->is_active ? 'Aktif' : 'Nonaktif' }}
                                </button>
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-1.5" x-data="{ copied: false }">
                                    {{-- Tombol Salin Tautan --}}
                                    <button type="button"
                                        @click="
                                            navigator.clipboard.writeText('{{ route('api.v1.app.download', ['buildNumber' => $rel->build_number]) }}');
                                            copied = true;
                                            setTimeout(() => copied = false, 2000);
                                        "
                                        class="relative p-2 rounded-lg text-gray-600 dark:text-gray-400 hover:bg-orange-50 hover:text-orange-600 dark:hover:bg-gray-700 transition"
                                        title="Salin Tautan Unduh APK (v{{ $rel->version_name }})">
                                        <span x-show="!copied">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                            </svg>
                                        </span>
                                        <span x-show="copied" x-cloak>
                                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                        <span x-show="copied" x-cloak
                                            class="absolute -top-7 left-1/2 -translate-x-1/2 px-2 py-0.5 text-[10px] font-bold text-white bg-gray-900 rounded shadow whitespace-nowrap z-10">
                                            Tersalin!
                                        </span>
                                    </button>

                                    {{-- Tombol Unduh Berkas APK --}}
                                    <a href="{{ route('api.v1.app.download', ['buildNumber' => $rel->build_number]) }}"
                                        class="p-2 rounded-lg text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-orange-600 transition" title="Unduh Berkas APK">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                    </a>

                                    {{-- Tombol Hapus Rilis --}}
                                    <button type="button" wire:click="deleteRelease({{ $rel->id }})"
                                        wire:confirm="Apakah Anda yakin ingin menghapus rilis v{{ $rel->version_name }} (Build {{ $rel->build_number }}) beserta berkas APK fisiknya?"
                                        class="p-2 rounded-lg text-gray-600 dark:text-gray-400 hover:bg-red-50 hover:text-red-600 transition" title="Hapus Rilis">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-gray-500 dark:text-gray-400">
                                Belum ada berkas rilis APK yang diunggah.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-gray-200 dark:border-gray-700">
            {{ $releases->links() }}
        </div>
    </div>

    {{-- Modal Unggah Rilis Baru --}}
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fadeIn">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl max-w-lg w-full p-6 border border-gray-200 dark:border-gray-700"
                @click.away="$wire.closeCreateModal()">
                
                <div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Unggah Rilis Baru APK SkyMobile</h3>
                    <button type="button" wire:click="closeCreateModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="save" class="mt-5 space-y-4">
                    {{-- Berkas APK --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Berkas APK (.apk) *</label>
                        <input type="file" wire:model="apk_file" accept=".apk"
                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100 border border-gray-300 dark:border-gray-600 rounded-xl p-1.5 dark:bg-gray-700" />
                        <span class="text-xs text-gray-500 mt-1 block">Format berkas: .apk (Maksimal 250 MB).</span>
                        <div wire:loading wire:target="apk_file" class="mt-2 text-xs text-orange-600 flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            Mengunggah berkas ke server...
                        </div>
                        @error('apk_file') <span class="text-xs text-red-500 font-medium mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    {{-- Versi & Build --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Nama Versi *</label>
                            <input type="text" wire:model="version_name" placeholder="Contoh: 1.0.1"
                                class="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-orange-500 focus:border-orange-500">
                            @error('version_name') <span class="text-xs text-red-500 font-medium mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Build Number *</label>
                            <input type="number" wire:model="build_number" min="1" placeholder="Contoh: 2"
                                class="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-orange-500 focus:border-orange-500">
                            @error('build_number') <span class="text-xs text-red-500 font-medium mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Minimum Supported Build --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                            Minimum Build Didukung (Opsional)
                        </label>
                        <input type="number" wire:model="min_supported_build" min="1" placeholder="Kosongkan jika pembaruan opsional"
                            class="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-orange-500 focus:border-orange-500">
                        <span class="text-xs text-gray-500 mt-1 block">Aplikasi dengan build lebih rendah dari angka ini akan dipaksa memperbarui (mandatory).</span>
                        @error('min_supported_build') <span class="text-xs text-red-500 font-medium mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    {{-- Catatan Rilis --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Catatan Rilis (Changelog)</label>
                        <textarea wire:model="release_notes" rows="3" placeholder="- Perbaikan stabilitas&#10;- Fitur baru cetak resi thermal"
                            class="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-orange-500 focus:border-orange-500"></textarea>
                        @error('release_notes') <span class="text-xs text-red-500 font-medium mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    {{-- Switch Aktif --}}
                    <div class="flex items-center gap-3 pt-1">
                        <input type="checkbox" id="is_active" wire:model="is_active"
                            class="w-4 h-4 rounded text-orange-600 focus:ring-orange-500 border-gray-300 dark:border-gray-600">
                        <label for="is_active" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Aktifkan rilis ini segera untuk seluruh pengguna SkyMobile
                        </label>
                    </div>

                    {{-- Tombol Modal --}}
                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end gap-3">
                        <button type="button" wire:click="closeCreateModal"
                            class="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition">
                            Batal
                        </button>
                        <button type="submit" wire:loading.attr="disabled"
                            class="px-5 py-2 text-sm font-semibold text-white bg-orange-600 hover:bg-orange-700 disabled:opacity-50 rounded-xl shadow-sm transition">
                            <span wire:loading.remove wire:target="save">Simpan Rilis</span>
                            <span wire:loading wire:target="save">Memproses Berkas...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
