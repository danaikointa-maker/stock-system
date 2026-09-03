<?php

namespace App\Http\Controllers\Web;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\OrgNode;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** จัดการสมาชิก (ผู้ใช้) ที่อยู่ในสายงานของตัวเอง */
class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $me = $request->user();

        $members = User::with('node:id,code,name,level_id')
            ->inScopeOf($me)
            ->where('id', '!=', $me->id)
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%$s%")
                ->orWhere('email', 'like', "%$s%")
                ->orWhere('phone', 'like', "%$s%")))
            ->when($request->node_id, fn ($q, $id) => $q->where('org_node_id', $id))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->orderBy('org_node_id')->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('members.index', [
            'members' => $members,
            'nodes'   => $this->selectableNodes($me),
            'roles'   => Role::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', User::class);

        return view('members.form', [
            'member' => new User,
            'nodes'  => $this->selectableNodes($request->user()),
            'roles'  => $this->assignableRoles($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $this->validated($request);

        // ต้องสร้างได้เฉพาะในหน่วยงานที่ตัวเองดูแล
        abort_unless($request->user()->canAccessNode($data['org_node_id']), 403,
            'ไม่มีสิทธิ์เพิ่มสมาชิกในหน่วยงานนี้');

        $this->assertRoleAllowed($request->user(), $data['role']);

        $tempPassword = $data['password'] ?? null ?: Str::random(10);

        $member = User::create([
            'org_node_id' => $data['org_node_id'],
            'name'        => $data['name'],
            'email'       => $data['email'] ?? null,
            'phone'       => $data['phone'] ?? null,
            'role'        => $data['role'],
            'is_active'   => $request->boolean('is_active', true),
            'password'    => $tempPassword,
        ]);

        return redirect()->route('members.index')
            ->with('status', "เพิ่มสมาชิก {$member->name} เรียบร้อย")
            ->with('temp_password', empty($data['password']) ? $tempPassword : null);
    }

    public function edit(Request $request, User $member): View
    {
        $this->authorize('update', $member);

        return view('members.form', [
            'member' => $member,
            'nodes'  => $this->selectableNodes($request->user()),
            'roles'  => $this->assignableRoles($request->user()),
        ]);
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        $this->authorize('update', $member);

        $data = $this->validated($request, $member);

        abort_unless($request->user()->canAccessNode($data['org_node_id']), 403);
        $this->assertRoleAllowed($request->user(), $data['role']);

        $member->update([
            'org_node_id' => $data['org_node_id'],
            'name'        => $data['name'],
            'email'       => $data['email'] ?? null,
            'phone'       => $data['phone'] ?? null,
            'role'        => $data['role'],
            'is_active'   => $request->boolean('is_active'),
        ]);

        if (! empty($data['password'])) {
            $member->update(['password' => $data['password']]);
        }

        return redirect()->route('members.index')->with('status', 'บันทึกข้อมูลเรียบร้อย');
    }

    /** เปิด/ปิดการใช้งานบัญชี */
    public function toggleStatus(Request $request, User $member): RedirectResponse
    {
        $this->authorize('update', $member);

        $member->update(['is_active' => ! $member->is_active]);

        return back()->with('status',
            $member->is_active ? "เปิดใช้งาน {$member->name} แล้ว" : "ระงับบัญชี {$member->name} แล้ว");
    }

    /** รีเซ็ตรหัสผ่านให้สมาชิก */
    public function resetPassword(Request $request, User $member): RedirectResponse
    {
        $this->authorize('resetPassword', $member);

        $new = Str::random(10);
        $member->update(['password' => $new]);

        return back()
            ->with('status', "รีเซ็ตรหัสผ่านของ {$member->name} แล้ว")
            ->with('temp_password', $new);
    }

    public function destroy(Request $request, User $member): RedirectResponse
    {
        $this->authorize('delete', $member);

        $member->delete();   // soft delete

        return redirect()->route('members.index')->with('status', 'ลบสมาชิกเรียบร้อย');
    }

    // ---------------- helpers ----------------

    private function validated(Request $request, ?User $member = null): array
    {
        return $request->validate([
            'org_node_id' => ['required', 'integer', 'exists:org_nodes,id'],
            'name'        => ['required', 'string', 'max:150'],
            'email'       => ['nullable', 'email', 'max:150', Rule::unique('users')->ignore($member)],
            'phone'       => ['nullable', 'string', 'max:30', Rule::unique('users')->ignore($member)],
            'role'        => ['required', Rule::enum(Role::class)],
            'password'    => [$member ? 'nullable' : 'nullable', 'string', 'min:8', 'confirmed'],
        ], [], [
            'org_node_id' => 'หน่วยงาน', 'name' => 'ชื่อ', 'email' => 'อีเมล',
            'phone' => 'เบอร์โทร', 'role' => 'บทบาท', 'password' => 'รหัสผ่าน',
        ]);
    }

    private function selectableNodes(User $me)
    {
        return OrgNode::whereIn('id', $me->visibleNodeIds())
            ->orderBy('path')->orderBy('code')
            ->get(['id', 'code', 'name', 'level_id', 'depth']);
    }

    /** ให้บทบาทที่สูงกว่าตัวเองไม่ได้ */
    private function assignableRoles(User $me): array
    {
        if ($me->isSystemAdmin()) {
            return Role::cases();
        }

        return array_values(array_filter(
            Role::cases(),
            fn (Role $r) => $r !== Role::SystemAdmin
        ));
    }

    private function assertRoleAllowed(User $me, string $role): void
    {
        if (! $me->isSystemAdmin() && $role === Role::SystemAdmin->value) {
            abort(403, 'ไม่มีสิทธิ์กำหนดบทบาทผู้ดูแลระบบ');
        }
    }
}
