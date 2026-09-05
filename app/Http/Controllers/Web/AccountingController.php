<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\{
    Invoice, InvoiceItem, Receipt, Payment, TaxInvoice, WithholdingTax,
    JournalEntry, JournalLine, Account, Product, Sale,
    DeliveryNote, DeliveryItem, CreditNote, CreditItem, StockLedger,
    Quotation, QuotationItem, PurchaseOrder, PurchaseOrderItem,
    ManualJournal, ManualJournalLine
};
use App\Services\DocSequenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AccountingController extends Controller
{
    public function __construct(private DocSequenceService $docSeq) {}

    /** หา node_id ของ user — ถ้าไม่มี (admin) ใช้ node แรกที่เห็น */
    private function resolveNodeId(Request $request): int
    {
        return $request->user()->node_id
            ?? $request->user()->visibleNodeIds()[0]
            ?? 0;
    }

    // ════════════════════════════════════
    // 📊 Dashboard บัญชี
    // ════════════════════════════════════
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $nodeIds = $user->visibleNodeIds();

        $invoices = Invoice::whereIn('org_node_id', $nodeIds);
        $receipts = Receipt::whereIn('org_node_id', $nodeIds);
        $payments = Payment::whereIn('org_node_id', $nodeIds);

        return view('accounting.dashboard', [
            'totalInvoiced' => (clone $invoices)->where('status', '!=', 'void')->sum('total'),
            'totalReceived' => (clone $receipts)->sum('amount'),
            'totalPaid' => (clone $payments)->sum('amount'),
            'outstanding' => (clone $invoices)->whereIn('status', ['issued', 'partial', 'overdue'])->sum('balance'),
            'overdueCount' => (clone $invoices)->where('status', 'overdue')->count(),
            'overdueAmount' => (clone $invoices)->where('status', 'overdue')->sum('balance'),
            'recentInvoices' => (clone $invoices)->latest('invoice_date')->limit(5)->get(),
            'recentReceipts' => (clone $receipts)->latest('receipt_date')->limit(5)->get(),
            'monthlyInvoices' => (clone $invoices)
                ->whereYear('invoice_date', now()->year)
                ->selectRaw('MONTH(invoice_date) as m, SUM(total) as total')
                ->groupBy('m')->orderBy('m')->pluck('total', 'm'),
            'totalDeliveries' => DeliveryNote::whereIn('org_node_id', $nodeIds)->count(),
            'pendingShip' => DeliveryNote::whereIn('org_node_id', $nodeIds)->where('status', 'ready')->count(),
            'totalCredits' => CreditNote::whereIn('org_node_id', $nodeIds)->where('status', 'confirmed')->sum('total_amount'),
            'pendingCredits' => CreditNote::whereIn('org_node_id', $nodeIds)->where('status', 'draft')->count(),
        ]);
    }

    // ════════════════════════════════════
    // 📄 บิลเรียกเก็บ (Invoices)
    // ════════════════════════════════════
    public function invoices(Request $request)
    {
        $q = Invoice::whereIn('org_node_id', $request->user()->visibleNodeIds())
            ->with('customerNode')
            ->latest('invoice_date');

        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('q')) $q->where(function($w) use ($request) {
            $w->where('invoice_no', 'like', "%{$request->q}%")
              ->orWhere('customer_name', 'like', "%{$request->q}%");
        });

        return view('accounting.invoices.index', ['invoices' => $q->paginate(20)]);
    }

    public function createInvoice(Request $request)
    {
        abort_unless($request->user()->hasAbility('create-invoice'), 403, 'ไม่มีสิทธิ์สร้างบิลเรียกเก็บ');
        $products = Product::whereIn('id', function($q) use ($request) {
            $q->select('product_id')->from('product_lots')
              ->whereIn('org_node_id', $request->user()->visibleNodeIds());
        })->get();

        return view('accounting.invoices.form', [
            'invoice' => null,
            'products' => $products,
            'docNo' => $this->docSeq->next('INV', $this->resolveNodeId($request)),
        ]);
    }

    public function storeInvoice(Request $request)
    {
        $data = $request->validate([
            'customer_name' => 'required|max:200',
            'customer_address' => 'nullable|max:500',
            'customer_tax_id' => 'nullable|max:20',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'vat_rate' => 'required|numeric|min:0|max:100',
            'notes' => 'nullable|max:1000',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|max:500',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $invoice = DB::transaction(function () use ($data, $request) {
            $inv = Invoice::create([
                'invoice_no' => $this->docSeq->next('INV', $this->resolveNodeId($request), $data['invoice_date']),
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'],
                'org_node_id' => $this->resolveNodeId($request),
                'customer_name' => $data['customer_name'],
                'customer_address' => $data['customer_address'],
                'customer_tax_id' => $data['customer_tax_id'],
                'vat_rate' => $data['vat_rate'],
                'notes' => $data['notes'],
                'created_by' => $request->user()->id,
                'status' => 'issued',
            ]);

            foreach ($data['items'] as $i => $item) {
                InvoiceItem::create([
                    'invoice_id' => $inv->id,
                    'description' => $item['description'],
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'amount' => $item['qty'] * $item['unit_price'],
                    'sort_order' => $i,
                ]);
            }

            $inv->recalc();
            $inv->save();

            return $inv;
        });

        return redirect()->route('accounting.invoices.show', $invoice)
            ->with('status', 'สร้างบิลเรียกเก็บสำเร็จ');
    }

    public function showInvoice(Invoice $invoice)
    {
        $invoice->load(['items', 'receipts', 'taxInvoice', 'customerNode', 'creditNotes']);
        return view('accounting.invoices.show', compact('invoice'));
    }

    public function voidInvoice(Request $request, Invoice $invoice)
    {
        if ($invoice->status === 'void') {
            return back()->with('error', '❌ บิลถูกยกเลิกแล้ว');
        }
        if ($invoice->status === 'paid') {
            return back()->with('error', '❌ บิลที่ชำระแล้วไม่สามารถยกเลิกได้ ให้ใช้ใบลดหนี้แทน');
        }
        $invoice->update(['status' => 'void', 'balance' => 0]);
        return redirect()->route('accounting.invoices')->with('flash', '❌ ยกเลิกบิล ' . $invoice->invoice_no . ' แล้ว');
    }

    // ════════════════════════════════════
    // 💰 บิลรับ (Receipts)
    // ════════════════════════════════════
    public function receipts(Request $request)
    {
        $q = Receipt::whereIn('org_node_id', $request->user()->visibleNodeIds())
            ->with('invoice')
            ->latest('receipt_date');

        return view('accounting.receipts.index', ['receipts' => $q->paginate(20)]);
    }

    public function createReceipt(Request $request, ?Invoice $invoice = null)
    {
        abort_unless($request->user()->hasAbility('create-receipt'), 403, 'ไม่มีสิทธิ์สร้างใบเสร็จรับเงิน');
        $invoices = Invoice::whereIn('org_node_id', $request->user()->visibleNodeIds())
            ->whereIn('status', ['issued', 'partial', 'overdue'])
            ->where('balance', '>', 0)
            ->get();

        return view('accounting.receipts.form', [
            'receipt' => null,
            'invoice' => $invoice,
            'invoices' => $invoices,
            'docNo' => $this->docSeq->next('RCP', $this->resolveNodeId($request)),
        ]);
    }

    public function storeReceipt(Request $request)
    {
        $data = $request->validate([
            'receipt_date' => 'required|date',
            'payer_name' => 'required|max:200',
            'payer_tax_id' => 'nullable|max:20',
            'invoice_id' => 'nullable|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,bank_transfer,promptpay,cheque,credit_card',
            'bank_ref' => 'nullable|max:100',
            'notes' => 'nullable|max:1000',
        ]);

        $receipt = DB::transaction(function () use ($data, $request) {
            $rcp = Receipt::create($data + [
                'receipt_no' => $this->docSeq->next('RCP', $this->resolveNodeId($request), $data['receipt_date']),
                'org_node_id' => $this->resolveNodeId($request),
                'created_by' => $request->user()->id,
            ]);

            // อัปเดต invoice balance
            if ($rcp->invoice_id) {
                $inv = Invoice::find($rcp->invoice_id);
                $inv->recalc();
                $inv->save();
            }

            return $rcp;
        });

        return redirect()->route('accounting.receipts.show', $receipt)
            ->with('status', 'สร้างบิลรับสำเร็จ');
    }

    public function showReceipt(Receipt $receipt)
    {
        $receipt->load(['invoice', 'node']);
        return view('accounting.receipts.show', compact('receipt'));
    }

    // ════════════════════════════════════
    // 💸 บิลจ่าย (Payments)
    // ════════════════════════════════════
    public function payments(Request $request)
    {
        $q = Payment::whereIn('org_node_id', $request->user()->visibleNodeIds())
            ->with('withholdingTax')
            ->latest('payment_date');

        return view('accounting.payments.index', ['payments' => $q->paginate(20)]);
    }

    public function createPayment(Request $request)
    {
        abort_unless($request->user()->hasAbility('create-payment'), 403, 'ไม่มีสิทธิ์สร้างบิลจ่าย');
        return view('accounting.payments.form', [
            'payment' => null,
            'docNo' => $this->docSeq->next('PAY', $this->resolveNodeId($request)),
            'whtRates' => WithholdingTax::commonRates(),
        ]);
    }

    public function storePayment(Request $request)
    {
        $data = $request->validate([
            'payment_date'   => 'required|date',
            'payee_name'     => 'required|max:200',
            'payee_tax_id'   => 'nullable|max:20',
            'income_amount'  => 'required|numeric|min:0.01',
            'vat_rate'       => 'nullable|numeric|min:0|max:100',
            'amount'         => 'required|numeric|min:0.01',  // ยอดรวม (income + VAT)
            'method'         => 'required|in:cash,bank_transfer,promptpay,cheque',
            'bank_ref'       => 'nullable|max:100',
            'description'    => 'nullable|max:1000',
            'wht_rate'       => 'nullable|numeric|min:0|max:100',
            'wht_amount'     => 'nullable|numeric|min:0',
            'net_amount'     => 'nullable|numeric|min:0',
            'income_type'    => 'nullable|max:200',
        ]);

        // ═══ สูตรคำนวณที่ถูกต้อง ═══
        // income_amount = ยอดก่อน VAT
        // vat_amount    = income_amount × vat_rate / 100
        // amount        = income_amount + vat_amount (ยอดรวม)
        // wht_amount    = income_amount × wht_rate / 100  (WHT คำนวณจากยอดก่อน VAT!)
        // net_amount    = amount - wht_amount (จ่ายสุทธิ)

        $incomeAmount = $data['income_amount'];
        $vatRate      = $data['vat_rate'] ?? 7;
        $whtRate      = $data['wht_rate'] ?? 0;

        $vatAmount = bcmul($incomeAmount, bcdiv($vatRate, 100, 4), 2);
        $amount    = bcadd($incomeAmount, $vatAmount, 2);
        $whtAmount = bcmul($incomeAmount, bcdiv($whtRate, 100, 4), 2);
        $netAmount = bcsub($amount, $whtAmount, 2);

        $payment = DB::transaction(function () use ($data, $request, $incomeAmount, $amount, $whtRate, $whtAmount, $netAmount) {
            $pay = Payment::create([
                'payment_no'   => $this->docSeq->next('PAY', $this->resolveNodeId($request), $data['payment_date']),
                'payment_date' => $data['payment_date'],
                'org_node_id'  => $this->resolveNodeId($request),
                'payee_name'   => $data['payee_name'],
                'payee_tax_id' => $data['payee_tax_id'] ?? null,
                'amount'       => $amount,
                'method'       => $data['method'],
                'bank_ref'     => $data['bank_ref'] ?? null,
                'description'  => $data['description'] ?? null,
                'created_by'   => $request->user()->id,
            ]);

            // สร้างใบหัก ณ ที่จ่าย (WHT คำนวณจาก income_amount ไม่ใช่ amount)
            if ($whtRate > 0 && $whtAmount > 0) {
                WithholdingTax::create([
                    'wht_no'        => $this->docSeq->next('WHT', $this->resolveNodeId($request), $data['payment_date']),
                    'issue_date'    => $data['payment_date'],
                    'org_node_id'   => $this->resolveNodeId($request),
                    'payee_name'    => $data['payee_name'],
                    'payee_tax_id'  => $data['payee_tax_id'] ?? null,
                    'income_amount' => $incomeAmount,
                    'wht_rate'      => $whtRate,
                    'wht_amount'    => $whtAmount,
                    'net_amount'    => $netAmount,
                    'income_type'   => $data['income_type'] ?? null,
                    'payment_id'    => $pay->id,
                    'created_by'    => $request->user()->id,
                ]);
            }

            return $pay;
        });

        return redirect()->route('accounting.payments.show', $payment)
            ->with('status', 'สร้างบิลจ่ายสำเร็จ');
    }

    public function showPayment(Payment $payment)
    {
        $payment->load(['withholdingTax', 'node']);
        return view('accounting.payments.show', compact('payment'));
    }

    // ════════════════════════════════════
    // 🧾 ใบกำกับภาษี (Tax Invoices)
    // ════════════════════════════════════
    public function taxInvoices(Request $request)
    {
        $q = TaxInvoice::whereIn('org_node_id', $request->user()->visibleNodeIds())
            ->with('invoice')
            ->latest('issue_date');

        return view('accounting.tax-invoices.index', ['taxInvoices' => $q->paginate(20)]);
    }

    public function createTaxInvoice(Request $request, ?Invoice $invoice = null)
    {
        abort_unless($request->user()->hasAbility('create-tax-invoice'), 403, 'ไม่มีสิทธิ์สร้างใบกำกับภาษี');
        $invoices = Invoice::whereIn('org_node_id', $request->user()->visibleNodeIds())
            ->whereDoesntHave('taxInvoice')
            ->whereIn('status', ['issued', 'partial', 'paid'])
            ->get();

        return view('accounting.tax-invoices.form', [
            'taxInvoice' => null,
            'invoice' => $invoice,
            'invoices' => $invoices,
            'docNo' => $this->docSeq->next('TXI', $this->resolveNodeId($request)),
        ]);
    }

    public function storeTaxInvoice(Request $request)
    {
        $data = $request->validate([
            'issue_date' => 'required|date',
            'invoice_id' => 'nullable|exists:invoices,id',
            'buyer_name' => 'required|max:200',
            'buyer_address' => 'nullable|max:500',
            'buyer_tax_id' => 'nullable|max:20',
            'buyer_branch' => 'nullable|max:100',
            'vat_rate' => 'required|numeric|min:0|max:100',
            'type' => 'required|in:full,simplified,revised',
            'notes' => 'nullable|max:1000',
        ]);

        // ถ้าเชื่อม invoice → ดึงข้อมูลจาก invoice
        if (!empty($data['invoice_id'])) {
            $inv = Invoice::find($data['invoice_id']);
            $taxInv = TaxInvoice::create($data + [
                'tax_invoice_no' => $this->docSeq->next('TXI', $this->resolveNodeId($request), $data['issue_date']),
                'org_node_id' => $this->resolveNodeId($request),
                'subtotal' => $inv->subtotal,
                'vat_amount' => $inv->vat_amount,
                'total' => $inv->total,
                'created_by' => $request->user()->id,
            ]);
        } else {
            $subtotal = $request->input('subtotal', 0);
            $vatAmount = $subtotal * ($data['vat_rate'] / 100);
            $taxInv = TaxInvoice::create($data + [
                'tax_invoice_no' => $this->docSeq->next('TXI', $this->resolveNodeId($request), $data['issue_date']),
                'org_node_id' => $this->resolveNodeId($request),
                'subtotal' => $subtotal,
                'vat_amount' => $vatAmount,
                'total' => $subtotal + $vatAmount,
                'created_by' => $request->user()->id,
            ]);
        }

        return redirect()->route('accounting.tax-invoices.show', $taxInv)
            ->with('status', 'สร้างใบกำกับภาษีสำเร็จ');
    }

    public function showTaxInvoice(TaxInvoice $taxInvoice)
    {
        $taxInvoice->load(['invoice.items', 'node']);
        return view('accounting.tax-invoices.show', compact('taxInvoice'));
    }

    // ════════════════════════════════════
    // 📋 ใบหัก ณ ที่จ่าย (WHT)
    // ════════════════════════════════════
    public function withholdingTaxes(Request $request)
    {
        $q = WithholdingTax::whereIn('org_node_id', $request->user()->visibleNodeIds())
            ->with('payment')
            ->latest('issue_date');

        return view('accounting.wht.index', ['taxes' => $q->paginate(20)]);
    }

    public function showWithholdingTax(WithholdingTax $wht)
    {
        $wht->load(['payment', 'node']);
        return view('accounting.wht.show', compact('wht'));
    }

    // ════════════════════════════════════
    // 📈 รายงานทางบัญชี
    // ════════════════════════════════════
    public function reports(Request $request)
    {
        abort_unless($request->user()->hasAbility('view-financial-statements'), 403, 'ไม่มีสิทธิ์ดูรายงานบัญชี');
        $user = $request->user();
        $nodeIds = $user->visibleNodeIds();
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->endOfMonth()->toDateString());

        // สรุปยอด
        $revenue = Invoice::whereIn('org_node_id', $nodeIds)
            ->whereBetween('invoice_date', [$from, $to])
            ->where('status', '!=', 'void')
            ->sum('subtotal');

        $received = Receipt::whereIn('org_node_id', $nodeIds)
            ->whereBetween('receipt_date', [$from, $to])
            ->sum('amount');

        $paid = Payment::whereIn('org_node_id', $nodeIds)
            ->whereBetween('payment_date', [$from, $to])
            ->sum('amount');

        $vatCollected = TaxInvoice::whereIn('org_node_id', $nodeIds)
            ->whereBetween('issue_date', [$from, $to])
            ->sum('vat_amount');

        $whtPaid = WithholdingTax::whereIn('org_node_id', $nodeIds)
            ->whereBetween('issue_date', [$from, $to])
            ->sum('wht_amount');

        // ลูกหนี้คงค้าง
        $receivables = Invoice::whereIn('org_node_id', $nodeIds)
            ->whereIn('status', ['issued', 'partial', 'overdue'])
            ->where('balance', '>', 0)
            ->select('customer_name', DB::raw('SUM(balance) as total_balance'))
            ->groupBy('customer_name')
            ->orderByDesc('total_balance')
            ->get();

        // สรุปตามเดือน
        $monthly = Invoice::whereIn('org_node_id', $nodeIds)
            ->whereYear('invoice_date', now()->year)
            ->where('status', '!=', 'void')
            ->selectRaw("MONTH(invoice_date) as m, SUM(subtotal) as revenue, SUM(vat_amount) as vat, COUNT(*) as count")
            ->groupBy('m')->orderBy('m')->get();

        return view('accounting.reports', compact(
            'from', 'to', 'revenue', 'received', 'paid',
            'vatCollected', 'whtPaid', 'receivables', 'monthly'
        ));
    }

    // ════════════════════════════════════
    // 📊 ผังบัญชี (Chart of Accounts)
    // ════════════════════════════════════
    public function chartOfAccounts(Request $request)
    {
        abort_unless($request->user()->hasAbility('view-financial-statements'), 403, 'ไม่มีสิทธิ์ดูผังบัญชี');
        $accounts = Account::where(function($q) use ($request) {
            $q->whereNull('org_node_id')
              ->orWhereIn('org_node_id', $request->user()->visibleNodeIds());
        })->orderBy('code')->get();

        return view('accounting.chart', compact('accounts'));
    }

    // ════════════════════════════════════
    // 🚚 ใบส่งของ (Delivery Notes)
    // ════════════════════════════════════
    public function deliveryNotes(Request $request)
    {
        $q = DeliveryNote::where(function($query) use ($request) {
            $query->whereNull('org_node_id')
                  ->orWhereIn('org_node_id', $request->user()->visibleNodeIds());
        })->with('node')->latest()->paginate(20);

        return view('accounting.delivery.index', ['notes' => $q]);
    }

    public function createDeliveryNote(Request $request)
    {
        abort_unless($request->user()->hasAbility('create-delivery'), 403, 'ไม่มีสิทธิ์สร้างใบส่งของ');
        $nodes = $request->user()->visibleNodes();
        $sales = Sale::where(function($q) use ($request) {
            $q->whereNull('org_node_id')
              ->orWhereIn('org_node_id', $request->user()->visibleNodeIds());
        })->latest()->limit(50)->get();

        $products = Product::where('status', 'active')->orderBy('name')->get();

        return view('accounting.delivery.form', compact('nodes', 'sales', 'products'));
    }

    public function storeDeliveryNote(Request $request)
    {
        $data = $request->validate([
            'org_node_id'     => 'required|exists:org_nodes,id',
            'sale_id'         => 'nullable|exists:sales,id',
            'customer_name'   => 'required|string|max:150',
            'delivery_address' => 'nullable|string',
            'recipient_name'  => 'nullable|string|max:100',
            'recipient_phone' => 'nullable|string|max:30',
            'tracking_no'     => 'nullable|string|max:50',
            'carrier'         => 'nullable|string|max:100',
            'note'            => 'nullable|string',
            'items'           => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.lot_id'     => 'nullable|exists:product_lots,id',
            'items.*.qty'        => 'required|integer|min:1',
            'items.*.unit_cost'  => 'required|numeric|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        $docNo = $this->docSeq->next('DLV', $data['org_node_id']);

        $delivery = DB::transaction(function () use ($data, $request, $docNo) {
            $totalQty = 0;
            $totalAmount = 0;
            $items = [];

            foreach ($data['items'] as $item) {
                $lineTotal = bcmul($item['unit_price'] ?? $item['unit_cost'], $item['qty'], 2);
                $totalQty += $item['qty'];
                $totalAmount = bcadd($totalAmount, $lineTotal, 2);
                $items[] = [
                    'product_id' => $item['product_id'],
                    'lot_id'     => $item['lot_id'] ?? null,
                    'qty'        => $item['qty'],
                    'unit_cost'  => $item['unit_cost'],
                    'unit_price' => $item['unit_price'] ?? 0,
                    'line_total' => $lineTotal,
                ];
            }

            $delivery = DeliveryNote::create([
                'org_node_id'      => $data['org_node_id'],
                'doc_no'           => $docNo,
                'sale_id'          => $data['sale_id'] ?? null,
                'customer_name'    => $data['customer_name'],
                'delivery_address' => $data['delivery_address'] ?? null,
                'recipient_name'   => $data['recipient_name'] ?? null,
                'recipient_phone'  => $data['recipient_phone'] ?? null,
                'status'           => 'ready',
                'total_qty'        => $totalQty,
                'total_amount'     => $totalAmount,
                'tracking_no'      => $data['tracking_no'] ?? null,
                'carrier'          => $data['carrier'] ?? null,
                'note'             => $data['note'] ?? null,
                'created_by'       => $request->user()->id,
            ]);

            foreach ($items as $item) {
                $delivery->items()->create($item);
            }

            return $delivery;
        });

        return redirect()->route('accounting.delivery.show', $delivery)
            ->with('flash', '🚚 สร้างใบส่งของสำเร็จ: ' . $docNo);
    }

    public function showDelivery(DeliveryNote $delivery)
    {
        $delivery->load(['items.product', 'items.lot', 'node', 'creator', 'sale', 'creditNotes']);
        return view('accounting.delivery.show', compact('delivery'));
    }

    public function shipDelivery(DeliveryNote $delivery)
    {
        if ($delivery->status !== 'ready') {
            return back()->with('error', '❌ ใบส่งของยังไม่พร้อมส่ง');
        }

        $delivery->update(['status' => 'shipped', 'shipped_at' => now()]);

        // บันทึก Stock Ledger + Journal Entry
        app(\App\Services\StockLedgerService::class)->recordDelivery($delivery);

        return back()->with('flash', '🚚 ส่งของสำเร็จ — ตัดสต๊อก + บันทึกบัญชีอัตโนมัติ');
    }

    public function deliverDelivery(DeliveryNote $delivery)
    {
        if (!in_array($delivery->status, ['shipped', 'ready'])) {
            return back()->with('error', '❌ ยังไม่ได้ส่งของ');
        }

        // ถ้ายังไม่ได้ shipped → ship ก่อน
        if ($delivery->status === 'ready') {
            $delivery->update(['status' => 'shipped', 'shipped_at' => now()]);
            app(\App\Services\StockLedgerService::class)->recordDelivery($delivery);
        }

        $delivery->update(['status' => 'delivered', 'delivered_at' => now()]);
        return back()->with('flash', '✅ ยืนยันการส่งถึงปลายทางสำเร็จ');
    }

    // ════════════════════════════════════
    // ↩️ ใบลดหนี้ / ใบคืนสินค้า (Credit Notes)
    // ════════════════════════════════════
    public function creditNotes(Request $request)
    {
        $q = CreditNote::where(function($query) use ($request) {
            $query->whereNull('org_node_id')
                  ->orWhereIn('org_node_id', $request->user()->visibleNodeIds());
        })->with('node')->latest()->paginate(20);

        return view('accounting.credit.index', ['notes' => $q]);
    }

    public function createCreditNote(Request $request, ?DeliveryNote $deliveryNote = null)
    {
        abort_unless($request->user()->hasAbility('create-credit-note'), 403, 'ไม่มีสิทธิ์สร้างใบลดหนี้');
        $nodes = $request->user()->visibleNodes();
        $deliveries = DeliveryNote::where(function($q) use ($request) {
            $q->whereNull('org_node_id')
              ->orWhereIn('org_node_id', $request->user()->visibleNodeIds());
        })->whereIn('status', ['shipped', 'delivered'])->latest()->limit(50)->get();

        $products = Product::where('status', 'active')->orderBy('name')->get();

        return view('accounting.credit.form', compact('nodes', 'deliveries', 'products', 'deliveryNote'));
    }

    public function storeCreditNote(Request $request)
    {
        $data = $request->validate([
            'org_node_id'      => 'required|exists:org_nodes,id',
            'type'             => 'required|in:return,discount,cancel,adjustment',
            'reason'           => 'required|string|max:255',
            'delivery_note_id' => 'nullable|exists:delivery_notes,id',
            'customer_name'    => 'required|string|max:150',
            'vat_rate'         => 'nullable|numeric|min:0|max:100',
            'note'             => 'nullable|string',
            'items'            => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.lot_id'     => 'nullable|exists:product_lots,id',
            'items.*.qty'        => 'required|integer|min:1',
            'items.*.unit_cost'  => 'required|numeric|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        $docNo = $this->docSeq->next('CN', $data['org_node_id']);
        $vatRate = $data['vat_rate'] ?? 7;

        $credit = DB::transaction(function () use ($data, $request, $docNo, $vatRate) {
            $subtotal = 0;
            $items = [];

            foreach ($data['items'] as $item) {
                $price = $item['unit_price'] ?? $item['unit_cost'];
                $lineTotal = bcmul($price, $item['qty'], 2);
                $subtotal = bcadd($subtotal, $lineTotal, 2);
                $items[] = [
                    'product_id' => $item['product_id'],
                    'lot_id'     => $item['lot_id'] ?? null,
                    'qty'        => $item['qty'],
                    'unit_cost'  => $item['unit_cost'],
                    'unit_price' => $price,
                    'line_total' => $lineTotal,
                ];
            }

            $vat = bcmul($subtotal, bcdiv($vatRate, 100, 4), 2);
            $total = bcadd($subtotal, $vat, 2);

            $credit = CreditNote::create([
                'org_node_id'      => $data['org_node_id'],
                'doc_no'           => $docNo,
                'type'             => $data['type'],
                'reason'           => $data['reason'],
                'delivery_note_id' => $data['delivery_note_id'] ?? null,
                'customer_name'    => $data['customer_name'],
                'subtotal'         => $subtotal,
                'vat_amount'       => $vat,
                'total_amount'     => $total,
                'status'           => 'draft',
                'note'             => $data['note'] ?? null,
                'created_by'       => $request->user()->id,
            ]);

            foreach ($items as $item) {
                $credit->items()->create($item);
            }

            return $credit;
        });

        return redirect()->route('accounting.credit.show', $credit)
            ->with('flash', '↩️ สร้างใบลดหนี้สำเร็จ: ' . $docNo . ' (ร่าง)');
    }

    public function showCreditNote(CreditNote $credit)
    {
        $credit->load(['items.product', 'items.lot', 'node', 'creator', 'deliveryNote', 'invoice']);
        return view('accounting.credit.show', compact('credit'));
    }

    public function confirmCreditNote(CreditNote $credit)
    {
        if ($credit->status !== 'draft') {
            return back()->with('error', '❌ ใบลดหนี้ถูกยืนยันแล้ว');
        }

        $credit->update(['status' => 'confirmed']);

        // บันทึก Stock Ledger + Journal Entry
        app(\App\Services\StockLedgerService::class)->recordCreditNote($credit);

        return back()->with('flash', '✅ ยืนยันใบลดหนี้สำเร็จ — บันทึกบัญชี + คืนสต๊อกอัตโนมัติ');
    }

    // ════════════════════════════════════
    // 📋 Stock Ledger (Audit Trail)
    // ════════════════════════════════════
    public function stockLedger(Request $request)
    {
        abort_unless($request->user()->hasAbility('view-financial-statements'), 403, 'ไม่มีสิทธิ์ดู Stock Ledger');
        $q = StockLedger::query();

        // กรองตาม node
        $q->where(function($query) use ($request) {
            $query->whereNull('org_node_id')
                  ->orWhereIn('org_node_id', $request->user()->visibleNodeIds());
        });

        // filters
        if ($request->filled('product_id')) {
            $q->where('product_id', $request->product_id);
        }
        if ($request->filled('movement_type')) {
            $q->where('movement_type', $request->movement_type);
        }
        if ($request->filled('from')) {
            $q->where('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->where('created_at', '<=', $request->to . ' 23:59:59');
        }

        $ledgers = $q->with(['product', 'node', 'creator', 'lot'])->latest('id')->paginate(30);
        $products = Product::where('status', 'active')->orderBy('name')->get();

        return view('accounting.stock-ledger', compact('ledgers', 'products'));
    }

    // ════════════════════════════════════
    // 🔍 Audit — ตรวจยอดตรง
    // ════════════════════════════════════
    public function audit(Request $request)
    {
        abort_unless($request->user()->hasAbility('view-financial-statements'), 403, 'ไม่มีสิทธิ์ตรวจสอบบัญชี');
        $service = app(\App\Services\StockLedgerService::class);
        $nodeId = $request->input('node_id');

        $stockResult    = $service->verifyBalances($nodeId);
        $journalResult  = $service->verifyJournals($nodeId);

        $nodes = $request->user()->visibleNodes();

        return view('accounting.audit', compact('stockResult', 'journalResult', 'nodes', 'nodeId'));
    }

    // ════════════════════════════════════
    // 📋 ใบเสนอราคา (Quotations)
    // ════════════════════════════════════
    public function quotations(Request $request)
    {
        $q = Quotation::whereIn('org_node_id', $request->user()->visibleNodeIds())
            ->latest('issue_date')->paginate(20);
        return view('accounting.quotations.index', ['quotations' => $q]);
    }

    public function createQuotation(Request $request)
    {
        abort_unless($request->user()->hasAbility('create-quotation'), 403, 'ไม่มีสิทธิ์สร้างใบเสนอราคา');
        $nodes = $request->user()->visibleNodes();
        return view('accounting.quotations.form', [
            'quotation' => null,
            'nodes' => $nodes,
            'docNo' => $this->docSeq->next('QT', $this->resolveNodeId($request)),
        ]);
    }

    public function storeQuotation(Request $request)
    {
        $data = $request->validate([
            'org_node_id'       => 'required|exists:org_nodes,id',
            'customer_name'     => 'required|max:200',
            'customer_address'  => 'nullable|max:500',
            'customer_tax_id'   => 'nullable|max:20',
            'customer_contact'  => 'nullable|max:100',
            'issue_date'        => 'required|date',
            'valid_until'       => 'required|date|after_or_equal:issue_date',
            'vat_rate'          => 'required|numeric|min:0|max:100',
            'notes'             => 'nullable|max:1000',
            'terms'             => 'nullable|max:2000',
            'items'             => 'required|array|min:1',
            'items.*.description' => 'required|max:500',
            'items.*.qty'         => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric|min:0',
        ]);

        $docNo = $this->docSeq->next('QT', $data['org_node_id']);
        $vatRate = $data['vat_rate'];

        $quotation = DB::transaction(function () use ($data, $request, $docNo, $vatRate) {
            $subtotal = 0;
            $items = [];
            foreach ($data['items'] as $item) {
                $lineTotal = bcmul($item['qty'], $item['unit_price'], 2);
                $subtotal = bcadd($subtotal, $lineTotal, 2);
                $items[] = [
                    'description' => $item['description'],
                    'qty'         => $item['qty'],
                    'unit_price'  => $item['unit_price'],
                    'line_total'  => $lineTotal,
                ];
            }

            $vatAmount = bcmul($subtotal, bcdiv($vatRate, 100, 4), 2);
            $total = bcadd($subtotal, $vatAmount, 2);

            $q = Quotation::create([
                'org_node_id'     => $data['org_node_id'],
                'doc_no'          => $docNo,
                'customer_name'   => $data['customer_name'],
                'customer_address' => $data['customer_address'] ?? null,
                'customer_tax_id' => $data['customer_tax_id'] ?? null,
                'customer_contact' => $data['customer_contact'] ?? null,
                'issue_date'      => $data['issue_date'],
                'valid_until'     => $data['valid_until'],
                'subtotal'        => $subtotal,
                'vat_rate'        => $vatRate,
                'vat_amount'      => $vatAmount,
                'total'           => $total,
                'status'          => 'draft',
                'notes'           => $data['notes'] ?? null,
                'terms'           => $data['terms'] ?? null,
                'created_by'      => $request->user()->id,
            ]);

            foreach ($items as $item) {
                $q->items()->create($item);
            }

            return $q;
        });

        return redirect()->route('accounting.quotations.show', $quotation)
            ->with('flash', '📋 สร้างใบเสนอราคาสำเร็จ: ' . $docNo);
    }

    public function showQuotation(Quotation $quotation)
    {
        $quotation->load(['items', 'node', 'creator', 'convertedInvoice']);
        return view('accounting.quotations.show', compact('quotation'));
    }

    public function sendQuotation(Quotation $quotation)
    {
        if ($quotation->status !== 'draft') return back()->with('error', '❌ ส่งแล้ว');
        $quotation->update(['status' => 'sent']);
        return back()->with('flash', '📤 ส่งใบเสนอราคาแล้ว');
    }

    public function acceptQuotation(Quotation $quotation)
    {
        if (!in_array($quotation->status, ['sent', 'draft'])) return back()->with('error', '❌ สถานะไม่ถูกต้อง');
        $quotation->update(['status' => 'accepted']);
        return back()->with('flash', '✅ ลูกค้าตกลง — สร้างบิลเรียกเก็บจากใบเสนอราคานี้');
    }

    public function convertQuotation(Quotation $quotation)
    {
        if (!in_array($quotation->status, ['accepted', 'sent'])) {
            return back()->with('error', '❌ ต้องได้รับการตกลงก่อนแปลงเป็นบิล');
        }

        $invoice = DB::transaction(function () use ($quotation) {
            $invNo = $this->docSeq->next('INV', $quotation->org_node_id);
            $invoice = Invoice::create([
                'org_node_id'      => $quotation->org_node_id,
                'invoice_no'       => $invNo,
                'customer_name'    => $quotation->customer_name,
                'customer_address' => $quotation->customer_address,
                'customer_tax_id'  => $quotation->customer_tax_id,
                'invoice_date'     => now(),
                'due_date'         => now()->addDays(30),
                'subtotal'         => $quotation->subtotal,
                'vat_rate'         => $quotation->vat_rate,
                'vat_amount'       => $quotation->vat_amount,
                'total'            => $quotation->total,
                'balance'          => $quotation->total,
                'status'           => 'issued',
                'notes'            => 'แปลงจากใบเสนอราคา: ' . $quotation->doc_no,
            ]);

            foreach ($quotation->items as $item) {
                $invoice->items()->create([
                    'description' => $item->description,
                    'qty'         => $item->qty,
                    'unit_price'  => $item->unit_price,
                    'amount'      => $item->line_total,
                    'sort_order'  => $item->id,
                ]);
            }

            $quotation->update(['status' => 'converted', 'converted_invoice_id' => $invoice->id]);

            return $invoice;
        });

        return redirect()->route('accounting.invoices.show', $invoice)
            ->with('flash', '🔄 แปลงเป็นบิลเรียกเก็บสำเร็จ: ' . $invoice->invoice_no);
    }

    // ════════════════════════════════════
    // 🛒 ใบสั่งซื้อ (Purchase Orders)
    // ════════════════════════════════════
    public function purchaseOrders(Request $request)
    {
        $q = PurchaseOrder::whereIn('org_node_id', $request->user()->visibleNodeIds())
            ->latest('order_date')->paginate(20);
        return view('accounting.po.index', ['pos' => $q]);
    }

    public function createPurchaseOrder(Request $request)
    {
        abort_unless($request->user()->hasAbility('create-purchase-order'), 403, 'ไม่มีสิทธิ์สร้างใบสั่งซื้อ');
        $nodes = $request->user()->visibleNodes();
        $products = Product::where('status', 'active')->orderBy('name')->get();
        return view('accounting.po.form', [
            'po' => null, 'nodes' => $nodes, 'products' => $products,
            'docNo' => $this->docSeq->next('PO', $this->resolveNodeId($request)),
        ]);
    }

    public function storePurchaseOrder(Request $request)
    {
        $data = $request->validate([
            'org_node_id'     => 'required|exists:org_nodes,id',
            'vendor_name'     => 'required|max:200',
            'vendor_address'  => 'nullable|max:500',
            'vendor_tax_id'   => 'nullable|max:20',
            'vendor_contact'  => 'nullable|max:100',
            'order_date'      => 'required|date',
            'expected_date'   => 'nullable|date|after_or_equal:order_date',
            'vat_rate'        => 'required|numeric|min:0|max:100',
            'wht_rate'        => 'nullable|numeric|min:0|max:100',
            'notes'           => 'nullable|max:1000',
            'items'           => 'required|array|min:1',
            'items.*.product_id'   => 'nullable|exists:products,id',
            'items.*.description'  => 'required|max:500',
            'items.*.qty'          => 'required|numeric|min:0.01',
            'items.*.unit_price'   => 'required|numeric|min:0',
        ]);

        $docNo = $this->docSeq->next('PO', $data['org_node_id']);
        $vatRate = $data['vat_rate'];
        $whtRate = $data['wht_rate'] ?? 0;

        $po = DB::transaction(function () use ($data, $request, $docNo, $vatRate, $whtRate) {
            $subtotal = 0;
            $items = [];
            foreach ($data['items'] as $item) {
                $lineTotal = bcmul($item['qty'], $item['unit_price'], 2);
                $subtotal = bcadd($subtotal, $lineTotal, 2);
                $items[] = [
                    'product_id'  => $item['product_id'] ?? null,
                    'description' => $item['description'],
                    'qty'         => $item['qty'],
                    'unit_price'  => $item['unit_price'],
                    'line_total'  => $lineTotal,
                ];
            }

            $vatAmount = bcmul($subtotal, bcdiv($vatRate, 100, 4), 2);
            $whtAmount = bcmul($subtotal, bcdiv($whtRate, 100, 4), 2);
            $total = bcadd($subtotal, $vatAmount, 2);
            $netTotal = bcsub($total, $whtAmount, 2);

            $po = PurchaseOrder::create([
                'org_node_id'    => $data['org_node_id'],
                'po_no'          => $docNo,
                'vendor_name'    => $data['vendor_name'],
                'vendor_address' => $data['vendor_address'] ?? null,
                'vendor_tax_id'  => $data['vendor_tax_id'] ?? null,
                'vendor_contact' => $data['vendor_contact'] ?? null,
                'order_date'     => $data['order_date'],
                'expected_date'  => $data['expected_date'] ?? null,
                'subtotal'       => $subtotal,
                'vat_rate'       => $vatRate,
                'vat_amount'     => $vatAmount,
                'wht_rate'       => $whtRate,
                'wht_amount'     => $whtAmount,
                'total'          => $total,
                'net_total'      => $netTotal,
                'status'         => 'draft',
                'notes'          => $data['notes'] ?? null,
                'created_by'     => $request->user()->id,
            ]);

            foreach ($items as $item) {
                $po->items()->create($item);
            }

            return $po;
        });

        return redirect()->route('accounting.po.show', $po)
            ->with('flash', '🛒 สร้างใบสั่งซื้อสำเร็จ: ' . $docNo);
    }

    public function showPurchaseOrder(PurchaseOrder $po)
    {
        $po->load(['items.product', 'node', 'creator', 'approver']);
        return view('accounting.po.show', compact('po'));
    }

    public function approvePurchaseOrder(PurchaseOrder $po)
    {
        if ($po->status !== 'draft') return back()->with('error', '❌ อนุมัติไม่ได้');
        $po->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        return back()->with('flash', '✅ อนุมัติใบสั่งซื้อแล้ว');
    }

    // ════════════════════════════════════
    // 📒 ลงบัญชีแยก (Manual Journals)
    // ════════════════════════════════════
    public function manualJournals(Request $request)
    {
        abort_unless($request->user()->hasAbility('manage-journals'), 403, 'ไม่มีสิทธิ์ดูบัญชีแยก');
        $q = ManualJournal::whereIn('org_node_id', $request->user()->visibleNodeIds())
            ->with('lines.account')
            ->latest('entry_date')->paginate(20);
        return view('accounting.journals.index', ['journals' => $q]);
    }

    public function createManualJournal(Request $request)
    {
        abort_unless($request->user()->hasAbility('manage-journals'), 403, 'ไม่มีสิทธิ์ลงบัญชีแยก');
        $nodes = $request->user()->visibleNodes();
        $accounts = Account::where(function($q) use ($request) {
            $q->whereNull('org_node_id')
              ->orWhereIn('org_node_id', $request->user()->visibleNodeIds());
        })->where('is_active', true)->orderBy('code')->get();

        return view('accounting.journals.form', [
            'journal'  => null,
            'nodes'    => $nodes,
            'accounts' => $accounts,
            'docNo'    => $this->docSeq->next('JV', $this->resolveNodeId($request)),
        ]);
    }

    public function storeManualJournal(Request $request)
    {
        $data = $request->validate([
            'org_node_id'  => 'required|exists:org_nodes,id',
            'entry_date'   => 'required|date',
            'description'  => 'required|max:500',
            'notes'        => 'nullable|max:1000',
            'lines'        => 'required|array|min:2',
            'lines.*.account_id'  => 'required|exists:accounts,id',
            'lines.*.debit'       => 'required|numeric|min:0',
            'lines.*.credit'      => 'required|numeric|min:0',
            'lines.*.description' => 'nullable|max:200',
        ]);

        // Check balance
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($data['lines'] as $line) {
            $totalDebit = bcadd($totalDebit, $line['debit'], 2);
            $totalCredit = bcadd($totalCredit, $line['credit'], 2);
        }

        if (bccomp($totalDebit, $totalCredit, 2) !== 0) {
            return back()->withInput()->with('error', '❌ Debit (' . number_format($totalDebit, 2) . ') ≠ Credit (' . number_format($totalCredit, 2) . ') — ต้องเท่ากัน');
        }

        $docNo = $this->docSeq->next('JV', $data['org_node_id']);

        $journal = DB::transaction(function () use ($data, $request, $docNo) {
            $j = ManualJournal::create([
                'org_node_id' => $data['org_node_id'],
                'doc_no'      => $docNo,
                'entry_date'  => $data['entry_date'],
                'description' => $data['description'],
                'status'      => 'draft',
                'notes'       => $data['notes'] ?? null,
                'created_by'  => $request->user()->id,
            ]);

            foreach ($data['lines'] as $line) {
                $j->lines()->create($line);
            }

            return $j;
        });

        return redirect()->route('accounting.journals.show', $journal)
            ->with('flash', '📒 สร้างรายการบัญชีสำเร็จ: ' . $docNo);
    }

    public function showManualJournal(ManualJournal $journal)
    {
        $journal->load(['lines.account', 'node', 'creator']);
        return view('accounting.journals.show', compact('journal'));
    }

    public function postManualJournal(ManualJournal $journal)
    {
        if ($journal->status !== 'draft') return back()->with('error', '❌ ต้องเป็นร่างเท่านั้น');
        if (!$journal->isBalanced()) return back()->with('error', '❌ Debit ≠ Credit');

        DB::transaction(function () use ($journal) {
            $entry = JournalEntry::create([
                'org_node_id' => $journal->org_node_id,
                'entry_date'  => $journal->entry_date,
                'doc_no'      => $journal->doc_no,
                'description' => $journal->description,
                'status'      => 'posted',
                'created_by'  => auth()->id(),
            ]);

            foreach ($journal->lines as $line) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $line->account_id,
                    'debit'            => $line->debit,
                    'credit'           => $line->credit,
                    'description'      => $line->description,
                ]);
            }

            $journal->update(['status' => 'posted']);
        });

        return back()->with('flash', '✅ ผ่านรายการบัญชีแล้ว');
    }

    public function reverseManualJournal(ManualJournal $journal)
    {
        if ($journal->status !== 'posted') return back()->with('error', '❌ ต้องเป็น posted เท่านั้น');

        $reversed = DB::transaction(function () use ($journal) {
            $docNo = $this->docSeq->next('JV', $journal->org_node_id);
            $rev = ManualJournal::create([
                'org_node_id' => $journal->org_node_id,
                'doc_no'      => $docNo,
                'entry_date'  => now(),
                'description' => 'กลับรายการ: ' . $journal->doc_no . ' — ' . $journal->description,
                'status'      => 'draft',
                'notes'       => 'Reversal of ' . $journal->doc_no,
                'created_by'  => auth()->id(),
            ]);

            foreach ($journal->lines as $line) {
                $rev->lines()->create([
                    'account_id'  => $line->account_id,
                    'debit'       => $line->credit,
                    'credit'      => $line->debit,
                    'description' => 'กลับรายการ: ' . $line->description,
                ]);
            }

            $journal->update(['status' => 'reversed', 'reversed_by_id' => $rev->id]);
            return $rev;
        });

        return redirect()->route('accounting.journals.show', $reversed)
            ->with('flash', '🔄 สร้างรายการกลับทางสำเร็จ — ต้องโพสต์ต่อ');
    }

    // ════════════════════════════════════
    // 📊 General Ledger + Financial Statements
    // ════════════════════════════════════
    public function generalLedger(Request $request)
    {
        abort_unless($request->user()->hasAbility('view-financial-statements'), 403, 'ไม่มีสิทธิ์ดูงบการเงิน');
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $accountId = $request->input('account_id');

        $accounts = Account::where(function($q) use ($request) {
            $q->whereNull('org_node_id')
              ->orWhereIn('org_node_id', $request->user()->visibleNodeIds());
        })->orderBy('code')->get();

        $entries = [];
        if ($accountId) {
            $lines = JournalLine::where('account_id', $accountId)
                ->whereHas('entry', fn($q) => $q->where('status', 'posted')
                    ->whereBetween('entry_date', [$from, $to]))
                ->with('entry')
                ->orderBy('id')
                ->get();

            $running = 0;
            $account = Account::find($accountId);
            foreach ($lines as $line) {
                // Normal balance logic
                $isNormalDebit = in_array($account->category, ['asset', 'expense']);
                $change = $isNormalDebit
                    ? bcsub($line->debit, $line->credit, 2)
                    : bcsub($line->credit, $line->debit, 2);
                $running = bcadd($running, $change, 2);

                $entries[] = [
                    'date'        => $line->entry->entry_date,
                    'reference'   => $line->entry->doc_no,
                    'description' => $line->description,
                    'debit'       => $line->debit,
                    'credit'      => $line->credit,
                    'balance'     => $running,
                ];
            }
        }

        return view('accounting.general-ledger', compact('accounts', 'entries', 'from', 'to', 'accountId'));
    }

    public function trialBalance(Request $request)
    {
        $asOf = $request->input('as_of', now()->toDateString());
        abort_unless($request->user()->hasAbility('view-financial-statements'), 403, 'ไม่มีสิทธิ์ดูงบการเงิน');
        $nodeIds = $request->user()->visibleNodeIds();

        $accounts = Account::where(function($q) use ($nodeIds) {
            $q->whereNull('org_node_id')
              ->orWhereIn('org_node_id', $nodeIds);
        })->orderBy('code')->get();

        $results = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($accounts as $account) {
            $lines = JournalLine::where('account_id', $account->id)
                ->whereHas('entry', fn($q) => $q->where('status', 'posted')
                    ->where('entry_date', '<=', $asOf))
                ->get();

            $debit  = $lines->sum('debit');
            $credit = $lines->sum('credit');
            $isNormalDebit = in_array($account->category, ['asset', 'expense']);

            $balance = $isNormalDebit
                ? bcsub($debit, $credit, 2)
                : bcsub($credit, $debit, 2);

            if (bccomp($balance, 0, 2) === 0 && $lines->isEmpty()) continue;

            $results[] = [
                'code'     => $account->code,
                'name'     => $account->name,
                'category' => $account->category,
                'debit'    => $isNormalDebit && $balance > 0 ? $balance : ($balance < 0 ? abs($balance) : 0),
                'credit'   => !$isNormalDebit && $balance > 0 ? $balance : ($balance < 0 ? abs($balance) : 0),
            ];

            if ($isNormalDebit && $balance > 0) $totalDebit = bcadd($totalDebit, $balance, 2);
            elseif (!$isNormalDebit && $balance > 0) $totalCredit = bcadd($totalCredit, $balance, 2);
            elseif ($balance < 0) {
                if ($isNormalDebit) $totalCredit = bcadd($totalCredit, abs($balance), 2);
                else $totalDebit = bcadd($totalDebit, abs($balance), 2);
            }
        }

        return view('accounting.trial-balance', compact('results', 'totalDebit', 'totalCredit', 'asOf'));
    }

    public function profitLoss(Request $request)
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        abort_unless($request->user()->hasAbility('view-financial-statements'), 403, 'ไม่มีสิทธิ์ดูงบการเงิน');
        $to = $request->input('to', now()->toDateString());
        $nodeIds = $request->user()->visibleNodeIds();

        // Revenue accounts
        $revenueAccounts = Account::where('category', 'revenue')->get();
        $expenseAccounts = Account::where('category', 'expense')->get();

        $revenues = [];
        $totalRevenue = 0;
        foreach ($revenueAccounts as $account) {
            $amount = JournalLine::where('account_id', $account->id)
                ->whereHas('entry', fn($q) => $q->where('status', 'posted')
                    ->whereBetween('entry_date', [$from, $to]))
                ->selectRaw('SUM(credit) - SUM(debit) as net')
                ->value('net') ?? 0;
            if ($amount != 0) {
                $revenues[] = ['name' => $account->name, 'amount' => $amount];
                $totalRevenue = bcadd($totalRevenue, $amount, 2);
            }
        }

        $expenses = [];
        $totalExpense = 0;
        foreach ($expenseAccounts as $account) {
            $amount = JournalLine::where('account_id', $account->id)
                ->whereHas('entry', fn($q) => $q->where('status', 'posted')
                    ->whereBetween('entry_date', [$from, $to]))
                ->selectRaw('SUM(debit) - SUM(credit) as net')
                ->value('net') ?? 0;
            if ($amount != 0) {
                $expenses[] = ['name' => $account->name, 'amount' => $amount];
                $totalExpense = bcadd($totalExpense, $amount, 2);
            }
        }

        $netProfit = bcsub($totalRevenue, $totalExpense, 2);

        return view('accounting.profit-loss', compact(
            'from', 'to', 'revenues', 'expenses',
            'totalRevenue', 'totalExpense', 'netProfit'
        ));
    }

    public function balanceSheet(Request $request)
    {
        $asOf = $request->input('as_of', now()->toDateString());
        abort_unless($request->user()->hasAbility('view-financial-statements'), 403, 'ไม่มีสิทธิ์ดูงบการเงิน');

        $categories = ['asset', 'liability', 'equity'];
        $sections = [];

        foreach ($categories as $cat) {
            $accounts = Account::where('category', $cat)->get();
            $items = [];
            $total = 0;

            foreach ($accounts as $account) {
                $lines = JournalLine::where('account_id', $account->id)
                    ->whereHas('entry', fn($q) => $q->where('status', 'posted')
                        ->where('entry_date', '<=', $asOf))
                    ->get();

                $debit  = $lines->sum('debit');
                $credit = $lines->sum('credit');
                $isNormalDebit = ($cat === 'asset');

                $balance = $isNormalDebit
                    ? bcsub($debit, $credit, 2)
                    : bcsub($credit, $debit, 2);

                if (bccomp($balance, 0, 2) === 0 && $lines->isEmpty()) continue;

                $items[] = ['name' => $account->name, 'amount' => $balance];
                $total = bcadd($total, $balance, 2);
            }

            $sections[$cat] = ['items' => $items, 'total' => $total];
        }

        // Retained earnings = Revenue - Expense (cumulative)
        $retainedEarnings = 0;
        $revenueAccounts = Account::where('category', 'revenue')->get();
        $expenseAccounts = Account::where('category', 'expense')->get();

        foreach ($revenueAccounts as $acc) {
            $retainedEarnings = bcadd($retainedEarnings,
                JournalLine::where('account_id', $acc->id)
                    ->whereHas('entry', fn($q) => $q->where('status', 'posted')
                        ->where('entry_date', '<=', $asOf))
                    ->selectRaw('SUM(credit) - SUM(debit) as net')->value('net') ?? 0, 2);
        }
        foreach ($expenseAccounts as $acc) {
            $retainedEarnings = bcsub($retainedEarnings,
                JournalLine::where('account_id', $acc->id)
                    ->whereHas('entry', fn($q) => $q->where('status', 'posted')
                        ->where('entry_date', '<=', $asOf))
                    ->selectRaw('SUM(debit) - SUM(credit) as net')->value('net') ?? 0, 2);
        }

        $totalAssets = $sections['asset']['total'];
        $totalLiabilitiesAndEquity = bcadd(
            $sections['liability']['total'],
            bcadd($sections['equity']['total'], $retainedEarnings, 2),
            2
        );

        return view('accounting.balance-sheet', compact(
            'asOf', 'sections', 'retainedEarnings',
            'totalAssets', 'totalLiabilitiesAndEquity'
        ));
    }

    // ════════════════════════════════════
    // 📋 Aging Reports (AR + AP)
    // ════════════════════════════════════
    public function agingReport(Request $request)
    {
        $nodeIds = $request->user()->visibleNodeIds();
        abort_unless($request->user()->hasAbility('view-financial-statements'), 403, 'ไม่มีสิทธิ์ดูงบการเงิน');

        // AR — ลูกหนี้ค้างรับ (จาก Invoices)
        $receivables = Invoice::whereIn('org_node_id', $nodeIds)
            ->whereIn('status', ['issued', 'partial', 'overdue'])
            ->where('balance', '>', 0)
            ->selectRaw("
                customer_name,
                SUM(balance) as total_balance,
                DATEDIFF(CURDATE(), due_date) as days_overdue
            ")
            ->groupBy('customer_name')
            ->orderByDesc('days_overdue')
            ->get();

        // AP — เจ้าหนี้ค้างจ่าย (จาก Purchase Orders)
        $payables = PurchaseOrder::whereIn('org_node_id', $nodeIds)
            ->whereIn('status', ['approved', 'ordered', 'partial_received'])
            ->where('net_total', '>', 0)
            ->select('vendor_name', 'po_no', 'net_total', 'expected_date', 'order_date')
            ->orderBy('order_date')
            ->get();

        return view('accounting.aging-report', compact('receivables', 'payables'));
    }
}
