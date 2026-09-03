<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrgNode;
use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function __construct(private TransferService $transfers) {}

    /** GET /api/transfers — เห็นเฉพาะใบที่เกี่ยวกับสายงานของตัวเอง */
    public function index(Request $request): JsonResponse
    {
        $visible = $request->user()->visibleNodeIds();

        $rows = Transfer::with(['fromNode:id,code,name', 'toNode:id,code,name'])
            ->where(fn ($q) => $q->whereIn('from_node_id', $visible)->orWhereIn('to_node_id', $visible))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest('id')
            ->paginate(20);

        return response()->json($rows);
    }

    /** POST /api/transfers */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_node_id'       => ['required', 'integer', 'exists:org_nodes,id'],
            'to_node_id'         => ['required', 'integer', 'exists:org_nodes,id', 'different:from_node_id'],
            'type'               => ['nullable', 'in:allocation,requisition,return'],
            'note'               => ['nullable', 'string'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.lot_id'     => ['nullable', 'integer', 'exists:product_lots,id'],
            'items.*.qty'        => ['required', 'integer', 'min:1'],
        ]);

        abort_unless($request->user()->canAccessNode($data['from_node_id']), 403, 'ไม่มีสิทธิ์โอนจากคลังนี้');

        $transfer = $this->transfers->create(
            OrgNode::findOrFail($data['from_node_id']),
            OrgNode::findOrFail($data['to_node_id']),
            $data['items'],
            $data['type'] ?? 'allocation',
            $data['note'] ?? null,
        );

        return response()->json($transfer, 201);
    }

    public function approve(Request $request, Transfer $transfer): JsonResponse
    {
        abort_unless($request->user()->canAccessNode($transfer->from_node_id), 403);

        return response()->json($this->transfers->approve($transfer, $request->user()));
    }

    public function reject(Request $request, Transfer $transfer): JsonResponse
    {
        abort_unless($request->user()->canAccessNode($transfer->from_node_id), 403);

        return response()->json(
            $this->transfers->reject($transfer, $request->user(), $request->input('reason'))
        );
    }

    public function ship(Request $request, Transfer $transfer): JsonResponse
    {
        abort_unless($request->user()->canAccessNode($transfer->from_node_id), 403);

        $data = $request->validate(['qty' => ['nullable', 'array']]);

        return response()->json($this->transfers->ship($transfer, $data['qty'] ?? null));
    }

    public function receive(Request $request, Transfer $transfer): JsonResponse
    {
        abort_unless($request->user()->canAccessNode($transfer->to_node_id), 403, 'ไม่มีสิทธิ์รับของแทนหน่วยงานนี้');

        $data = $request->validate(['qty' => ['nullable', 'array']]);

        return response()->json(
            $this->transfers->receive($transfer, $request->user(), $data['qty'] ?? null)
        );
    }
}
