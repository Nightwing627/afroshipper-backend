<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApiDashboardController extends Controller
{
    private function isUser() {
        $user = Auth::user();
        return !$user->can('invoice_create_shipping') && !$user->can('invoice_create_dispatch');
    }

    public function generalReport() {
        $is_user = $this->isUser();
        $user_id = Auth::id();
        $n_shippings = Invoice::where('type', 'shipping')
            ->where(function($query) use ($is_user, $user_id) {
                if($is_user) $query->where('user_id', $user_id);
            })
            ->where('paid', false)->count();
        $n_purchases = Invoice::where('type', 'purchase')
            ->where(function($query) use ($is_user, $user_id) {
                if($is_user) $query->where('user_id', $user_id);
            })
            ->where('paid', false)->count();
        $n_dispatches = Invoice::where('type', 'dispatch')
            ->where(function($query) use ($is_user, $user_id) {
                if($is_user) $query->where('user_id', $user_id);
            })
            ->where('paid', false)->count();
        $n_users = User::count();
        $month_ago = (new \Datetime())->sub(new \DateInterval('P1M'));
        $n_invoices_30days = Invoice::where('created_at', '>', $month_ago->format('y-m-d'))
            ->where(function($query) use ($is_user, $user_id) {
                if($is_user) $query->where('user_id', $user_id);
            })->count();

        return response()->json([
            'success' => true,
            'n_shippings' => $n_shippings,
            'n_purchases' => $n_purchases,
            'n_dispatches' => $n_dispatches,
            'n_invoices_30days' => $n_invoices_30days,
            'n_users' => $n_users,
        ]);
    }

    public function salesReport(Request $request) {
        $is_user = $this->isUser();
        $user_id = Auth::id();

        $start = $request->start;
        $end = (new \DateTime($request->end))->add(new \DateInterval('P1D'));

        $sales = Invoice::select([DB::raw('SUM(cost) AS cost'), DB::raw('SUM(insurance) AS insurance'), DB::raw('SUM(service_fee) AS service_fee'), 'paid_at'])
            ->where(function($query) use ($is_user, $user_id) {
                if($is_user) $query->where('user_id', $user_id);
            })
            ->where('paid_at', '<=', $end)
            ->where('paid_at', '>=', $start)
            ->where('paid', true)
            ->groupBy('paid_at')
            ->orderBy('paid_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sales
        ]);
    }

    public function statReport() {
        $is_user = $this->isUser();
        $user_id = Auth::id();

        $n_shippings = Invoice::where('type', 'shipping')
            ->where(function($query) use ($is_user, $user_id) {
                if($is_user) $query->where('user_id', $user_id);
            })->count();
        $n_purchases = Invoice::where('type', 'purchase')
            ->where(function($query) use ($is_user, $user_id) {
                if($is_user) $query->where('user_id', $user_id);
            })->count();
        $n_dispatches = Invoice::where('type', 'dispatch')
            ->where(function($query) use ($is_user, $user_id) {
                if($is_user) $query->where('user_id', $user_id);
            })->count();

        return response()->json([
            'success' => true,
            'data' => [
                'shipping' => $n_shippings,
                'dispatch' => $n_dispatches,
                'purchase' => $n_purchases
            ]
        ]);
    }

    public function invoicesPending() {
        $is_user = $this->isUser();
        $user_id = Auth::id();

        $invoices = Invoice::with('user')
            ->where(function($query) use ($is_user, $user_id) {
                if($is_user) $query->where('user_id', $user_id);
            })
            ->where('paid', false)
            ->get();

        return response()->json([
            'success' => true,
            'invoices' => $invoices
        ]);
    }
}
