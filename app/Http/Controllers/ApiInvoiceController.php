<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceStoreRequest;
use App\Http\Requests\InvoiceUpdateRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Resources\InvoiceResource;
use App\Models\User;
use App\Models\Invoice;
use App\Models\Address;

class ApiInvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        abort_if(Gate::denies('invoice_list'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $limit = $request->limit ? $request->limit : 8;
        $user = Auth::user();
        
        $invoices = Invoice::with('user');
        if (!$user->can('invoice_create_shipping') && !$user->can('invoice_create_dispatch')) {
            $invoices = $invoices->where('user_id', $user->id);
        }
        else if(isset($request->user)) {
            $invoices = $invoices->where('user_id', $request->user);
        }
        if (isset($request->status) && $request->status != 'all') {
            $invoices = $invoices->where('status', $request->status);
        }
        if (isset($request->type) && $request->type != 'all') {
            $invoices = $invoices->where('type', $request->type);
        }
        if (isset($request->paid) && $request->paid != 'all') {
            $invoices = $invoices->where('paid', $request->paid == 'paid' ? true : false);
        }
        if (isset($request->start) && isset($request->end)) {
            $end = new \DateTime($request->end);
            $end->add(new \DateInterval('P1D'));
            $end = $end->format('y-m-d');
            $invoices = $invoices->where('created_at', '>=', $request->start)
                ->where('created_at', '<=', $end);
        }
        $invoices = $invoices->orderByRaw('paid, created_at DESC')->paginate($limit);

        $result = [];
        
        foreach ($invoices as &$invoice) {
            if ($invoice->user) {
                $invoice->address = Address::where('user_id', $invoice->user->id)
                    ->where('is_primary', true)->first();
            }
        }

        return response()->json([
            'success' => true,
            'invoices' => new InvoiceResource($invoices)
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(InvoiceStoreRequest $request)
    {
        if ($request->type == 'shipping' && $this->isManager()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 401);
        }
        $data = $request->all();
        $data['created_by'] = Auth::id();
        $data['created_at'] = Carbon::now()->format('Y-m-d H:i:s');
        $invoice = Invoice::create($data);

        $user = User::find($invoice->user_id);
        $this->sendMail($user, $invoice);

        return response()->json([
            'success' => true,
            'invoice' => new InvoiceResource($invoice)
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Invoice $invoice)
    {
        abort_if(Gate::denies('invoice_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return response()->json([
            'success' => true,
            'invoice' => new InvoiceResource($invoice)
        ]);
    }

    private function isManager(){
        $user = Auth::user();
        return !$user->can('invoice_create_shipping') && $user->can('invoice_create_dispatch');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(InvoiceUpdateRequest $request, Invoice $invoice)
    {
        $data = [];
        if ($this->isManager()) {
            $data = [
                'status' => $request->status,
                $request->status.'_at' => Carbon::now()->format('Y-m-d H:i:s')
            ];
        }
        else {
            $data = $request->all();
            $data[$request->status.'_at'] = Carbon::now()->format('Y-m-d H:i:s');
        }
        $invoice->update($data);
        $user = User::find($invoice->user_id);
        $this->sendMail($user, $invoice);
        return response()->json([
            'success' => true,
            'invoice' => new InvoiceResource($invoice)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Invoice $invoice)
    {
        abort_if(Gate::denies('invoice_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $user = Auth::user();
        if ($this->isManager() && $user->id != $invoice->created_by) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 401);
        }
        $invoice->delete();

        return response()->json([
            'success' => true
        ]);
    }

    public function pay(Request $request, Invoice $invoice) {
        if ($request->status == 'success') {
            $invoice->paid = true;
            $invoice->paid_at = Carbon::now()->format('Y-m-d H:i:s');
            $invoice->ref_paystack = $request->reference;
            $invoice->save();
            return response()->json(["success" => true]);
        }
        return response()->json(["success" => false], 401);
    }

    private function sendMail(User $user, Invoice $invoice) {
        // 'created','shipped','received','dispatched','collected'
        try {
            switch ($invoice->status) {
                case 'created':
                    $viewT = 'emails/invoices/created';
                    $subject = "SHIPPING INVOICE CREATED";
                    break;
                case 'shipped':
                    $viewT = 'emails/invoices/shipped';
                    $subject = "ITEM SHIPPED";
                    break;
                case 'received':
                    $viewT = 'emails/invoices/received';
                    $subject = "ITEM READY FOR PICKUP";
                    break;
                case 'dispatched':
                    $viewT = 'emails/invoices/dispatched';
                    $subject = "ITEM IS OUT FOR DELIVERY";
                    break;
                case 'collected':
                    $viewT = 'emails/invoices/collected';
                    $subject = "ITEM DELIVERED";
                    break;
                default:
                    $viewT = 'emails/invoices/created';
                    $subject = "SHIPPING INVOICE CREATED";
                    break;
            }

            $result = Mail::send($viewT, [
                "user" => $user,
                "invoice" => $invoice
            ],
                function ($message) use ($user, $subject) {
                    $message->from('hello@afroshipper.com');
                    $message->to($user->email, $user->first_name)
                        ->subject($subject);
                });
        } catch (\Exception $e) {}
    }
}
