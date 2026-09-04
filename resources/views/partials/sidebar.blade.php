@php
    $u = auth()->user();
    $lv = $u->level();
@endphp
<aside class="sidebar">
  <div class="brand">
    <b style="display:flex;align-items:center;gap:8px">
      <img src="{{ asset('brand/logo.svg') }}" alt="" style="width:26px;height:26px;object-fit:contain;background:#fff;border-radius:7px;padding:2px">
      RoaMembers
    </b>
    <span>{{ $lv?->label() ?? 'ไม่ระบุระดับ' }} · {{ $u->node?->code }}</span>
  </div>

  <nav>
    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'on' : '' }}">
      ภาพรวม
    </a>

    <div class="group">งานประจำวัน</div>

    @can('create', App\Models\Sale::class)
      <a href="{{ route('pos.index') }}" class="{{ request()->routeIs('pos.index') || request()->routeIs('pos.receipt') ? 'on' : '' }}">
        เปิดบิลขาย (POS)
      </a>
    @endcan

    @if($u->hasAbility('sell') || $u->hasAbility('view-reports'))
      <a href="{{ route('pos.history') }}" class="{{ request()->routeIs('pos.history') ? 'on' : '' }}">
        ประวัติการขาย
      </a>
    @endif

    @if($u->hasAbility('accept-redeem'))
      <a href="{{ route('redeem.desk') }}" class="{{ request()->routeIs('redeem.desk') || request()->routeIs('redeem.receipt') ? 'on' : '' }}">
        รับแลกแต้ม
      </a>
      <a href="{{ route('redeem.history') }}" class="{{ request()->routeIs('redeem.history') ? 'on' : '' }}">
        ประวัติรับแลกแต้ม
      </a>
    @endif

    @if($u->hasAbility('manage-shop'))
      <a href="{{ route('shop.settings') }}" class="{{ request()->routeIs('shop.settings') ? 'on' : '' }}">
        ตั้งค่าหน้าร้าน
      </a>
    @endif

    @if($u->hasAbility('claim-money'))
      <a href="{{ route('claims.index') }}" class="{{ request()->routeIs('claims.*') ? 'on' : '' }}">
        เบิกเงินคืน
      </a>
    @endif

    @if($u->hasAbility('manage-subscriptions'))
      <a href="{{ route('subscriptions.index') }}" class="{{ request()->routeIs('subscriptions.*') ? 'on' : '' }}">
        สมาชิกร้านค้า
      </a>
    @endif

    @if($u->hasAbility('approve-claim'))
      @php
        $pendingClaims = \App\Models\ReimbursementClaim::where('status', 'submitted')->count();
      @endphp
      <a href="{{ route('admin.claims.index') }}" class="{{ request()->routeIs('admin.claims.*') ? 'on' : '' }}">
        อนุมัติใบเบิก
        @if($pendingClaims > 0)
          <span style="margin-left:auto;background:var(--brand);color:#fff;font-size:10.5px;padding:1px 7px;border-radius:99px;font-weight:700">{{ $pendingClaims }}</span>
        @endif
      </a>
    @endif

    @if($u->hasAbility('manage-packages') || $u->hasAbility('view-security'))
      <div class="group">เจ้าของระบบ</div>

      @if($u->hasAbility('manage-packages'))
        <a href="{{ route('admin.packages.index') }}" class="{{ request()->routeIs('admin.packages.*') ? 'on' : '' }}">
          แพ็กเกจและค่าแต้ม
        </a>
      @endif

      @if($u->hasAbility('view-security'))
        @php
          $newAlerts = \App\Models\AdminAlert::where('status', 'new')->count();
        @endphp
        <a href="{{ route('admin.security.index') }}" class="{{ request()->routeIs('admin.security.*') ? 'on' : '' }}">
          ศูนย์ความปลอดภัย
          @if($newAlerts > 0)
            <span style="margin-left:auto;background:var(--bad);color:#fff;font-size:10.5px;padding:1px 7px;border-radius:99px;font-weight:700">{{ $newAlerts }}</span>
          @endif
        </a>
      @endif

      <a href="{{ route('admin.settings.index') }}" class="{{ request()->routeIs('admin.settings.*') ? 'on' : '' }}">
        ⚙️ ตั้งค่าระบบ
      </a>
    @endif

    @php
      $trfPending = \App\Models\Transfer::whereIn('from_node_id', $u->visibleNodeIds())
          ->where('status', 'pending_approve')->count()
        + \App\Models\Transfer::whereIn('to_node_id', $u->visibleNodeIds())
          ->where('status', 'shipped')->count();
    @endphp
    <a href="{{ route('transfers.index') }}" class="{{ request()->routeIs('transfers.*') ? 'on' : '' }}">
      ใบโอนสินค้า
      @if($trfPending)
        <span class="badge b-amber" style="margin-left:auto">{{ $trfPending }}</span>
      @endif
    </a>

    <a href="{{ route('reports.stock') }}" class="{{ request()->routeIs('reports.stock') ? 'on' : '' }}">
      สต๊อกคงเหลือ
    </a>
    <a href="{{ route('reports.movements') }}" class="{{ request()->routeIs('reports.movements') ? 'on' : '' }}">
      ความเคลื่อนไหวสินค้า
    </a>

    @can('adjust-stock')
      <a href="{{ route('stock.count') }}" class="{{ request()->routeIs('stock.count*') ? 'on' : '' }}">
        นับสต๊อก / ปรับยอด
      </a>
    @endcan

    @if($u->hasAbility('manage-members') || $u->hasAbility('manage-nodes'))
      <div class="group">บริหารสายงาน</div>
      @if($u->hasAbility('manage-nodes'))
        <a href="{{ route('nodes.index') }}" class="{{ request()->routeIs('nodes.*') ? 'on' : '' }}">
          หน่วยงานในสังกัด
        </a>
      @endif
      @if($u->hasAbility('manage-members'))
        <a href="{{ route('members.index') }}" class="{{ request()->routeIs('members.*') ? 'on' : '' }}">
          สมาชิกและสิทธิ์
        </a>
      @endif
    @endif

    @can('manage-products')
      <div class="group">คลังสินค้ากลาง</div>
      <a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.*') ? 'on' : '' }}">
        สินค้าและล็อต QR
      </a>
    @endcan

    @if($u->hasAbility('view-reports'))
      <div class="group">ลูกค้าและคะแนน</div>
      @php
        $redeemPending = \App\Models\RewardRedemption::where('status', 'pending')->count();
      @endphp
      <a href="{{ route('customers.index') }}" class="{{ request()->routeIs('customers.index') || request()->routeIs('customers.show') ? 'on' : '' }}">
        ลูกค้าและคะแนน
      </a>
      <a href="{{ route('customers.rewards') }}" class="{{ request()->routeIs('customers.rewards') ? 'on' : '' }}">
        ของรางวัล
        @if($redeemPending)
          <span class="badge b-amber" style="margin-left:auto">{{ $redeemPending }}</span>
        @endif
      </a>

      <div class="group">รายงาน</div>
      <a href="{{ route('reports.summary') }}" class="{{ request()->routeIs('reports.summary') ? 'on' : '' }}">
        สรุปผลประกอบการ
      </a>
      <a href="{{ route('reports.qr') }}" class="{{ request()->routeIs('reports.qr') ? 'on' : '' }}">
        QR และคะแนนสะสม
      </a>
    @endif

    <div class="group">บัญชีของฉัน</div>
    <a href="{{ route('profile') }}" class="{{ request()->routeIs('profile') ? 'on' : '' }}">
      เปลี่ยนรหัสผ่าน
    </a>

    <a href="{{ route('profile.notify') }}" class="{{ request()->routeIs('profile.notify*') ? 'on' : '' }}">
      การแจ้งเตือน
    </a>
  </nav>

  <div class="who">
    <b>{{ $u->name }}</b>
    <span>{{ $u->email ?? $u->phone }}</span>
    <form method="POST" action="{{ route('logout') }}" style="margin-top:9px">
      @csrf
      <button class="btn btn-sm" style="width:100%">ออกจากระบบ</button>
    </form>
  </div>
</aside>
