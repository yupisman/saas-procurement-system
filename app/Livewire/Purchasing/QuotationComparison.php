<?php
// =============================================================================
// FILE: app/Livewire/Purchasing/QuotationComparison.php
// PURPOSE: Livewire component untuk tabel perbandingan penawaran supplier.
//          Real-time, sortable, filterable tanpa page reload.
// =============================================================================
namespace App\Livewire\Purchasing;

use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Services\ProcurementService;
use Livewire\Component;
use Livewire\Attributes\Url;

class QuotationComparison extends Component
{
    // ── State properties (reactive) ───────────────────────────────────────────

    public int $prId;                          // ID PR yang dibandingkan
    public ?PurchaseRequest $pr = null;

    #[Url]
    public string $sortBy    = 'total_amount'; // Kolom sort default: harga
    public string $sortDir   = 'asc';
    public string $filterStatus = 'all';       // Filter status penawaran

    // Kolom yang bisa di-toggle tampilnya
    public array $visibleColumns = [
        'supplier'       => true,
        'total_amount'   => true,
        'delivery_days'  => true,
        'valid_until'    => true,
        'score'          => true,
        'terms'          => false,   // Tersembunyi by default
        'files'          => true,
    ];

    // Penawaran yang dipilih untuk dibandingkan (max 3)
    public array $selectedQuotations = [];

    // Listeners untuk real-time updates dari Filament actions
    protected $listeners = ['quotation-approved' => '$refresh'];

    public function mount(int $prId): void
    {
        $this->prId = $prId;
        $this->pr   = PurchaseRequest::with(['category', 'createdBy'])->find($prId);
    }

    /**
     * Sort kolom - klik header untuk sort naik/turun
     */
    public function sortColumn(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy  = $column;
            $this->sortDir = 'asc';
        }
    }

    /**
     * Toggle visibility kolom
     */
    public function toggleColumn(string $column): void
    {
        $this->visibleColumns[$column] = !($this->visibleColumns[$column] ?? false);
    }

    /**
     * Toggle pilih penawaran untuk perbandingan side-by-side
     */
    public function toggleSelect(int $quotationId): void
    {
        if (in_array($quotationId, $this->selectedQuotations)) {
            $this->selectedQuotations = array_values(
                array_filter($this->selectedQuotations, fn ($id) => $id !== $quotationId)
            );
        } elseif (count($this->selectedQuotations) < 3) {
            $this->selectedQuotations[] = $quotationId;
        } else {
            $this->addError('select', 'Maksimal 3 penawaran bisa dibandingkan sekaligus.');
        }
    }

    /**
     * Jalankan evaluasi otomatis: hitung skor semua penawaran
     */
    public function runEvaluation(): void
    {
        try {
            $best = app(ProcurementService::class)->evaluateQuotations($this->pr);
            session()->flash('success', "Evaluasi selesai. Skor dihitung ulang. Penawaran terbaik: " .
                ($best?->supplier->company_name ?? '-'));
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Approve penawaran tertentu
     */
    public function approveQuotation(int $quotationId): void
    {
        $quotation = Quotation::find($quotationId);
        if (!$quotation) return;

        try {
            app(ProcurementService::class)->approveQuotation($quotation);
            session()->flash('success', "Penawaran dari {$quotation->supplier->company_name} disetujui.");
            $this->dispatch('quotation-approved');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Computed property: daftar penawaran yang sudah disort & difilter
     */
    public function getQuotationsProperty()
    {
        $query = Quotation::where('purchase_request_id', $this->prId)
            ->with(['supplier', 'items', 'files'])
            ->withCount('files');

        // Filter status
        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        // Sort
        $allowedSorts = ['total_amount', 'delivery_days', 'score', 'valid_until', 'created_at'];
        if (in_array($this->sortBy, $allowedSorts)) {
            $query->orderBy($this->sortBy, $this->sortDir);
        }

        return $query->get();
    }

    /**
     * Computed: penawaran yang dipilih untuk side-by-side comparison
     */
    public function getComparedQuotationsProperty()
    {
        if (empty($this->selectedQuotations)) return collect();

        return Quotation::whereIn('id', $this->selectedQuotations)
            ->with(['supplier', 'items', 'files'])
            ->get()
            ->sortBy(fn ($q) => array_search($q->id, $this->selectedQuotations));
    }

    public function render()
    {
        return view('livewire.purchasing.quotation-comparison', [
            'quotations'         => $this->quotations,
            'comparedQuotations' => $this->comparedQuotations,
        ]);
    }
}
