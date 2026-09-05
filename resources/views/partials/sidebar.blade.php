@php
    $u = auth()->user();
    $lv = $u->level();
@endphp

<aside class="sidebar" id="sidebar">
  <div class="brand">
    <b>
      <img src="{{ brand_logo() }}" alt="" style="width:26px;height:26px;object-fit:contain;background:#fff;border-radius:7px;padding:2px">
      {{ config('app.name', 'RaoMembers') }}
    </b>
    <span>{{ $lv?->label() ?? 'ไม่ระบุระดับ' }} · {{ $u->node?->code }}</span>
  </div>

  <nav id="sidebarNav">

    {{-- ═══ 1. ภาพรวม (always visible) ═══ --}}
    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'on' : '' }}">
      <span class="nav-icon">📊</span>
      <span class="nav-text">ภาพรวม</span>
    </a>

    {{-- ═══ 2. ขายและแลกแต้ม ═══ --}}
    @if($u->hasAbility('sell') || $u->hasAbility('accept-redeem'))
    <div class="nav-group {{ (request()->routeIs('pos.*') || request()->routeIs('redeem.*')) ? 'open' : '' }}" data-group="sales">
      <button class="nav-group-toggle" type="button">
        <span class="nav-icon">💰</span>
        <span class="nav-text">ขายและแลกแต้ม</span>
        <span class="nav-chevron">›</span>
      </button>
      <div class="nav-group-body">
        @can('create', App\Models\Sale::class)
          <a href="{{ route('pos.index') }}" class="nav-link {{ request()->routeIs('pos.index') || request()->routeIs('pos.receipt') ? 'on' : '' }}">
            <span class="nav-icon">🛒</span><span class="nav-text">เปิดบิลขาย (POS)</span>
          </a>
        @endcan
        @if($u->hasAbility('sell') || $u->hasAbility('view-reports'))
          <a href="{{ route('pos.history') }}" class="nav-link {{ request()->routeIs('pos.history') ? 'on' : '' }}">
            <span class="nav-icon">📜</span><span class="nav-text">ประวัติการขาย</span>
          </a>
        @endif
        @if($u->hasAbility('accept-redeem'))
          <a href="{{ route('redeem.desk') }}" class="nav-link {{ request()->routeIs('redeem.desk') || request()->routeIs('redeem.receipt') ? 'on' : '' }}">
            <span class="nav-icon">🎁</span><span class="nav-text">รับแลกแต้ม</span>
          </a>
          <a href="{{ route('redeem.history') }}" class="nav-link {{ request()->routeIs('redeem.history') ? 'on' : '' }}">
            <span class="nav-icon">📋</span><span class="nav-text">ประวัติรับแลกแต้ม</span>
          </a>
        @endif
      </div>
    </div>
    @endif

    {{-- ═══ 3. คลังสินค้า ═══ --}}
    @php
      $trfPending = \App\Models\Transfer::whereIn('from_node_id', $u->visibleNodeIds())
          ->where('status', 'pending_approve')->count()
        + \App\Models\Transfer::whereIn('to_node_id', $u->visibleNodeIds())
          ->where('status', 'shipped')->count();
    @endphp
    <div class="nav-group {{ request()->routeIs('reports.stock') || request()->routeIs('transfers.*') || request()->routeIs('reports.movements') || request()->routeIs('stock.count*') || request()->routeIs('products.*') ? 'open' : '' }}" data-group="stock">
      <button class="nav-group-toggle" type="button">
        <span class="nav-icon">📦</span>
        <span class="nav-text">คลังสินค้า</span>
        @if($trfPending > 0)
          <span class="nav-badge badge-warn">{{ $trfPending }}</span>
        @endif
        <span class="nav-chevron">›</span>
      </button>
      <div class="nav-group-body">
        <a href="{{ route('reports.stock') }}" class="nav-link {{ request()->routeIs('reports.stock') ? 'on' : '' }}">
          <span class="nav-icon">📊</span><span class="nav-text">สต๊อกคงเหลือ</span>
        </a>
        <a href="{{ route('transfers.index') }}" class="nav-link {{ request()->routeIs('transfers.*') ? 'on' : '' }}">
          <span class="nav-icon">🔄</span><span class="nav-text">ใบโอนสินค้า</span>
          @if($trfPending > 0)
            <span class="nav-badge badge-warn">{{ $trfPending }}</span>
          @endif
        </a>
        <a href="{{ route('reports.movements') }}" class="nav-link {{ request()->routeIs('reports.movements') ? 'on' : '' }}">
          <span class="nav-icon">📈</span><span class="nav-text">ความเคลื่อนไหวสินค้า</span>
        </a>
        @can('adjust-stock')
          <a href="{{ route('stock.count') }}" class="nav-link {{ request()->routeIs('stock.count*') ? 'on' : '' }}">
            <span class="nav-icon">🔢</span><span class="nav-text">นับสต๊อก / ปรับยอด</span>
          </a>
        @endcan
        @can('manage-products')
          <a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.*') && !request()->routeIs('products.quick-stock*') ? 'on' : '' }}">
            <span class="nav-icon">🏷️</span><span class="nav-text">สินค้าและล็อต QR</span>
          </a>
          <a href="{{ route('products.quick-stock') }}" class="nav-link {{ request()->routeIs('products.quick-stock*') ? 'on' : '' }}">
            <span class="nav-icon">📱</span><span class="nav-text">เพิ่มสต๊อกด่วน</span>
          </a>
        @endcan
      </div>
    </div>

    {{-- ═══ 4. ลูกค้าและคะแนน ═══ --}}
    @if($u->hasAbility('view-reports'))
      @php $redeemPending = \App\Models\RewardRedemption::where('status', 'pending')->count(); @endphp
      <div class="nav-group {{ request()->routeIs('customers.*') ? 'open' : '' }}" data-group="customers">
        <button class="nav-group-toggle" type="button">
          <span class="nav-icon">⭐</span>
          <span class="nav-text">ลูกค้าและคะแนน</span>
          @if($redeemPending > 0)
            <span class="nav-badge badge-warn">{{ $redeemPending }}</span>
          @endif
          <span class="nav-chevron">›</span>
        </button>
        <div class="nav-group-body">
          <a href="{{ route('customers.index') }}" class="nav-link {{ request()->routeIs('customers.index') || request()->routeIs('customers.show') ? 'on' : '' }}">
            <span class="nav-icon">👥</span><span class="nav-text">ลูกค้าและคะแนน</span>
          </a>
          <a href="{{ route('customers.rewards') }}" class="nav-link {{ request()->routeIs('customers.rewards') ? 'on' : '' }}">
            <span class="nav-icon">🎁</span><span class="nav-text">ของรางวัล</span>
            @if($redeemPending > 0)
              <span class="nav-badge badge-warn">{{ $redeemPending }}</span>
            @endif
          </a>
        </div>
      </div>
    @endif

    {{-- ═══ 5. บริหารจัดการ ═══ --}}
    @if($u->hasAbility('manage-members') || $u->hasAbility('manage-nodes') || $u->hasAbility('manage-subscriptions') || $u->hasAbility('claim-money') || $u->hasAbility('approve-claim'))
      @php
        $pendingClaims = \App\Models\ReimbursementClaim::where('status', 'submitted')->count();
      @endphp
      <div class="nav-group {{ request()->routeIs('nodes.*') || request()->routeIs('members.*') || request()->routeIs('subscriptions.*') || request()->routeIs('claims.*') || request()->routeIs('admin.claims.*') ? 'open' : '' }}" data-group="manage">
        <button class="nav-group-toggle" type="button">
          <span class="nav-icon">🏢</span>
          <span class="nav-text">บริหารจัดการ</span>
          @if($pendingClaims > 0)
            <span class="nav-badge badge-warn">{{ $pendingClaims }}</span>
          @endif
          <span class="nav-chevron">›</span>
        </button>
        <div class="nav-group-body">
          @if($u->hasAbility('manage-nodes'))
            <a href="{{ route('nodes.index') }}" class="nav-link {{ request()->routeIs('nodes.*') ? 'on' : '' }}">
              <span class="nav-icon">🏛️</span><span class="nav-text">หน่วยงานในสังกัด</span>
            </a>
          @endif
          @if($u->hasAbility('manage-members'))
            <a href="{{ route('members.index') }}" class="nav-link {{ request()->routeIs('members.*') ? 'on' : '' }}">
              <span class="nav-icon">👤</span><span class="nav-text">สมาชิกและสิทธิ์</span>
            </a>
          @endif
          @if($u->hasAbility('manage-subscriptions'))
            <a href="{{ route('subscriptions.index') }}" class="nav-link {{ request()->routeIs('subscriptions.*') ? 'on' : '' }}">
              <span class="nav-icon">💳</span><span class="nav-text">สมาชิกร้านค้า</span>
            </a>
          @endif
          @if($u->hasAbility('claim-money'))
            <a href="{{ route('claims.index') }}" class="nav-link {{ request()->routeIs('claims.*') ? 'on' : '' }}">
              <span class="nav-icon">💵</span><span class="nav-text">เบิกเงินคืน</span>
            </a>
          @endif
          @if($u->hasAbility('approve-claim'))
            <a href="{{ route('admin.claims.index') }}" class="nav-link {{ request()->routeIs('admin.claims.*') ? 'on' : '' }}">
              <span class="nav-icon">✅</span><span class="nav-text">อนุมัติใบเบิก</span>
              @if($pendingClaims > 0)
                <span class="nav-badge badge-brand">{{ $pendingClaims }}</span>
              @endif
            </a>
          @endif
        </div>
      </div>
    @endif

    {{-- ═══ 6. หน้าร้าน ═══ --}}
    @if($u->hasAbility('manage-shop'))
      <div class="nav-group {{ request()->routeIs('shop.*') ? 'open' : '' }}" data-group="shop">
        <button class="nav-group-toggle" type="button">
          <span class="nav-icon">🏪</span>
          <span class="nav-text">หน้าร้าน</span>
          <span class="nav-chevron">›</span>
        </button>
        <div class="nav-group-body">
          <a href="{{ route('shop.settings') }}" class="nav-link {{ request()->routeIs('shop.settings') ? 'on' : '' }}">
            <span class="nav-icon">⚙️</span><span class="nav-text">ตั้งค่าหน้าร้าน</span>
          </a>
        </div>
      </div>
    @endif

    {{-- ═══ 7. รายงาน ═══ --}}
    @if($u->hasAbility('view-reports'))
      <div class="nav-group {{ request()->routeIs('reports.summary') || request()->routeIs('reports.qr') ? 'open' : '' }}" data-group="reports">
        <button class="nav-group-toggle" type="button">
          <span class="nav-icon">📈</span>
          <span class="nav-text">รายงาน</span>
          <span class="nav-chevron">›</span>
        </button>
        <div class="nav-group-body">
          <a href="{{ route('reports.summary') }}" class="nav-link {{ request()->routeIs('reports.summary') ? 'on' : '' }}">
            <span class="nav-icon">📊</span><span class="nav-text">สรุปผลประกอบการ</span>
          </a>
          <a href="{{ route('reports.qr') }}" class="nav-link {{ request()->routeIs('reports.qr') ? 'on' : '' }}">
            <span class="nav-icon">📱</span><span class="nav-text">QR และคะแนนสะสม</span>
          </a>
        </div>
      </div>
    @endif

    {{-- ═══ 8. เจ้าของระบบ (Admin) ═══ --}}
    @if($u->hasAbility('manage-packages') || $u->hasAbility('view-security'))
      @php $newAlerts = \App\Models\AdminAlert::where('status', 'new')->count(); @endphp
      <div class="nav-group {{ request()->routeIs('admin.packages.*') || request()->routeIs('admin.security.*') || request()->routeIs('admin.settings.*') || request()->routeIs('admin.brand.*') ? 'open' : '' }}" data-group="admin">
        <button class="nav-group-toggle" type="button">
          <span class="nav-icon">⚙️</span>
          <span class="nav-text">เจ้าของระบบ</span>
          @if($newAlerts > 0)
            <span class="nav-badge badge-danger">{{ $newAlerts }}</span>
          @endif
          <span class="nav-chevron">›</span>
        </button>
        <div class="nav-group-body">
          @if($u->hasAbility('manage-packages'))
            <a href="{{ route('admin.packages.index') }}" class="nav-link {{ request()->routeIs('admin.packages.*') ? 'on' : '' }}">
              <span class="nav-icon">📦</span><span class="nav-text">แพ็กเกจและค่าแต้ม</span>
            </a>
          @endif
          @if($u->hasAbility('view-security'))
            <a href="{{ route('admin.security.index') }}" class="nav-link {{ request()->routeIs('admin.security.*') ? 'on' : '' }}">
              <span class="nav-icon">🔒</span><span class="nav-text">ศูนย์ความปลอดภัย</span>
              @if($newAlerts > 0)
                <span class="nav-badge badge-danger">{{ $newAlerts }}</span>
              @endif
            </a>
          @endif
          <a href="{{ route('admin.settings.index') }}" class="nav-link {{ request()->routeIs('admin.settings.*') ? 'on' : '' }}">
            <span class="nav-icon">🔧</span><span class="nav-text">ตั้งค่าระบบ</span>
          </a>
          <a href="{{ route('admin.brand.index') }}" class="nav-link {{ request()->routeIs('admin.brand.*') ? 'on' : '' }}">
            <span class="nav-icon">🖼️</span><span class="nav-text">ตั้งค่าโลโก้</span>
          </a>
        </div>
      </div>
    @endif

    {{-- ═══ 9. บัญชีและการเงิน (Role-based) ═══ --}}
    @if($u->hasAbility('create-quotation') || $u->hasAbility('create-invoice') || $u->hasAbility('create-receipt') || $u->hasAbility('create-delivery') || $u->hasAbility('create-payment') || $u->hasAbility('create-purchase-order') || $u->hasAbility('create-credit-note') || $u->hasAbility('view-financial-statements'))
      @php $isAcct = request()->routeIs('accounting.*'); @endphp
      <div class="nav-group {{ $isAcct ? 'open' : '' }}" data-group="accounting">
        <button class="nav-group-toggle" type="button">
          <span class="nav-icon">📒</span>
          <span class="nav-text">บัญชีและการเงิน</span>
          <span class="nav-chevron">›</span>
        </button>
        <div class="nav-group-body">

          {{-- Dashboard --}}
          @php
            $overdueCount = \App\Models\Invoice::whereIn('org_node_id', $u->visibleNodeIds())
                ->where('status', 'overdue')->count();
          @endphp
          <a href="{{ route('accounting.dashboard') }}" class="nav-link {{ request()->routeIs('accounting.dashboard') ? 'on' : '' }}">
            <span class="nav-icon">📊</span><span class="nav-text">Dashboard บัญชี</span>
            @if($overdueCount > 0)
              <span class="nav-badge badge-danger">{{ $overdueCount }}</span>
            @endif
          </a>

          {{-- AR Aging สำหรับ Agent --}}
          @if($u->hasAbility('view-ar-report'))
          <a href="{{ route('accounting.ar-aging') }}" class="nav-link {{ request()->routeIs('accounting.ar-aging') ? 'on' : '' }}">
            <span class="nav-icon">📋</span><span class="nav-text">ลูกหนี้ค้างรับ</span>
          </a>
          @endif

          {{-- เอกสารขาย --}}
          @if($u->hasAbility('create-quotation') || $u->hasAbility('create-invoice') || $u->hasAbility('create-receipt') || $u->hasAbility('create-credit-note'))
          <div class="nav-subgroup {{ request()->routeIs('accounting.quotations*') || request()->routeIs('accounting.invoices*') || request()->routeIs('accounting.receipts*') || request()->routeIs('accounting.tax-invoices*') || request()->routeIs('accounting.credit*') ? 'open' : '' }}" data-subgroup="sales">
            <button class="nav-sub-toggle" type="button">
              <span class="nav-icon">📄</span>
              <span class="nav-text">เอกสารขาย</span>
              <span class="nav-chevron sm">›</span>
            </button>
            <div class="nav-sub-body">
              @if($u->hasAbility('create-quotation'))
              <a href="{{ route('accounting.quotations') }}" class="nav-link {{ request()->routeIs('accounting.quotations*') ? 'on' : '' }}">
                <span class="nav-icon">📋</span><span class="nav-text">ใบเสนอราคา</span>
              </a>
              @endif
              @if($u->hasAbility('create-invoice'))
              <a href="{{ route('accounting.invoices') }}" class="nav-link {{ request()->routeIs('accounting.invoices*') ? 'on' : '' }}">
                <span class="nav-icon">📄</span><span class="nav-text">บิลเรียกเก็บ</span>
              </a>
              @endif
              @if($u->hasAbility('create-receipt'))
              <a href="{{ route('accounting.receipts') }}" class="nav-link {{ request()->routeIs('accounting.receipts*') ? 'on' : '' }}">
                <span class="nav-icon">💰</span><span class="nav-text">ใบเสร็จรับเงิน</span>
              </a>
              @endif
              @if($u->hasAbility('create-tax-invoice'))
              <a href="{{ route('accounting.tax-invoices') }}" class="nav-link {{ request()->routeIs('accounting.tax-invoices*') ? 'on' : '' }}">
                <span class="nav-icon">🧾</span><span class="nav-text">ใบกำกับภาษี</span>
              </a>
              @endif
              @if($u->hasAbility('create-credit-note'))
              <a href="{{ route('accounting.credit') }}" class="nav-link {{ request()->routeIs('accounting.credit*') ? 'on' : '' }}">
                <span class="nav-icon">↩️</span><span class="nav-text">ใบลดหนี้</span>
              </a>
              @endif
            </div>
          </div>
          @endif

          {{-- เอกสารจัดซื้อ --}}
          @if($u->hasAbility('create-purchase-order') || $u->hasAbility('create-payment'))
          <div class="nav-subgroup {{ request()->routeIs('accounting.po*') || request()->routeIs('accounting.payments*') || request()->routeIs('accounting.wht*') || request()->routeIs('accounting.withholding*') ? 'open' : '' }}" data-subgroup="purchase">
            <button class="nav-sub-toggle" type="button">
              <span class="nav-icon">🛒</span>
              <span class="nav-text">เอกสารจัดซื้อ</span>
              <span class="nav-chevron sm">›</span>
            </button>
            <div class="nav-sub-body">
              @if($u->hasAbility('create-purchase-order'))
              <a href="{{ route('accounting.po') }}" class="nav-link {{ request()->routeIs('accounting.po*') ? 'on' : '' }}">
                <span class="nav-icon">🛒</span><span class="nav-text">ใบสั่งซื้อ</span>
              </a>
              @endif
              @if($u->hasAbility('create-payment'))
              <a href="{{ route('accounting.payments') }}" class="nav-link {{ request()->routeIs('accounting.payments*') ? 'on' : '' }}">
                <span class="nav-icon">💸</span><span class="nav-text">บิลจ่าย</span>
              </a>
              <a href="{{ route('accounting.withholding-taxes') }}" class="nav-link {{ request()->routeIs('accounting.wht*') || request()->routeIs('accounting.withholding*') ? 'on' : '' }}">
                <span class="nav-icon">📋</span><span class="nav-text">หัก ณ ที่จ่าย</span>
              </a>
              @endif
            </div>
          </div>
          @endif

          {{-- ส่งของ --}}
          @if($u->hasAbility('create-delivery'))
          <a href="{{ route('accounting.delivery') }}" class="nav-link {{ request()->routeIs('accounting.delivery*') ? 'on' : '' }}">
            <span class="nav-icon">🚚</span><span class="nav-text">ใบส่งของ</span>
          </a>
          @endif

          {{-- ตรวจสอบ/บัญชี --}}
          @if($u->hasAbility('view-financial-statements'))
          <div class="nav-subgroup {{ request()->routeIs('accounting.stock-ledger') || request()->routeIs('accounting.audit') || request()->routeIs('accounting.journals*') ? 'open' : '' }}" data-subgroup="audit">
            <button class="nav-sub-toggle" type="button">
              <span class="nav-icon">🔍</span>
              <span class="nav-text">ตรวจสอบ / บัญชี</span>
              <span class="nav-chevron sm">›</span>
            </button>
            <div class="nav-sub-body">
              <a href="{{ route('accounting.stock-ledger') }}" class="nav-link {{ request()->routeIs('accounting.stock-ledger') ? 'on' : '' }}">
                <span class="nav-icon">📋</span><span class="nav-text">Stock Ledger</span>
              </a>
              <a href="{{ route('accounting.audit') }}" class="nav-link {{ request()->routeIs('accounting.audit') ? 'on' : '' }}">
                <span class="nav-icon">🔍</span><span class="nav-text">Audit ตรวจสอบยอด</span>
              </a>
              @if($u->hasAbility('manage-journals'))
              <a href="{{ route('accounting.journals') }}" class="nav-link {{ request()->routeIs('accounting.journals*') ? 'on' : '' }}">
                <span class="nav-icon">📒</span><span class="nav-text">ลงบัญชีแยก</span>
              </a>
              @endif
            </div>
          </div>
          @endif

          {{-- งบการเงิน (เจ้าของระบบเท่านั้น) --}}
          @if($u->hasAbility('view-financial-statements'))
          <div class="nav-subgroup {{ request()->routeIs('accounting.general-ledger') || request()->routeIs('accounting.trial-balance') || request()->routeIs('accounting.profit-loss') || request()->routeIs('accounting.balance-sheet') || request()->routeIs('accounting.aging-report') || request()->routeIs('accounting.reports') ? 'open' : '' }}" data-subgroup="statements">
            <button class="nav-sub-toggle" type="button">
              <span class="nav-icon">📊</span>
              <span class="nav-text">งบการเงิน</span>
              <span class="nav-chevron sm">›</span>
            </button>
            <div class="nav-sub-body">
              <a href="{{ route('accounting.reports') }}" class="nav-link {{ request()->routeIs('accounting.reports') ? 'on' : '' }}">
                <span class="nav-icon">📈</span><span class="nav-text">รายงานบัญชี</span>
              </a>
              <a href="{{ route('accounting.general-ledger') }}" class="nav-link {{ request()->routeIs('accounting.general-ledger') ? 'on' : '' }}">
                <span class="nav-icon">📒</span><span class="nav-text">General Ledger</span>
              </a>
              <a href="{{ route('accounting.trial-balance') }}" class="nav-link {{ request()->routeIs('accounting.trial-balance') ? 'on' : '' }}">
                <span class="nav-icon">⚖️</span><span class="nav-text">งบทดลอง</span>
              </a>
              <a href="{{ route('accounting.profit-loss') }}" class="nav-link {{ request()->routeIs('accounting.profit-loss') ? 'on' : '' }}">
                <span class="nav-icon">📈</span><span class="nav-text">งบกำไรขาดทุน</span>
              </a>
              <a href="{{ route('accounting.balance-sheet') }}" class="nav-link {{ request()->routeIs('accounting.balance-sheet') ? 'on' : '' }}">
                <span class="nav-icon">🏦</span><span class="nav-text">งบแสดงฐานะ</span>
              </a>
              <a href="{{ route('accounting.aging-report') }}" class="nav-link {{ request()->routeIs('accounting.aging-report') ? 'on' : '' }}">
                <span class="nav-icon">⏳</span><span class="nav-text">AR/AP Aging</span>
              </a>
              <a href="{{ route('accounting.chart') }}" class="nav-link {{ request()->routeIs('accounting.chart') ? 'on' : '' }}">
                <span class="nav-icon">🗂️</span><span class="nav-text">ผังบัญชี</span>
              </a>
            </div>
          </div>
          @endif

        </div>
      </div>
    @endif

    {{-- ═══ 10. บัญชีของฉัน ═══ --}}
    <div class="nav-group {{ request()->routeIs('profile*') ? 'open' : '' }}" data-group="profile">
      <button class="nav-group-toggle" type="button">
        <span class="nav-icon">👤</span>
        <span class="nav-text">บัญชีของฉัน</span>
        <span class="nav-chevron">›</span>
      </button>
      <div class="nav-group-body">
        <a href="{{ route('profile') }}" class="nav-link {{ request()->routeIs('profile') ? 'on' : '' }}">
          <span class="nav-icon">🔑</span><span class="nav-text">เปลี่ยนรหัสผ่าน</span>
        </a>
        <a href="{{ route('profile.notify') }}" class="nav-link {{ request()->routeIs('profile.notify*') ? 'on' : '' }}">
          <span class="nav-icon">🔔</span><span class="nav-text">การแจ้งเตือน</span>
        </a>
      </div>
    </div>

  </nav>

  <div class="who">
    <b>{{ $u->name }}</b>
    <span>{{ $u->email ?? $u->phone }}</span>
    <form method="POST" action="{{ route('logout') }}" style="margin-top:9px">
      @csrf
      <button class="btn btn-sm" style="width:100%">🚪 ออกจากระบบ</button>
    </form>
  </div>
</aside>

@push('scripts')
<script>
(function() {
  const STORAGE_KEY = 'sidebar_state';
  const sidebar = document.getElementById('sidebarNav');
  if (!sidebar) return;

  // Load saved state
  let saved = {};
  try { saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); } catch(e) {}

  // ═══════════════════════════════════════
  // Initialize: เปิด sub-groups ก่อน → แล้วเปิด parent groups
  // ═══════════════════════════════════════

  // Step 1: เปิด sub-groups ที่มี active link
  sidebar.querySelectorAll('.nav-subgroup').forEach(function(sub) {
    const parentGroup = sub.closest('.nav-group');
    const parentKey = parentGroup ? parentGroup.dataset.group : '';
    const subKey = parentKey + '_' + sub.dataset.subgroup;
    const subHasActive = sub.querySelector('.nav-link.on') !== null;
    const subOpen = saved.hasOwnProperty(subKey) ? saved[subKey] : (subHasActive || sub.classList.contains('open'));

    if (subOpen) {
      sub.classList.add('open');
      // ไม่ต้อง set max-height ที่นี่ — จะทำตอน parent เปิด
    } else {
      sub.classList.remove('open');
      sub.querySelector('.nav-sub-body').style.maxHeight = '0px';
    }
  });

  // Step 2: เปิด parent groups (sub-groups เปิดแล้ว → scrollHeight ถูกต้อง)
  sidebar.querySelectorAll('.nav-group').forEach(function(group) {
    const key = group.dataset.group;
    const hasActive = group.querySelector('.nav-link.on') !== null;
    const isOpen = saved.hasOwnProperty(key) ? saved[key] : (hasActive || group.classList.contains('open'));
    const body = group.querySelector('.nav-group-body');

    if (isOpen) {
      group.classList.add('open');
      // ใช้ max-height: none ทันที (ไม่ต้องรอ transition)
      body.style.maxHeight = 'none';
    } else {
      group.classList.remove('open');
      body.style.maxHeight = '0px';
    }

    // Recalculate sub-group heights (ตอนนี้ parent เปิดแล้ว)
    group.querySelectorAll('.nav-subgroup.open .nav-sub-body').forEach(function(subBody) {
      subBody.style.maxHeight = 'none';
    });
  });

  // ═══════════════════════════════════════
  // Toggle: group (with animation)
  // ═══════════════════════════════════════
  sidebar.querySelectorAll('.nav-group-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const group = this.closest('.nav-group');
      const body = group.querySelector('.nav-group-body');
      const key = group.dataset.group;
      const isOpen = group.classList.contains('open');

      if (isOpen) {
        // Close: animate from current height → 0
        body.style.maxHeight = body.scrollHeight + 'px';
        // Force reflow
        body.offsetHeight;
        body.style.maxHeight = '0px';
        group.classList.remove('open');
        saved[key] = false;
      } else {
        // Open: animate from 0 → scrollHeight → none
        group.classList.add('open');
        body.style.maxHeight = '0px';
        // Force reflow
        body.offsetHeight;
        body.style.maxHeight = body.scrollHeight + 'px';
        saved[key] = true;

        // After transition → set none for dynamic content
        body.addEventListener('transitionend', function handler() {
          if (group.classList.contains('open')) {
            body.style.maxHeight = 'none';
          }
          body.removeEventListener('transitionend', handler);
        });
      }

      localStorage.setItem(STORAGE_KEY, JSON.stringify(saved));
    });
  });

  // ═══════════════════════════════════════
  // Toggle: sub-group (with animation + recalc parent)
  // ═══════════════════════════════════════
  sidebar.querySelectorAll('.nav-sub-toggle').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      const sub = this.closest('.nav-subgroup');
      const body = sub.querySelector('.nav-sub-body');
      const parentGroup = sub.closest('.nav-group');
      const parentBody = parentGroup.querySelector('.nav-group-body');
      const subKey = parentGroup.dataset.group + '_' + sub.dataset.subgroup;
      const isOpen = sub.classList.contains('open');

      if (isOpen) {
        body.style.maxHeight = body.scrollHeight + 'px';
        body.offsetHeight;
        body.style.maxHeight = '0px';
        sub.classList.remove('open');
        saved[subKey] = false;
      } else {
        sub.classList.add('open');
        body.style.maxHeight = '0px';
        body.offsetHeight;
        body.style.maxHeight = body.scrollHeight + 'px';
        saved[subKey] = true;

        body.addEventListener('transitionend', function handler() {
          if (sub.classList.contains('open')) {
            body.style.maxHeight = 'none';
          }
          body.removeEventListener('transitionend', handler);
        });
      }

      // Recalculate parent height (parent ต้อง open อยู่แล้ว)
      if (parentGroup.classList.contains('open') && parentBody.style.maxHeight !== 'none') {
        parentBody.style.maxHeight = 'none';
      }

      localStorage.setItem(STORAGE_KEY, JSON.stringify(saved));
    });
  });

  // Scroll to active link
  requestAnimationFrame(function() {
    var active = sidebar.querySelector('.nav-link.on');
    if (active) {
      active.scrollIntoView({ block: 'nearest', behavior: 'instant' });
    }
  });
})();
</script>
@endpush
