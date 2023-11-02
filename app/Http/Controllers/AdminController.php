<?php

namespace App\Http\Controllers;

use App\Http\Resources\AdminResource;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\UserResource;
use App\Models\Announcement;
use App\Models\Bank;
use App\Models\Category;
use App\Models\Due;
use App\Models\Event;
use App\Models\Notification;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    public function profile()
    {
        return response()->json([
            'code' => 200,
            'message' => "Profile retrieved successfully.",
            'data' => new AdminResource(Auth::user())
        ], 200);
    }

    public function verify_member(Request $request)
    {
        $user = User::find($request->user_id);

        if(!$user)
        {
            return response()->json([
                'code' => 401,
                'message' => 'No user with the ID - '.$request->user_id.' in our database.',
            ], 401);
        }

        $user->update([
            'isSubscribed' => true
        ]);

        /** Store information to include in mail in $data as an array */
        $data = array(
            'name' => $user->first_name.' '.$user->last_name,
            'email' => $user->email,
            'username' => $user->username,
            'membership_id' => $user->membership_id,
            'password' => $user->current_password
        );

        /** Send message to the user */
        Mail::send('emails.verifiedMember', $data, function ($m) use ($data) {
            $m->to($data['email'])->subject('Your '.config('app.name').' Account Has Been Successfully Activated');
        });

        return response()->json([
            'code' => 200,
            'message' => $user->first_name.' '.$user->last_name. ' account has been successfully activated.'
        ], 200); 
    }

    public function update_profile(Request $request)
    {
        $admin = User::find(Auth::user()->id);

        if ($admin->email == $request->email) {
            $admin->update([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
            ]);
        } else {
            //Validate Request
            $validator = Validator::make(request()->all(), [
                'email' => ['string', 'email', 'max:255', 'unique:users'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'message' => 'Please see errors parameter for all errors.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $admin->update([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
            ]);
        }

        return response()->json([
            'code' => 200,
            'message' => 'Profile updated successfully.',
            'data' => new AdminResource($admin)
        ], 200);
    }

    public function update_password(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'old_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $admin = User::find(Auth::user()->id);

        if($admin->current_password !== $request->old_password)
        {
            return response()->json([
                'code' => 401,
                'message' => "Old password doesn't match, try again.",
            ], 401);
        }

        $admin->password = Hash::make($request->new_password);
        $admin->current_password = $request->new_password;
        $admin->save();

        Notification::create([
            'user_id' => $admin->id,
            'title' => config('app.name'),
            'body' => 'Your '.config('app.name').' password changed successfully.',
            'image' => config('app.url').'/favicon.png',
            'type' => 'Password Changed'
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'Password Updated Successfully',
            'data' => new AdminResource($admin)
        ], 200);
    }

    public function upload_profile_picture(Request $request)
    {
        $input = $request->only(['avatar']);

        $validate_data = [
            'avatar' => 'required|mimes:jpeg,png,jpg',
        ];

        $validator = Validator::make($input, $validate_data);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        //User
        $admin = User::find(Auth::user()->id);

        $token = explode('/', $admin->avatar);
        $token2 = explode('.', $token[sizeof($token)-1]);

        if($admin->avatar)
        {
            cloudinary()->destroy(config('app.name').'/api/'.$token2[0]);
        }

        $file = str_replace(' ', '', uniqid(5).'-'.$request->avatar->getClientOriginalName());
        $filename = pathinfo($file, PATHINFO_FILENAME);

        $response = cloudinary()->uploadFile($request->avatar->getRealPath(),
        [
            'folder' => config('app.name').'/api',
            "public_id" => $filename,
            "use_filename" => TRUE
        ])->getSecurePath();

        $admin->avatar = $response;
        $admin->save();

        return response()->json([
            'code' => 200,
            'message' => 'Profile Picture Uploaded Successfully!',
            'data' => new AdminResource($admin)
        ], 200);
    }

    public function get_all_notifications()
    {
        $notifications = Notification::latest()->with(['from_who', 'user'])->get();

        return response()->json([
            'code' => 200,
            'message' => 'All notifications retrieved.',
            'data' => NotificationResource::collection($notifications)
        ], 200);
    }

    public function get_all_unread_notifications()
    {
        $userUnreadNotifications = Notification::latest()->where('user_id', Auth::user()->id)->where('status', 'Unread')->with(['from_who'])->get();

        return response()->json([
            'code' => 200,
            'message' => 'All unread notifications retrieved.',
            'data' => NotificationResource::collection($userUnreadNotifications)
        ], 200);
    }

    public function count_unread_notifications()
    {
        $userCountUnreadNotifications = Notification::latest()->where('user_id', Auth::user()->id)->where('status', 'Unread')->count();

        return response()->json([
            'code' => 200,
            'message' => 'Count all unread notifications.',
            'data' => $userCountUnreadNotifications
        ], 200);
    }

    public function read_notification(Request $request)
    {
        $notification = Notification::find($request->notification_id);

        if(!$notification)
        {
            return response()->json([
                'code' => 401,
                'message' => 'Not found in our database.'
            ], 401);
        }

        if($notification->user_id !== Auth::user()->id)
        {
            return response()->json([
                'code' => 401,
                'message' => "Notification doesn't belong to you."
            ], 401);
        }
        
        $notification->update([
            'status' => 'Read'
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'Notification read successfully.'
        ], 200);
    }

    public function delete_notification(Request $request)
    {
        $notification = Notification::find($request->notification_id);

        if(!$notification)
        {
            return response()->json([
                'code' => 401,
                'message' => 'Not found in our database.'
            ], 401);
        }

        $notification->delete();

        return response()->json([
            'code' => 200,
            'message' => 'Notification deleted successfully.'
        ], 200);
    }

    public function get_all_member(Request $request)
    {
        $users = UserResource::collection($this->getUserBySearch($request, 1000))
                ->response()
                ->getData(true);

        return response()->json([
            'code' => 200,
            'message' => 'All members retrieved successfully.',
            'data' => $users
        ], 200);
    }

    /**
     * query users by search
     *
     * @param  Request $request
     * @param  int $number
     * @return LengthAwarePaginator
     */
    public function getUserBySearch(Request $request, int $number): LengthAwarePaginator
    {
        $users = User::where('account_type', '<>', 'Member')->where('account_type', 'LIKE', "%{$request->keyword}%")
            ->latest()
            ->paginate($number)
            ->appends($request->query());

        if (isset($request->keyword)) {
            if (count($users) > 0) return $users;

            throw new ModelNotFoundException("Not found in our database.");
            
        };

        return $users;
    }

    public function member_add(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'account_type' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc,strict', 'max:255', 'unique:users,email'],
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

        $existing = User::where('email', $request->email)->first();
            
        if($existing)
        {
            return response()->json([
                'code' => 401,
                'message' => 'Email already exists.'
            ], 401);
        }

        $latestId = User::where('account_type', '<>', 'Administrator')->max('id') + 1;
        $customId = str_pad($latestId, 3, '0', STR_PAD_LEFT);
        $password = Str::random(8);

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
            'username' => $request->username ?? config('app.name').strtoupper(substr($request->first_name, 0, 2)).$customId,
            'email' => $request->email,
            'email_verified_at' => now(),
            'password' => Hash::make($password),
            'current_password' => $password,
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
            'status' => 'Unsubscribe'
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

    public function member_activate(Request $request)
    {
        $user = User::find($request->user_id);

        if(!$user)
        {
            return response()->json([
                'code' => 401,
                'message' => 'No user with the ID - '.$request->user_id.' in our database.',
            ], 401);
        }

        $user->update([
            'status' => 'Active'
        ]);

        if($user->isSubscribed == false)
        {
            /** Store information to include in mail in $data as an array */
            $data = array(
                'name' => $user->first_name.' '.$user->last_name,
                'email' => $user->email,
                'username' => $user->username,
                'membership_id' => $user->membership_id,
                'password' => $user->current_password
            );

            /** Send message to the user */
            Mail::send('emails.verifiedMember', $data, function ($m) use ($data) {
                $m->to($data['email'])->subject('Your '.config('app.name').' Account Has Been Successfully Activated');
            });
        }

        return response()->json([
            'code' => 200,
            'message' => $user->first_name.' '.$user->last_name. ' account activated successfully!'
        ], 200);
    }

    public function member_deactivate(Request $request)
    {
        $user = User::find($request->user_id);

        if(!$user)
        {
            return response()->json([
                'code' => 401,
                'message' => 'No user with the ID - '.$request->user_id.' in our database.',
            ], 401);
        }

        $user->update([
            'status' => 'Inactive'
        ]);

        return response()->json([
            'code' => 200,
            'message' => $user->first_name.' '.$user->last_name. ' account deactivated successfully!'
        ], 200);
    }

    // public function member_delete($id)
    // {
    //     $finder = Crypt::decrypt($id);

    //     $user = User::find($finder);

    //     $notifications = Notification::where('to', $user->id)->get();
    //     if($notifications->count() > 0)
    //     {
    //         foreach($notifications as $notification)
    //         {
    //             $notification->delete();
    //         }
    //     }

    //     if($user->avatar) {
    //         Storage::delete(str_replace("storage", "public", $user->avatar));
    //     }

    //     $user->delete();

    //     return back()->with([
    //         'alertType' => 'success',
    //         'message' => 'Branch deleted successfully!'
    //     ]);
    // }

    public function member_update_profile(Request $request)
    {
        $user = User::find($request->user_id);

        if(!$user)
        {
            return response()->json([
                'code' => 401,
                'message' => 'No user with the ID - '.$request->user_id.' in our database.',
            ], 401);
        }

        $validator = Validator::make(request()->all(), [
            'account_type' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:255', 'regex:/^([0-9\s\-\+\(\)]*)$/', 'min:10'],
            // 'username' => ['required', 'string', 'max:255', 'unique:users,username'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

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

        if($user->email == $request->email)
        {
            $user->update([
                'account_type' => $request->account_type,
                // 'username' => $request->username,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone_number' => $request->phone_number,
                'gender' => $request->gender,
                'marital_status' => $request->marital_status,
                'state' => $request->state,
                'address' =>  $request->address,
                'passport' => $passport ?? $user->passport,
                'certificates' => $certificates ?? $user->certificates,
                'place_business_employment' => $request->place_business_employment,
                'nature_business_employment' => $request->nature_business_employment,
                'membership_professional_bodies' => $request->membership_professional_bodies,
                'previous_insolvency_work_experience' => $request->previous_insolvency_work_experience,
                'referee_email_address' => $request->referee_email_address,
            ]);  
        } else {
            //Validate Request
            $validator = Validator::make(request()->all(), [
                'email' => ['required', 'string', 'email:rfc,strict', 'max:255', 'unique:users,email'],
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'message' => 'Please see errors parameter for all errors.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user->update([
                'account_type' => $request->account_type,
                // 'username' => $request->username,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'gender' => $request->gender,
                'marital_status' => $request->marital_status,
                'state' => $request->state,
                'address' =>  $request->address,
                'passport' => $passport ?? $user->passport,
                'certificates' => $certificates ?? $user->certificates,
                'place_business_employment' => $request->place_business_employment,
                'nature_business_employment' => $request->nature_business_employment,
                'membership_professional_bodies' => $request->membership_professional_bodies,
                'previous_insolvency_work_experience' => $request->previous_insolvency_work_experience,
                'referee_email_address' => $request->referee_email_address,
            ]);  
        }

        return response()->json([
            'code' => 200,
            'message' => $user->first_name.' '.$user->last_name. ' profile updated successfully!'
        ], 200);
    }

    public function member_update_password(Request $request)
    {
        $user = User::find($request->user_id);

        if(!$user)
        {
            return response()->json([
                'code' => 401,
                'message' => 'No user with the ID - '.$request->user_id.' in our database.',
            ], 401);
        }

        $validator = Validator::make(request()->all(), [
            'new_password' => ['required', 'string', 'min:8', 'confirmed']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->current_password = $request->new_password;
        $user->save();

        /** Store information to include in mail in $data as an array */
        $data = array(
            'name' => $user->first_name.' '.$user->last_name,
            'email' => $user->email,
            'password' => $request->new_password
        );

        /** Send message to the user */
        Mail::send('emails.changePassword', $data, function ($m) use ($data) {
            $m->to($data['email'])->subject(config('app.name'));
        });

        return response()->json([
            'code' => 200,
            'message' => $user->first_name.' '.$user->last_name. ' password updated successfully.'
        ], 200);
    }

    public function member_update_profile_picture(Request $request)
    {
        $user = User::find($request->user_id);

        if(!$user)
        {
            return response()->json([
                'code' => 401,
                'message' => 'No user with the ID - '.$request->user_id.' in our database.',
            ], 401);
        }

        $validator = Validator::make(request()->all(), [
            'avatar' => 'required|mimes:jpeg,png,jpg',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

       $token = explode('/', $user->avatar);
       $token2 = explode('.', $token[sizeof($token)-1]);

       if($user->avatar)
       {
           cloudinary()->destroy(config('app.name').'/api/'.$token2[0]);
       }

       $file = str_replace(' ', '', uniqid(5).'-'.$request->avatar->getClientOriginalName());
       $filename = pathinfo($file, PATHINFO_FILENAME);

       $response = cloudinary()->uploadFile($request->avatar->getRealPath(),
       [
           'folder' => config('app.name').'/api',
           "public_id" => $filename,
           "use_filename" => TRUE
       ])->getSecurePath();

       $user->avatar = $response;
       $user->save();

        return response()->json([
            'code' => 200,
            'message' => $user->first_name.' '.$user->last_name. ' profile picture uploaded successfully.',
            'avatar' => $user->avatar
        ], 200);
    }

    public function member_resend_login_details(Request $request)
    {
        $user = User::find($request->user_id);

        if(!$user)
        {
            return response()->json([
                'code' => 401,
                'message' => 'No user with the ID - '.$request->user_id.' in our database.',
            ], 401);
        }

        /** Store information to include in mail in $data as an array */
        $data = array(
            'name' => $user->first_name.' '.$user->last_name,
            'email' => $user->email,
            'username' => $user->username,
            'membership_id' => $user->membership_id,
            'password' => $user->current_password
        );

        /** Send message to the user */
        Mail::send('emails.notifyMember', $data, function ($m) use ($data) {
            $m->to($data['email'])->subject(config('app.name'));
        });

        return response()->json([
            'code' => 200,
            'message' => $user->first_name.' '.$user->last_name. ' login details resend successfully.'
        ], 200); 
    }

    public function member_view(Request $request)
    {
        $user = User::find($request->user_id);

        if(!$user)
        {
            return response()->json([
                'code' => 401,
                'message' => 'No user with the ID - '.$request->user_id.' in our database.',
            ], 401);
        }

        return response()->json([
            'code' => 200,
            'message' => $user->first_name.' '.$user->last_name. ' account retrieved successfully!',
            'data' => new UserResource($user)
        ], 200);
    }

    public function member_view_payments(Request $request)
    {
        $user = User::find($request->user_id);

        if(!$user)
        {
            return response()->json([
                'code' => 401,
                'message' => 'No user with the ID - '.$request->user_id.' in our database.',
            ], 401);
        }

        $payments = Transaction::latest()->where('user_id', $user->id)->with(['due', 'subscription'])->get();

        return response()->json([
            'code' => 200,
            'message' => $user->first_name.' '.$user->last_name. ' account retrieved successfully!',
            'data' => $payments
        ], 200);
    }

    public function banks()
    {
        $banks = Bank::latest()->where('status', 'Active')->get();

        return response()->json([
            'code' => 200,
            'message' => 'All Bank Information Retrieved Successfully.',
            'data' => $banks
        ], 200);
    }

    public function admin_bank_post(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'numeric'],
            'bank_name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        Bank::create([
            'account_name' => $request->account_name,
            'account_number' => $request->account_number,
            'bank_name' => $request->bank_name,
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'Added successfully!',
        ], 200);
    }

    public function admin_bank_update(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'bank_id' => ['required', 'numeric'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'numeric'],
            'bank_name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $bank = Bank::find($request->bank_id);

        if(!$bank)
        {
            return response()->json([
                'code' => 401,
                'message' => 'Not found in our database.',
            ], 401);
        }

        $bank->update([
            'account_name' => $request->account_name,
            'account_number' => $request->account_number,
            'bank_name' => $request->bank_name,
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'Updated successfully!',
        ], 200);
    }

    public function admin_bank_delete(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'bank_id' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $bank = Bank::find($request->bank_id);

        if(!$bank)
        {
            return response()->json([
                'code' => 401,
                'message' => 'Not found in our database.',
            ], 401);
        }

        $bank->update([
            'status' => 'Inactive'
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'Deleted Successfully!',
        ], 200);
    }

    // Category
    public function admin_category()
    {
        $categories = Category::latest()->where('status', 'Active')->with('bank')->get();

        return response()->json([
            'code' => 200,
            'message' => 'All Payment Category Retrieved Successfully.',
            'data' => $categories
        ], 200);
    }

    public function admin_category_post(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'name' => ['required', 'string', 'max:255'],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        Category::create([
            'name' => $request->name,
            'bank_id' => $request->bank_id
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'Created successfully!',
        ], 200);
    }

    public function admin_category_update(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'category_id' => ['required', 'numeric'],
            'name' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:banks,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $category = Category::find($request->category_id);

        $category->update([
            'name' => $request->name,
            'bank_id' => $request->bank_id
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'Updated successfully!',
        ], 200);
    }

    public function admin_category_delete(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'category_id' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $category = Category::find($request->category_id);

        if(!$category)
        {
            return response()->json([
                'code' => 401,
                'message' => 'Not found in our database.',
            ], 401);
        }

        $category->update([
            'status' => 'Inactive'
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'Deleted Successfully!',
        ], 200);
    }

    public function admin_dues()
    {
        $dues = Due::latest()->where('status', 'Active')->with('category')->get();

        return response()->json([
            'code' => 200,
            'message' => 'All Payment Dues Retrieved Successfully.',
            'data' => $dues
        ], 200);
    }

    public function admin_dues_post(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'payment_category_id' => ['required', 'numeric', 'exists:categories,id'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric'],
            'start_date' => ['required', 'date_format:d/m/Y'],
            'end_date' => ['required', 'date_format:d/m/Y'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        Due::create([
            'payment_category_id' => $request->payment_category_id,
            'description' => $request->description,
            'amount' => $request->amount,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'Created successfully!',
        ], 200);
    }

    public function admin_dues_view_payments(Request $request)
    {
        $due = Due::find($request->due_id);

        if(!$due)
        {
            return response()->json([
                'code' => 401,
                'message' => 'No due with the ID - '.$request->due_id.' in our database.',
            ], 401);
        }

        $payments = $due->load(['category', 'transactions']);

        return response()->json([
            'code' => 200,
            'message' => 'Payments retrieved successfully!',
            'data' => $payments
        ], 200);
    }

    public function admin_dues_all_payments()
    {
        $payments = Transaction::latest()
                ->where(function ($query) {
                    $query->whereNull('subscription_id')
                        ->orWhere('subscription_id', '');
                })->with(['user', 'due'])->get();

        return response()->json([
            'code' => 200,
            'message' => 'All Dues payments retrieved successfully!',
            'data' => $payments
        ], 200);
    }

    public function admin_dues_transaction_update(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'status' => ['required', 'string', 'max:255'],
            'transaction_id' => ['required', 'numeric', 'exists:transactions,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $transaction = Transaction::find($request->transaction_id);

        $transaction->update([
            'status' => $request->status
        ]);

        Notification::create([
            'user_id' => Auth::user()->id,
            'title' => 'Due Payment',
            'body' => 'You payment has been reviewed.',
            'image' => config('app.url').'/favicon.png',
            'type' => 'Due Payment'
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'Updated successfully!',
        ], 200);

    }

    public function admin_dues_update(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'due_id' => ['required', 'numeric', 'exists:dues,id'],
            'payment_category_id' => ['required', 'numeric', 'exists:categories,id'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric'],
            'start_date' => ['required', 'date_format:d/m/Y'],
            'end_date' => ['required', 'date_format:d/m/Y'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $due = Due::find($request->due_id);

        $due->update([
            'payment_category_id' => $request->payment_category_id,
            'description' => $request->description,
            'amount' => $request->amount,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'Updated successfully!',
        ], 200);
    }

    public function admin_dues_delete(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'due_id' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $due = Due::find($request->due_id);

        if(!$due)
        {
            return response()->json([
                'code' => 401,
                'message' => 'Not found in our database.',
            ], 401);
        }

        $due->update([
            'status' => 'Inactive'
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'Deleted Successfully!',
        ], 200);
    }

    public function admin_payments_approved()
    {
        $approvedPayments = Transaction::latest()->where('status', 'success')->get();

        return view('admin.payment.approved', [
            'approvedPayments' => $approvedPayments
        ]);
    }

    public function admin_payments_pending()
    {
        $pendingPayments = Transaction::latest()->where('status', 'pending')->get();

        return view('admin.payment.pending', [
            'pendingPayments' => $pendingPayments
        ]);
    }

    // Event
    public function admin_events()
    {
        $events = Event::latest()->where('status', 'Active')->get();

        return response()->json([
            'code' => 200,
            'message' => 'All Events Retrieved Successfully.',
            'data' => $events
        ], 200);
    }

    public function admin_event_post(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'image' => 'nullable|mimes:jpeg,png,jpg,gif|max:2048', // Define image validation rules
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'start_date' => 'required|date_format:d/m/Y',
            'end_date' => 'required|date_format:d/m/Y',
            'location' => 'required|string',
            'organizer' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        // Handle image upload
        if (request()->hasFile('image')) {
            $file = str_replace(' ', '', uniqid(5).'-'.$request->image->getClientOriginalName());
            $filename = pathinfo($file, PATHINFO_FILENAME);

            $image = cloudinary()->uploadFile($request->image->getRealPath(),
            [
                'folder' => config('app.name').'/api',
                "public_id" => $filename,
                "use_filename" => TRUE
            ])->getSecurePath();
        }

        Event::create([
            'image' => $image ?? null,
            'title' => $request->title,
            'description' => $request->description,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'location' => $request->title,
            'organizer' => $request->organizer,
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'Event created successfully!',
        ], 200);
    }

    public function admin_event_update(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'event_id' => ['required', 'numeric', 'exists:events,id'],
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'start_date' => 'required|date_format:d/m/Y',
            'end_date' => 'required|date_format:d/m/Y',
            'location' => 'required|string',
            'organizer' => 'required|string',
            'image' => 'nullable|mimes:jpeg,png,jpg,gif|max:2048', // Define image validation rules
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $event = Event::find($request->event_id);

        // Handle image upload
        if (request()->hasFile('image')) {
            if($event->image)
            {
                $token = explode('/', $event->image);
                $token2 = explode('.', $token[sizeof($token)-1]);
                cloudinary()->destroy(config('app.name').'/api/'.$token2[0]);
            }
            $file = str_replace(' ', '', uniqid(5).'-'.$request->image->getClientOriginalName());
            $filename = pathinfo($file, PATHINFO_FILENAME);

            $image = cloudinary()->uploadFile($request->image->getRealPath(),
            [
                'folder' => config('app.name').'/api',
                "public_id" => $filename,
                "use_filename" => TRUE
            ])->getSecurePath();
        }

        $event->update([
            'title' => $request->title,
            'description' => $request->description,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'location' => $request->title,
            'organizer' => $request->organizer,
            'image' => $image ?? $event->image
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'Updated successfully!',
        ], 200);
    }

    public function admin_event_delete(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'event_id' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $event = Event::find($request->event_id);

        if(!$event)
        {
            return response()->json([
                'code' => 401,
                'message' => 'Not found in our database.',
            ], 401);
        }

        $event->delete();

        return response()->json([
            'code' => 200,
            'message' => 'Deleted Successfully!',
        ], 200);
    }

    // Announcement
    public function admin_announcements()
    {
        $announcements = Announcement::latest()->get();

        return response()->json([
            'code' => 200,
            'message' => 'All Annoucements Retrieved Successfully.',
            'data' => $announcements
        ], 200);
    }

    public function admin_announcements_post(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'title' => ['required', 'string', 'max:255'],
            'content' => 'required|string',
            'image' => 'nullable|mimes:jpeg,png,jpg,gif|max:2048', // Define image validation rules
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        // Handle image upload
        if (request()->hasFile('image')) {
            $file = str_replace(' ', '', uniqid(5).'-'.$request->image->getClientOriginalName());
            $filename = pathinfo($file, PATHINFO_FILENAME);

            $image = cloudinary()->uploadFile($request->image->getRealPath(),
            [
                'folder' => config('app.name').'/api',
                "public_id" => $filename,
                "use_filename" => TRUE
            ])->getSecurePath();
        }

        Announcement::create([
            'title' => $request->title,
            'content' => $request->content,
            'image' => $image ?? null,
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'Announcement created successfully!',
        ], 200);
    }

    public function admin_announcements_update(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'announcement_id' => ['required', 'numeric', 'exists:announcements,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => 'required|string',
            'image' => 'nullable|mimes:jpeg,png,jpg,gif|max:2048', // Define image validation rules
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $announcement = Announcement::find($request->announcement_id);

        // Handle image upload
        if (request()->hasFile('image')) {
            if($announcement->image)
            {
                $token = explode('/', $announcement->image);
                $token2 = explode('.', $token[sizeof($token)-1]);
                cloudinary()->destroy(config('app.name').'/api/'.$token2[0]);
            }
            $file = str_replace(' ', '', uniqid(5).'-'.$request->image->getClientOriginalName());
            $filename = pathinfo($file, PATHINFO_FILENAME);

            $image = cloudinary()->uploadFile($request->image->getRealPath(),
            [
                'folder' => config('app.name').'/api',
                "public_id" => $filename,
                "use_filename" => TRUE
            ])->getSecurePath();
        }

        $announcement->update([
            'title' => $request->title,
            'content' => $request->content,
            'image' => $image ?? $announcement->image,
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'Announcement updated successfully!',
        ], 200);
    }

    public function admin_announcements_delete(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'announcement_id' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $announcement = Announcement::find($request->announcement_id);

        if(!$announcement)
        {
            return response()->json([
                'code' => 401,
                'message' => 'Not found in our database.',
            ], 401);
        }

        $announcement->delete();

        return response()->json([
            'code' => 200,
            'message' => 'Deleted Successfully!',
        ], 200);
    }

    // Subscription
    public function subscription(Request $request)
    {
        if ($request->isMethod('get'))
        {
            $subscriptions = Subscription::latest()->get();

            return response()->json([
                'code' => 200,
                'message' => 'All Subscription Retrieved Successfully.',
                'data' => $subscriptions
            ], 200);
        }

        if ($request->isMethod('post'))
        {
            $validator = Validator::make(request()->all(), [
                'subscription_id' => ['required', 'numeric', 'exists:subscriptions,id'],
                'amount' => ['required', 'numeric'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'message' => 'Please see errors parameter for all errors.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $subscription = Subscription::find($request->subscription_id);

            $subscription->update([
                'amount' => $request->amount,
            ]);

            return response()->json([
                'code' => 200,
                'message' => 'Updated successfully!',
            ], 200);
        }
    }

    public function subscription_transaction()
    {
        $payments = Transaction::latest()
            ->where(function ($query) {
                $query->whereNull('due_id')
                    ->orWhere('due_id', '');
            })->with(['user', 'subscription'])->get();

        return response()->json([
            'code' => 200,
            'message' => 'All Subscription payment retrieved successfully!',
            'data' => $payments
        ], 200);
    }
}
