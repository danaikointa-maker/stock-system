<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\{Invoice, InvoiceItem, Receipt, Payment, TaxInvoice, WithholdingTax, JournalEntry, JournalLine, Account, Product};
use App\Services\DocSequenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AccountingController extends Controller
{
    public function __construct(private DocSequenceService $docSeq) {}

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
        $products = Product::whereIn('id', function($q) use ($request) {
            $q->select('product_id')->from('product_lots')
              ->whereIn('org_node_id', $request->user()->visibleNodeIds());
        })->get();

        return view('accounting.invoices.form', [
            'invoice' => null,
            'products' => $products,
            'docNo' => $this->docSeq->next('INV', $request->user()->node_id),
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
                'invoice_no' => $this->docSeq->next('INV', $request->user()->node_id, $data['invoice_date']),
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'],
                'org_node_id' => $request->user()->node_id,
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
        Gate::authorize('view-invoice', $invoice);
        $invoice->load(['items', 'receipts', 'taxInvoice', 'customerNode']);
        return view('accounting.invoices.show', compact('invoice'));
    }

    public void voidInvoice(Request $request, Invoice $invoice)
    {
        Gate::authorize('void-invoice', $invoice);
        $invoice->update(['status' => 'void', 'balance' => 0]);
        return redirect()->back()->with('status', 'ยกเลิกบิลแล้ว');
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
        $invoices = Invoice::whereIn('org_node_id', $request->user()->visibleNodeIds())
            ->whereIn('status', ['issued', 'partial', 'overdue'])
            ->where('balance', '>', 0)
            ->get();

        return view('accounting.receipts.form', [
            'receipt' => null,
            'invoice' => $invoice,
            'invoices' => $invoices,
            'docNo' => $this->docSeq->next('RCP', $request->user()->node_id),
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
                'receipt_no' => $this->docSeq->next('RCP', $request->user()->node_id, $data['receipt_date']),
                'org_node_id' => $request->user()->node_id,
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
        return view('accounting.payments.form', [
            'payment' => null,
            'docNo' => $this->docSeq->next('PAY', $request->user()->node_id),
            'whtRates' => WithholdingTax::commonRates(),
        ]);
    }

    public function storePayment(Request $request)
    {
        $data = $request->validate([
            'payment_date' => 'required|date',
            'payee_name' => 'required|max:200',
            'payee_tax_id' => 'nullable|max:20',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,bank_transfer,promptpay,cheque',
            'bank_ref' => 'nullable|max:100',
            'description' => 'nullable|max:1000',
            'wht_rate' => 'nullable|numeric|min:0',
            'income_type' => 'nullable|max:200',
        ]);

        $payment = DB::transaction(function () use ($data, $request) {
            $pay = Payment::create($data + [
                'payment_no' => $this->docSeq->next('PAY', $request->user()->node_id, $data['payment_date']),
                'org_node_id' => $request->user()->node_id,
                'created_by' => $request->user()->id,
            ]);

            // สร้างใบหัก ณ ที่จ่าย (ถ้ามี)
            if (!empty($data['wht_rate']) && $data['wht_rate'] > 0) {
                $whtAmount = $data['amount'] * ($data['wht_rate'] / 100);
                WithholdingTax::create([
                    'wht_no' => $this->docSeq->next('WHT', $request->user()->node_id, $data['payment_date']),
                    'issue_date' => $data['payment_date'],
                    'org_node_id' => $request->user()->node_id,
                    'payee_name' => $data['payee_name'],
                    'payee_tax_id' => $data['payee_tax_id'] ?? null,
                    'income_amount' => $data['amount'],
                    'wht_rate' => $data['wht_rate'],
                    'wht_amount' => $whtAmount,
                    'net_amount' => $data['amount'] - $whtAmount,
                    'income_type' => $data['income_type'] ?? null,
                    'payment_id' => $pay->id,
                    'created_by' => $request->user()->id,
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
        $invoices = Invoice::whereIn('org_node_id', $request->user()->visibleNodeIds())
            ->whereDoesntHave('taxInvoice')
            ->whereIn('status', ['issued', 'partial', 'paid'])
            ->get();

        return view('accounting.tax-invoices.form', [
            'taxInvoice' => null,
            'invoice' => $invoice,
            'invoices' => $invoices,
            'docNo' => $this->docSeq->next('TXI', $request->user()->node_id),
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
                'tax_invoice_no' => $this->docSeq->next('TXI', $request->user()->node_id, $data['issue_date']),
                'org_node_id' => $request->user()->node_id,
                'subtotal' => $inv->subtotal,
                'vat_amount' => $inv->vat_amount,
                'total' => $inv->total,
                'created_by' => $request->user()->id,
            ]);
        } else {
            $subtotal = $request->input('subtotal', 0);
            $vatAmount = $subtotal * ($data['vat_rate'] / 100);
            $taxInv = TaxInvoice::create($data + [
                'tax_invoice_no' => $this->docSeq->next('TXI', $request->user()->node_id, $data['issue_date']),
                'org_node_id' => $request->user()->node_id,
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
        $accounts = Account::where(function($q) use ($request) {
            $q->whereNull('org_node_id')
              ->orWhereIn('org_node_id', $request->user()->visibleNodeIds());
        })->orderBy('code')->get();

        return view('accounting.chart', compact('accounts'));
    }
}
