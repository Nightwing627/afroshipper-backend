<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Referral;
use App\Http\Resources\ReferralResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class ApiReferralController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $referrals = Referral::where('user_id', Auth::id())->get();

        return response()->json([
            'success' => true,
            'referrals' => new ReferralResource($referrals)
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $referral = Referral::where('user_id', Auth::id())
            ->where('sent_to', $request->sent_to)->first();
        if (!$referral) {
            $referral = new Referral;
            $referral->user_id = Auth::id();
            $referral->sent_to = $request->sent_to;
            $referral->save();
        }

        try {
            $result = Mail::send('emails/referral', [
                'name' => "Afroshipper",
                'email' => $request->sent_to,
                'affiliate_id' => Auth::user()->affiliate_id
            ],
                function ($message) use ($request) {
                    $message->from('hello@afroshipper.com');
                    $message->to($request->sent_to, "")
                        ->subject('Please register to afroshipper.');
                });
        } catch (\Exception $e) {}

        return response()->json([
            'success' => true,
            'result' => $result
        ]);
    }

    public function resend(Referral $referral) {
        $user = User::find($referral->user_id);
        try {
            $result = Mail::send('emails/referral', [
                'name' => "Afroshipper",
                'email' => $referral->sent_to,
                'affiliate_id' => $user->affiliate_id
            ],
                function ($message) use ($referral) {
                    $message->from('hello@afroshipper.com');
                    $message->to($referral->sent_to, "")
                        ->subject('Please register to afroshipper.');
                });
        } catch (\Exception $e) {}

        return response()->json([
            'succes' => true,
            'referral' => new ReferralResource($referral)
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Referral $referral)
    {
        $referral->delete();

        return response()->json([
            'success' => true
        ]);
    }
}
