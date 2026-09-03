<?php

namespace App\Http\Controllers\Web;

use App\Enums\TransferStatus;
use App\Http\Controllers\Controller;
use App\Models\OrgNode;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** จัดการใบโอนสินค้าบนเว็บ: สร้าง -> อนุมัติ -> ส่ง -> รับ */
class TransferWebController extends Controller
{
    public function __construct(private TransferService $transfers) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Transfer::class);

        $user = $request->user();
        $visible = $user->visibleNodeIds();

        $query = Transfer::with(['fromNode:id,code,name', 'toNode:id,code,name'])
            ->where(fn ($q) => $q->whereIn('from_node_id', $visible)->orWhereIn('to_node_id', $visible));

        // แท็บ: ทั้งหมด / รอฉันอนุมัติ / รอฉันรับ
        match ($request->tab) {
            'approve' => $query->whereIn('from_node_id', $visible)
                               ->where('status', TransferStatus::PendingApprove),
            'receive' => $query->whereIn('to_node_id', $visible)
                               ->where('status', TransferStatus::Shipped),
            default   => $query->when($request->status, fn ($q, $s) => $q->where('status', $s)),
        };

        return view('transfers.index', [
            'transfers'    => $query->when($request->q, fn ($q, $s) => $q->where('doc_no', 'like', "%$s%"))
                                    ->latest('id')->paginate(20)->withQueryString(),
            'statuses'     => TransferStatus::cases(),
            'countApprove' => Transfer::whereIn('from_node_id', $visible)
                                ->where('status', TransferStatus::PendingApprove)->count(),
            'countReceive' => Transfer::whereIn('to_node_id', $visible)
                                ->where('status', TransferStatus::Shipped)->count(),
        ]);
    }

    public function show(Request $request, Transfer $transfer): View
    {
        $this->authorize('view', $transfer);

        return view('transfers.show', [
            'transfer' => $transfer->load(['items.product', 'items.lot', 'fromNode', 'toNode']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Transfer::class);

        $user = $request->user();
        $from = $this->resolveFromNode($request, $user);

        return view('transfers.create', [
            'fromNode'  => $from,
            'fromNodes' => $this->sourceNodes($user),
            'toNodes'   => OrgNode::where('parent_id', $from->id)->where('status', 'active')
                            ->orderBy('code')->get(['id', 'code', 'name', 'level_id']),
            'stock'     => $this->availableStock($from),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Transfer::class);

        $data = $request->validate([
            'from_node_id'       => ['required', 'integer', 'exists:org_nodes,id'],
            'to_node_id'         => ['required', 'integer', 'exists:org_nodes,id', 'different:from_node_id'],
            'note'               => ['nullable', 'string', 'max:1000'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty'        => ['required', 'integer', 'min:1'],
        ], [], ['items' => 'รายการสินค้า', 'to_node_id' => 'หน่วยงานปลายทาง']);

        $from = OrgNode::findOrFail($data['from_node_id']);
        $to = OrgNode::findOrFail($data['to_node_id']);

        abort_unless($request->user()->canAccessNode($from->id), 403, 'ไม่มีสิทธิ์โอนจากหน่วยงานนี้');

        try {
            $transfer = $this->transfers->create($from, $to, $data['items'], 'allocation', $data['note'] ?? null);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['transfer' => $e->getMessage()]);
        }

        return redirect()->route('transfers.show', $transfer)
            ->with('status', "สร้างใบโอน {$transfer->doc_no} แล้ว รออนุมัติ");
    }

    public function approve(Request $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('approve', $transfer);

        return $this->run(fn () => $this->transfers->approve($transfer, $request->user()),
            "อนุมัติใบโอน {$transfer->doc_no} แล้ว (จองสินค้าที่ต้นทางเรียบร้อย)");
    }

    public function reject(Request $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('reject', $transfer);

        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:255']])['reason'] ?? null;

        return $this->run(fn () => $this->transfers->reject($transfer, $request->user(), $reason),
            "ปฏิเสธใบโอน {$transfer->doc_no} แล้ว");
    }

    public function ship(Request $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('ship', $transfer);

        $qty = $request->validate(['qty' => ['nullable', 'array']])['qty'] ?? null;
        $qty = $qty ? array_map('intval', $qty) : null;

        return $this->run(fn () => $this->transfers->ship($transfer, $qty),
            "ส่งสินค้าตามใบโอน {$transfer->doc_no} แล้ว");
    }

    public function receive(Request $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('receive', $transfer);

        $qty = $request->validate(['qty' => ['nullable', 'array']])['qty'] ?? null;
        $qty = $qty ? array_map('intval', $qty) : null;

        return $this->run(fn () => $this->transfers->receive($transfer, $request->user(), $qty),
            "รับสินค้าเข้าคลังตามใบโอน {$transfer->doc_no} แล้ว");
    }

    public function cancel(Request $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('cancel', $transfer);

        return $this->run(fn () => $this->transfers->cancel($transfer),
            "ยกเลิกใบโอน {$transfer->doc_no} แล้ว");
    }

    // ---------------- helpers ----------------

    private function run(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (\Throwable $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }

        return back()->with('status', $message);
    }

    /** หน่วยงานที่โอนออกได้ = หน่วยงานในสายงานที่มีลูก */
    private function sourceNodes($user)
    {
        return OrgNode::whereIn('id', $user->visibleNodeIds())
            ->whereHas('children')
            ->orderBy('path')
            ->get(['id', 'code', 'name', 'level_id', 'path', 'depth']);
    }

    private function resolveFromNode(Request $request, $user): OrgNode
    {
        $nodes = $this->sourceNodes($user);

        abort_if($nodes->isEmpty(), 403, 'หน่วยงานของคุณยังไม่มีหน่วยงานลูกให้โอนสินค้าไป');

        if ($request->filled('from')) {
            $picked = $nodes->firstWhere('id', (int) $request->from);
            if ($picked) {
                return $picked;
            }
        }

        return $nodes->firstWhere('id', $user->org_node_id) ?? $nodes->first();
    }

    private function availableStock(OrgNode $node)
    {
        return StockBalance::with('product:id,sku,name')
            ->where('org_node_id', $node->id)
            ->selectRaw('MIN(id) as id, org_node_id, product_id,
                         SUM(qty_on_hand) as qty_on_hand,
                         SUM(qty_reserved) as qty_reserved,
                         0 as qty_in_transit, 0 as reorder_point')
            ->groupBy('org_node_id', 'product_id')
            ->havingRaw('SUM(qty_on_hand) - SUM(qty_reserved) > 0')
            ->get()
            ->filter(fn ($b) => $b->product !== null)
            ->values();
    }
}
