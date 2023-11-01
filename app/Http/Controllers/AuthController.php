<?php

namespace App\Http\Controllers;

use App\Http\Resources\AdminResource;
use App\Http\Resources\UserResource;
use App\Models\ResetCodePassword;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function admin_login(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $input = $request->only(['email', 'password']);

        $admin = User::query()->where('email', $request->email)->first();

        if ($admin && !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'code' => 401,
                'message' => 'Incorrect Password!',
            ], 401);
        }

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'code' => 401,
                'message' => "Email doesn't exist",
            ], 401);
        }

        // authentication attempt
        if (auth()->attempt($input, $request->get('remember'))) {
            if ($admin->status == '0' || $admin->access == '0') {

                Auth::logout();

                return response()->json([
                    'code' => 401,
                    'message' => "Account disactivated, please contact administrator.",
                ], 401);
            }

            if ($admin->account_type == 'Administrator') {

                // Get the old token
                $oldToken = $admin->tokens->where('name', 'API TOKEN')->first();

                if($oldToken)
                {
                    // Revoke the old token
                    $oldToken->delete();
                }

                $token = $admin->createToken("API TOKEN")->plainTextToken;

                return response()->json([
                    'code' => 200,
                    'message' => 'Admin logged in succesfully.',
                    'token' => $token,
                    'data' => new AdminResource($admin)
                ], 200);
            }
            return response()->json([
                'code' => 401,
                'message' => "You are not an Administrator!"
            ], 401);
        } else {
            return response()->json([
                'code' => 401,
                'message' => "Admin authentication failed."
            ], 401);
        }
    }

    public function register(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'account_type' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc,strict', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone_number' => ['required', 'string', 'max:255', 'regex:/^([0-9\s\-\+\(\)]*)$/', 'min:10'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'passport' => 'nullable|mimes:jpeg,png,jpg|max:16384',
            'certificates' => 'nullable|mimes:jpeg,png,jpg|max:16384',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $latestId = User::where('account_type', '<>', 'Administrator')->max('id') + 1;
        $customId = str_pad($latestId, 3, '0', STR_PAD_LEFT);

        if (request()->hasFile('passport')) {
            $file = str_replace(' ', '', uniqid(5).'-'.$request->passport->getClientOriginalName());
            $filename = pathinfo($file, PATHINFO_FILENAME);

            $passport = cloudinary()->uploadFile($request->passport->getRealPath(),
            [
                'folder' => config('app.name').'/api',
                "public_id" => $filename,
                "use_filename" => TRUE
            ])->getSecurePath();
        }

        if (request()->hasFile('certificates')) {
            $file = str_replace(' ', '', uniqid(5).'-'.$request->certificates->getClientOriginalName());
            $filename = pathinfo($file, PATHINFO_FILENAME);

            $certificates = cloudinary()->uploadFile($request->certificates->getRealPath(),
            [
                'folder' => config('app.name').'/api',
                "public_id" => $filename,
                "use_filename" => TRUE
            ])->getSecurePath();
        }

        $user = User::create([
            'membership_id' => config('app.name').$customId,
            'account_type' => $request->account_type,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'username' => $request->username,
            'email' => $request->email,
            'email_verified_at' => now(),
            'password' => Hash::make($request->password),
            'current_password' => $request->password,
            'phone_number' => $request->phone_number,
            'gender' => $request->gender,
            'marital_status' => $request->marital_status,
            'state' => $request->state,
            'address' =>  $request->address,
            'passport' => $passport ?? null,
            'certificates' => $certificates ?? null,
            'place_business_employment' => $request->place_business_employment,
            'nature_business_employment' => $request->nature_business_employment,
            'membership_professional_bodies' => $request->membership_professional_bodies,
            'previous_insolvency_work_experience' => $request->previous_insolvency_work_experience,
            'referee_email_address' => $request->referee_email_address,
            'status' => 'Pending'
        ]);  
        
        /** Store information to include in mail in $data as an array */
        $data = array(
            'name' => $user->first_name.' '.$user->last_name,
            'email' => $user->email,
            'username' => $user->username,
            'membership_id' => $user->membership_id,
            'password' => $request->password
        );

        /** Send message to the user */
        Mail::send('emails.notifyMember', $data, function ($m) use ($data) {
            $m->to($data['email'])->subject(config('app.name'));
        });

        return response()->json([
            'code' => 200,
            'message' => $user->first_name.' '.$user->last_name.' account created successfully!',
        ], 200);
    }

    public function login(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'login_details' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $input = $request->only(['login_details', 'password']);

        $user = User::query()->Where('username', $request->login_details)
                            ->orWhere('email', $request->login_details)->first();

        if ($user && !Hash::check($request->password, $user->password)) {
            return response()->json([
                'code' => 401,
                'message' => 'Incorrect Password!',
            ], 401);
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'code' => 401,
                'message' => "Email or Username doesn't exist",
            ], 401);
        }

         // Determine if the login details are an email or a username
        $fieldType = filter_var($request->login_details, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $credentials = [
            $fieldType => $request->login_details,
            'password' => $request->password,
        ];

        if (auth()->attempt($credentials)) {
            $user = auth()->user();

            if ($user->status == 'Inactive') {
                auth()->logout();

                return response()->json([
                    'code' => 401,
                    'message' => "Account deactivated, please contact the administrator.",
                ], 401);
            }

            if ($user->status == 'Pending') {
                auth()->logout();

                return response()->json([
                    'code' => 401,
                    'message' => "Please check back, while the adminstrator process your informations.",
                ], 401);
            }

            if ($user->account_type <> 'Administrator') {
                // Get the old token
                $oldToken = $user->tokens->where('name', 'API TOKEN')->first();

                if($oldToken)
                {
                    // Revoke the old token
                    $oldToken->delete();
                }

                $token = $user->createToken("API TOKEN")->plainTextToken;

                return response()->json([
                    'code' => 200,
                    'message' => "Login Successfully.",
                    'token' => $token,
                    'data' => new UserResource($user)
                ], 200);
            }

            auth()->logout();

            return response()->json([
                'code' => 401,
                'message' => 'You are not a member.',
            ], 401);
        } else {
            return response()->json([
                'code' => 401,
                'message' => 'User authentication failed.',
            ], 401);
        }
    }

    public function forget_password(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'email' => 'required|email|exists:users',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        // Delete all old code that user send before.
        ResetCodePassword::where('email', $request->email)->delete();

        // Generate random code
        $code = mt_rand(100000, 999999);

        // Create a new code
        $codeData = ResetCodePassword::create([
            'email' => $request->email,
            'code' => $code
        ]);

         /** Store information to include in mail in $data as an array */
         $data = array(
            'name' => $user->name,
            'email' => $user->email,
            'code' => $codeData->code
        );

        /** Send message to the user */
        Mail::send('emails.resetPassword', $data, function ($m) use ($data) {
            $m->to($data['email'])->subject(config('app.name'));
        });

        return response()->json([
            'code' => 200,
            'message' => "We have emailed your password reset code.",
        ], 200);
    }

    public function reset_password(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'code' => 'required|string|exists:reset_code_passwords',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        if (ResetCodePassword::where('code', '=', $request->code)->exists()) {
            // find the code
            $passwordReset = ResetCodePassword::firstWhere('code', $request->code);

            // check if it does not expired: the time is one hour
            if ($passwordReset->created_at > now()->addHour()) {
                $passwordReset->delete();

                return response()->json([
                    'code' => 401,
                    'message' => 'Password reset code expired'
                ], 401);
            }

            // find user's email
            $user = User::where('email', $passwordReset->email)->first();

            // update user password
            $user->update([
                'password' => Hash::make($request->password),
                'current_password' => $request->password
            ]);

            // delete current code
            $passwordReset->delete();

            return response()->json([
                'code' => 200,
                'message' => 'Password has been successfully reset, Please login',
            ], 200);
        } else {
            return response()->json([
                'code' => 401,
                'message' => "Code doesn't exist in our database."
            ], 401);
        }
    }
}
