<?php

namespace App\Http\Controllers\Web;

use App\Enums\OrgLevel;
use App\Http\Controllers\Controller;
use App\Models\OrgNode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** จัดการหน่วยงานลูกในสายงาน (เปิดคลังย่อย / รับตัวแทน / เปิดร้าน) */
class NodeController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', OrgNode::class);

        $me = $request->user();

        $nodes = OrgNode::withCount(['children', 'users'])
            ->whereIn('id', $me->visibleNodeIds())
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%")))
            ->when($request->level, fn ($q, $l) => $q->where('level_id', $l))
            ->orderBy('path')->orderBy('code')
            ->get();

        return view('nodes.index', [
            'nodes'  => $nodes,
            'levels' => OrgLevel::cases(),
            'myNode' => $me->node,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', OrgNode::class);

        return view('nodes.form', [
            'node'    => new OrgNode,
            'parents' => $this->selectableParents($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', OrgNode::class);

        $data = $this->validated($request);
        $parent = OrgNode::findOrFail($data['parent_id']);

        abort_unless($request->user()->canAccessNode($parent->id), 403,
            'ไม่มีสิทธิ์เปิดหน่วยงานใต้สังกัดนี้');

        $childLevel = $parent->level_id->child();
        abort_if(! $childLevel, 422, 'หน่วยงานนี้เป็นระดับล่างสุดแล้ว ไม่สามารถมีหน่วยงานลูกได้');

        $node = OrgNode::create([
            'parent_id'    => $parent->id,
            'level_id'     => $childLevel,
            'code'         => $data['code'],
            'name'         => $data['name'],
            'phone'        => $data['phone'] ?? null,
            'address'      => $data['address'] ?? null,
            'credit_limit' => $data['credit_limit'] ?? 0,
            'status'       => $data['status'] ?? 'active',
        ]);

        return redirect()->route('nodes.index')
            ->with('status', "เปิด{$childLevel->label()} {$node->name} เรียบร้อย");
    }

    public function edit(Request $request, OrgNode $node): View
    {
        $this->authorize('update', $node);

        return view('nodes.form', [
            'node'    => $node,
            'parents' => $this->selectableParents($request->user()),
        ]);
    }

    public function update(Request $request, OrgNode $node): RedirectResponse
    {
        $this->authorize('update', $node);

        $data = $this->validated($request, $node);

        // ไม่อนุญาตย้าย parent ผ่านหน้านี้ (กระทบ path ของลูกหลานทั้งสาย)
        $node->update([
            'code'         => $data['code'],
            'name'         => $data['name'],
            'phone'        => $data['phone'] ?? null,
            'address'      => $data['address'] ?? null,
            'credit_limit' => $data['credit_limit'] ?? 0,
            'status'       => $data['status'] ?? 'active',
        ]);

        return redirect()->route('nodes.index')->with('status', 'บันทึกข้อมูลหน่วยงานเรียบร้อย');
    }

    /** หน้ารายละเอียด: สต๊อก + สมาชิก + ลูกหลาน */
    public function show(Request $request, OrgNode $node): View
    {
        $this->authorize('view', $node);

        return view('nodes.show', [
            'node'     => $node->load(['parent:id,code,name', 'children:id,parent_id,code,name,level_id,status']),
            'members'  => $node->users()->orderBy('name')->get(),
            'balances' => $node->stockBalances()->with('product:id,sku,name')->get(),
        ]);
    }

    public function destroy(Request $request, OrgNode $node): RedirectResponse
    {
        $this->authorize('delete', $node);

        if ($node->users()->exists()) {
            return back()->withErrors(['node' => 'ยังมีสมาชิกอยู่ในหน่วยงานนี้ กรุณาย้ายออกก่อน']);
        }

        $node->delete();

        return redirect()->route('nodes.index')->with('status', 'ปิดหน่วยงานเรียบร้อย');
    }

    private function validated(Request $request, ?OrgNode $node = null): array
    {
        $rules = [
            'code'         => ['required', 'string', 'max:50', Rule::unique('org_nodes')->ignore($node)],
            'name'         => ['required', 'string', 'max:150'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'address'      => ['nullable', 'string'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'status'       => ['nullable', 'in:active,suspended,closed'],
        ];

        if (! $node) {
            $rules['parent_id'] = ['required', 'integer', 'exists:org_nodes,id'];
        }

        return $request->validate($rules, [], [
            'code' => 'รหัสหน่วยงาน', 'name' => 'ชื่อหน่วยงาน',
            'parent_id' => 'สังกัด', 'phone' => 'เบอร์โทร',
        ]);
    }

    /** โหนดที่เป็น parent ได้ = โหนดในสายงานที่ยังไม่ถึงระดับล่างสุด */
    private function selectableParents($me)
    {
        return OrgNode::whereIn('id', $me->visibleNodeIds())
            ->where('level_id', '<', OrgLevel::Seller->value)
            ->orderBy('path')->orderBy('code')
            ->get(['id', 'code', 'name', 'level_id', 'depth']);
    }
}
