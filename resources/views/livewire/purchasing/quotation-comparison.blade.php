{{--
    FILE: resources/views/livewire/purchasing/quotation-comparison.blade.php
    PURPOSE: UI perbandingan penawaran supplier - real-time, sortable, responsive
--}}
<div class="space-y-6">

    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div class="alert alert-success shadow-md">
            <x-heroicon-o-check-circle class="w-5 h-5" />
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error shadow-md">
            <x-heroicon-o-exclamation-circle class="w-5 h-5" />
            {{ session('error') }}
        </div>
    @endif
    @error('select')
        <div class="alert alert-warning shadow-md">{{ $message }}</div>
    @enderror

    {{-- Header PR Info --}}
    @if ($pr)
    <div class="card bg-base-100 shadow-md border border-emerald-200">
        <div class="card-body py-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="badge badge-lg badge-outline badge-primary font-mono">{{ $pr->pr_number }}</span>
                        <h2 class="text-lg font-bold text-base-content">{{ $pr->title }}</h2>
                    </div>
                    <div class="flex flex-wrap gap-3 mt-1 text-sm text-base-content/70">
                        <span>📁 {{ $pr->category?->name ?? 'Tanpa Kategori' }}</span>
                        <span>📅 Deadline: <span class="{{ $pr->deadline->isPast() ? 'text-error font-semibold' : 'text-success' }}">{{ $pr->deadline->format('d/m/Y') }}</span></span>
                        <span>👤 PIC: {{ $pr->assignedTo?->name ?? '-' }}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    {{-- Download PDF PR --}}
                    <a href="{{ Storage::url($pr->file_path) }}"
                       target="_blank"
                       class="btn btn-sm btn-outline btn-primary">
                        <x-heroicon-o-arrow-down-tray class="w-4 h-4" /> PDF PR
                    </a>
                    {{-- Evaluasi Otomatis --}}
                    @if($pr->status === 'penawaran')
                    <button wire:click="runEvaluation"
                            wire:loading.attr="disabled"
                            class="btn btn-sm btn-warning">
                        <span wire:loading.remove wire:target="runEvaluation">
                            <x-heroicon-o-scale class="w-4 h-4" /> Evaluasi Otomatis
                        </span>
                        <span wire:loading wire:target="runEvaluation">
                            <span class="loading loading-spinner loading-xs"></span> Menghitung...
                        </span>
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Controls: Filter + Column Picker --}}
    <div class="flex flex-wrap items-center gap-3">
        {{-- Filter Status --}}
        <div class="flex items-center gap-2">
            <span class="text-sm font-medium">Status:</span>
            <select wire:model.live="filterStatus" class="select select-bordered select-sm">
                <option value="all">Semua</option>
                <option value="submitted">Submitted</option>
                <option value="review">Dalam Review</option>
                <option value="selected">Dipilih</option>
                <option value="rejected">Ditolak</option>
            </select>
        </div>

        {{-- Column Picker --}}
        <div class="dropdown">
            <label tabindex="0" class="btn btn-sm btn-ghost gap-1">
                <x-heroicon-o-table-cells class="w-4 h-4" /> Kolom
            </label>
            <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-48 border">
                @foreach(['terms' => 'Syarat & Ketentuan', 'files' => 'File Lampiran'] as $col => $label)
                <li>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox"
                               class="checkbox checkbox-sm checkbox-primary"
                               wire:click="toggleColumn('{{ $col }}')"
                               {{ ($visibleColumns[$col] ?? false) ? 'checked' : '' }}>
                        {{ $label }}
                    </label>
                </li>
                @endforeach
            </ul>
        </div>

        {{-- Indikator loading --}}
        <div wire:loading class="flex items-center gap-2 text-sm text-base-content/60">
            <span class="loading loading-spinner loading-xs"></span> Memuat...
        </div>

        <div class="ml-auto text-sm text-base-content/60">
            {{ $quotations->count() }} penawaran
            @if(count($selectedQuotations) > 0)
                • <span class="text-primary font-medium">{{ count($selectedQuotations) }} dipilih</span>
            @endif
        </div>
    </div>

    {{-- Tabel Penawaran Utama --}}
    <div class="overflow-x-auto rounded-xl shadow-sm border border-base-200">
        <table class="table table-zebra table-sm w-full">
            <thead class="bg-base-200">
                <tr>
                    <th class="w-8">
                        <span class="text-xs text-base-content/50">Pilih</span>
                    </th>

                    <th>
                        <button wire:click="sortColumn('supplier')" class="flex items-center gap-1 hover:text-primary">
                            Supplier
                            @if($sortBy === 'supplier')
                                <x-heroicon-o-chevron-{{ $sortDir === 'asc' ? 'up' : 'down' }} class="w-3 h-3" />
                            @endif
                        </button>
                    </th>

                    @if($visibleColumns['total_amount'] ?? true)
                    <th>
                        <button wire:click="sortColumn('total_amount')" class="flex items-center gap-1 hover:text-primary">
                            Total Nilai
                            @if($sortBy === 'total_amount')
                                <x-heroicon-o-chevron-{{ $sortDir === 'asc' ? 'up' : 'down' }} class="w-3 h-3" />
                            @endif
                        </button>
                    </th>
                    @endif

                    @if($visibleColumns['delivery_days'] ?? true)
                    <th>
                        <button wire:click="sortColumn('delivery_days')" class="flex items-center gap-1 hover:text-primary">
                            Est. Kirim
                            @if($sortBy === 'delivery_days')
                                <x-heroicon-o-chevron-{{ $sortDir === 'asc' ? 'up' : 'down' }} class="w-3 h-3" />
                            @endif
                        </button>
                    </th>
                    @endif

                    @if($visibleColumns['valid_until'] ?? true)
                    <th>Berlaku s/d</th>
                    @endif

                    @if($visibleColumns['score'] ?? true)
                    <th>
                        <button wire:click="sortColumn('score')" class="flex items-center gap-1 hover:text-primary">
                            Skor
                            @if($sortBy === 'score')
                                <x-heroicon-o-chevron-{{ $sortDir === 'asc' ? 'up' : 'down' }} class="w-3 h-3" />
                            @endif
                        </button>
                    </th>
                    @endif

                    @if($visibleColumns['terms'] ?? false)
                    <th>Syarat</th>
                    @endif

                    @if($visibleColumns['files'] ?? true)
                    <th>Files</th>
                    @endif

                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($quotations as $q)
                <tr class="{{ $q->is_best ? 'bg-emerald-50 border-l-4 border-l-emerald-500' : '' }}
                            {{ in_array($q->id, $selectedQuotations) ? 'ring-2 ring-inset ring-primary' : '' }}">

                    {{-- Checkbox pilih --}}
                    <td>
                        <input type="checkbox"
                               class="checkbox checkbox-sm checkbox-primary"
                               wire:click="toggleSelect({{ $q->id }})"
                               {{ in_array($q->id, $selectedQuotations) ? 'checked' : '' }}>
                    </td>

                    {{-- Supplier --}}
                    <td>
                        <div class="flex items-center gap-2">
                            @if($q->is_best)
                                <span class="badge badge-warning badge-xs">⭐ Terbaik</span>
                            @endif
                            <div>
                                <p class="font-semibold text-sm">{{ $q->supplier->company_name }}</p>
                                <p class="text-xs text-base-content/60">{{ $q->quotation_number ?? 'No. penawaran tidak ada' }}</p>
                            </div>
                        </div>
                    </td>

                    {{-- Total Nilai --}}
                    @if($visibleColumns['total_amount'] ?? true)
                    <td>
                        <span class="{{ $q->is_best ? 'text-emerald-700 font-bold' : 'font-medium' }}">
                            Rp {{ number_format($q->total_amount, 0, ',', '.') }}
                        </span>
                    </td>
                    @endif

                    {{-- Estimasi Kirim --}}
                    @if($visibleColumns['delivery_days'] ?? true)
                    <td>
                        <span class="badge badge-sm {{ $q->delivery_days <= 7 ? 'badge-success' : ($q->delivery_days <= 14 ? 'badge-warning' : 'badge-error') }}">
                            {{ $q->delivery_days }} hari
                        </span>
                    </td>
                    @endif

                    {{-- Berlaku s/d --}}
                    @if($visibleColumns['valid_until'] ?? true)
                    <td class="text-sm {{ $q->valid_until->isPast() ? 'text-error' : '' }}">
                        {{ $q->valid_until->format('d/m/Y') }}
                    </td>
                    @endif

                    {{-- Skor --}}
                    @if($visibleColumns['score'] ?? true)
                    <td>
                        @if($q->score > 0)
                        <div class="flex items-center gap-2">
                            <progress class="progress progress-{{ $q->score >= 80 ? 'success' : ($q->score >= 60 ? 'warning' : 'error') }} w-16"
                                      value="{{ $q->score }}" max="100"></progress>
                            <span class="text-xs font-mono">{{ number_format($q->score, 1) }}</span>
                        </div>
                        @else
                        <span class="text-xs text-base-content/40">Belum dinilai</span>
                        @endif
                    </td>
                    @endif

                    {{-- Syarat --}}
                    @if($visibleColumns['terms'] ?? false)
                    <td class="text-xs max-w-xs truncate" title="{{ $q->terms }}">
                        {{ $q->terms ?? '-' }}
                    </td>
                    @endif

                    {{-- Files --}}
                    @if($visibleColumns['files'] ?? true)
                    <td>
                        <span class="badge badge-outline badge-sm">{{ $q->files_count }} file</span>
                    </td>
                    @endif

                    {{-- Status --}}
                    <td>
                        <span class="badge badge-sm
                            {{ match($q->status) {
                                'submitted' => 'badge-info',
                                'review'    => 'badge-warning',
                                'selected'  => 'badge-success',
                                'rejected'  => 'badge-error',
                                'revised'   => 'badge-ghost',
                                default     => 'badge-ghost',
                            } }}">
                            {{ ucfirst($q->status) }}
                        </span>
                    </td>

                    {{-- Aksi --}}
                    <td>
                        <div class="flex gap-1">
                            @if(in_array($q->status, ['submitted', 'review']))
                            <button wire:click="approveQuotation({{ $q->id }})"
                                    wire:confirm="Setujui penawaran dari {{ $q->supplier->company_name }}?"
                                    class="btn btn-xs btn-success">
                                ✓ Setujui
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="text-center py-12 text-base-content/50">
                        <x-heroicon-o-inbox class="w-10 h-10 mx-auto mb-2 opacity-30" />
                        <p>Belum ada penawaran masuk untuk PR ini.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Side-by-Side Comparison Panel --}}
    @if(count($selectedQuotations) >= 2 && $comparedQuotations->count() >= 2)
    <div class="card bg-base-100 shadow-lg border border-primary/30">
        <div class="card-body">
            <h3 class="card-title text-primary">
                <x-heroicon-o-arrows-right-left class="w-5 h-5" />
                Perbandingan Side-by-Side ({{ $comparedQuotations->count() }} penawaran)
            </h3>

            <div class="overflow-x-auto">
                <table class="table table-bordered text-sm">
                    <thead>
                        <tr>
                            <th class="bg-base-200 w-40">Kriteria</th>
                            @foreach($comparedQuotations as $cq)
                            <th class="text-center {{ $cq->is_best ? 'bg-emerald-50 text-emerald-700' : '' }}">
                                {{ $cq->supplier->company_name }}
                                @if($cq->is_best) <span class="badge badge-warning badge-xs ml-1">⭐</span> @endif
                            </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $minAmount  = $comparedQuotations->min('total_amount');
                            $minDays    = $comparedQuotations->min('delivery_days');
                        @endphp
                        <tr>
                            <td class="font-medium bg-base-50">Total Nilai</td>
                            @foreach($comparedQuotations as $cq)
                            <td class="text-center {{ $cq->total_amount == $minAmount ? 'text-emerald-700 font-bold' : '' }}">
                                Rp {{ number_format($cq->total_amount, 0, ',', '.') }}
                                @if($cq->total_amount == $minAmount) 🏆 @endif
                            </td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="font-medium bg-base-50">Estimasi Pengiriman</td>
                            @foreach($comparedQuotations as $cq)
                            <td class="text-center {{ $cq->delivery_days == $minDays ? 'text-emerald-700 font-bold' : '' }}">
                                {{ $cq->delivery_days }} hari
                                @if($cq->delivery_days == $minDays) 🏆 @endif
                            </td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="font-medium bg-base-50">Berlaku Hingga</td>
                            @foreach($comparedQuotations as $cq)
                            <td class="text-center">{{ $cq->valid_until->format('d/m/Y') }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="font-medium bg-base-50">Skor Evaluasi</td>
                            @foreach($comparedQuotations as $cq)
                            <td class="text-center font-bold {{ $cq->score >= 80 ? 'text-success' : ($cq->score >= 60 ? 'text-warning' : 'text-error') }}">
                                {{ $cq->score > 0 ? number_format($cq->score, 1) : '-' }}
                            </td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="font-medium bg-base-50">Jumlah Dokumen</td>
                            @foreach($comparedQuotations as $cq)
                            <td class="text-center">{{ $cq->files->count() }} file</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

</div>
