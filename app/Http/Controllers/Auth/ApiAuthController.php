<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Kyc;
use App\Models\Address;
use App\Models\Referral;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ApiAuthController extends Controller
{
    public function register (Request $request) {
        
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'phone' => 'required|numeric',
        ]);
        if ($validator->fails())
        {
            return response(['errors'=>$validator->errors()->all()], 422);
        }

        $request['password'] = Hash::make($request['password']);
        $request['remember_token'] = Str::random(10);
        $user = User::create($request->toArray());

        // generate unique id for search
        $user->uid = $user->first_name . sprintf('%05d', $user->id);
        $user->save();

        // handel referral
        if(isset($request['ref'])) {
            $userRef = User::where('affiliate_id', $request['ref'])->first();
            if ($userRef) {
                Referral::where('user_id', $userRef->id)
                    ->where('sent_to', $user->email)
                    ->update(['referred_by' => $user->id]);
            }
        }

        try {
           $result = Mail::send('emails/login', [
               'user' => $user,
           ],
               function ($message) use ($user) {
                   $message->from('hello@afroshipper.com');
                   $message->to($user->email, $user->first_name)
                       ->subject('Please login to Afroshipper.');
               });

            $user->sendEmailVerificationNotification();
        } catch (\Exception $e) {}

        // assign user role
        $user->assignRole('User');

        Kyc::create(['user_id' => $user->id]);

        $permissions = $user->getPermissionsViaRoles()->toArray();
        $permissions = array_map(function($permission) {
            return $permission['name'];
        }, $permissions);

        $token = $user->createToken('Laravel Password Grant Client')->accessToken;
        $response = [
            'success' => true,
            'token' => $token,
            'user' => $user,
            'roles' => $user->getRoleNames(),
            'permissions' => $permissions,
            'has_address' => Address::where('user_id', $user->id)->count() > 0,
            'us_address' => Setting::where('key', 'us_address')->first()
        ];
        return response($response, 200);
    }

    public function login (Request $request) {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails())
        {
            return response(['errors'=>$validator->errors()->all()], 422);
        }
        $user = User::where('email', $request->email)->first();
        if ($user) {
            if (Hash::check($request->password, $user->password) && $user->hasVerifiedEmail()) {
                $token = $user->createToken('Laravel Password Grant Client')->accessToken;

                $permissions = $user->getPermissionsViaRoles()->toArray();
                $permissions = array_map(function($permission) {
                    return $permission['name'];
                }, $permissions);

                $primaryAddress = Address::where('user_id', $user->id)
                    ->where('is_primary', true)->first();
                $user->address = $primaryAddress;
                $response = [
                    'success' => true,
                    'token' => $token,
                    'user' => $user,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $permissions,
                    'referral' => $user->getReferralLink(),
                    'has_address' => $user->can('invoice_create_shipping') || $user->can('invoice_create_dispatch') ||
                        Address::where('user_id', $user->id)->count() > 0,
                    'us_address' => Setting::where('key', 'us_address')->first()
                ];

                return response($response, 200);
            } else if (!$user->hasVerifiedEmail()) {
                $response = [
                    "message" => "Email is not verified.",
                    "success" => false
                ];
                return response($response, 422);
            } else {
                $response = [
                    "message" => "Password mismatch",
                    "success" => false
                ];
                return response($response, 422);
            }
        } else {
            $response = [
                "message" =>'User does not exist',
                "success" => false
            ];
            return response($response, 422);
        }
    }

    public function logout (Request $request) {
        $token = $request->user()->token();
        $token->revoke();
        $response = [
            'message' => 'You have been successfully logged out!',
            'success' => true,
        ];
        return response($response, 200);
    }

    public function updateProfile(Request $request) {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'unique:users,email,' . $user->id,
            ],
            'phone' => 'required|numeric',
        ]);
        if ($validator->fails())
        {
            return response(['errors'=>$validator->errors()->all()], 422);
        }
        $user->update($request->all());

        return response()->json([
            'success' => true,
            'user' => $user
        ]);
    }

    public function changePwd(Request $request) {
        $validator = Validator::make($request->all(), [
            'new_pwd' => 'required|string|min:6',
        ]);
        if ($validator->fails())
        {
            return response(['errors'=>$validator->errors()->all()], 422);
        }
        $user = Auth::user();
        $user->password = Hash::make($request['new_pwd']);
        $user->save();

        return response()->json([
            'success' => true,
            'user' => $user
        ]);
    }
}
