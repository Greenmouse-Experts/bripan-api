<?php

namespace App\Http\Controllers;

use App\Http\Resources\AdminResource;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\UserResource;
use App\Models\Notification;
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
                ]);
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
        ]);
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
            ]);
        }

        $admin = User::find(Auth::user()->id);

        if($admin->current_password !== $request->old_password)
        {
            return response()->json([
                'code' => 401,
                'message' => "Old password doesn't match, try again.",
            ]);
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
        ]);
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
            ]);
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
        ]);
    }

    public function get_all_notifications()
    {
        $notifications = Notification::latest()->with(['from_who', 'user'])->get();

        return response()->json([
            'code' => 200,
            'message' => 'All notifications retrieved.',
            'data' => NotificationResource::collection($notifications)
        ]);
    }

    public function get_all_unread_notifications()
    {
        $userUnreadNotifications = Notification::latest()->where('user_id', Auth::user()->id)->where('status', 'Unread')->with(['from_who'])->get();

        return response()->json([
            'code' => 200,
            'message' => 'All unread notifications retrieved.',
            'data' => NotificationResource::collection($userUnreadNotifications)
        ]);
    }

    public function count_unread_notifications()
    {
        $userCountUnreadNotifications = Notification::latest()->where('user_id', Auth::user()->id)->where('status', 'Unread')->count();

        return response()->json([
            'code' => 200,
            'message' => 'Count all unread notifications.',
            'data' => $userCountUnreadNotifications
        ]);
    }

    public function read_notification(Request $request)
    {
        $notification = Notification::find($request->notification_id);

        if(!$notification)
        {
            return response()->json([
                'code' => 401,
                'message' => 'Not found in our database.'
            ]);
        }

        if($notification->user_id !== Auth::user()->id)
        {
            return response()->json([
                'code' => 401,
                'message' => "Notification doesn't belong to you."
            ]);
        }
        
        $notification->update([
            'status' => 'Read'
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'Notification read successfully.'
        ]);
    }

    public function delete_notification(Request $request)
    {
        $notification = Notification::find($request->notification_id);

        if(!$notification)
        {
            return response()->json([
                'code' => 401,
                'message' => 'Not found in our database.'
            ]);
        }

        $notification->delete();

        return response()->json([
            'code' => 200,
            'message' => 'Notification deleted successfully.'
        ]);
    }

    public function get_all_member(Request $request)
    {
        $users = UserResource::collection($this->getUserBySearch($request, 1000))
                ->response()
                ->getData(true);

        return response()->json([
            'success' => true,
            'message' => 'All members retrieved successfully.',
            'data' => $users
        ]);
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ]);
        }

        $existing = User::where('email', $request->email)->first();
            
        if($existing)
        {
            return response()->json([
                'code' => 401,
                'message' => 'Email already exists.'
            ]);
        }

        $latestId = User::where('account_type', 'Member')->max('id') + 1;
        $customId = str_pad($latestId, 3, '0', STR_PAD_LEFT);
        $password = Str::random(8);

        $user = User::create([
            'membership_id' => config('app.name').$customId,
            'account_type' => 'Member',
            'name' => $request->name,
            'username' => config('app.name').strtoupper(substr($request->name, 0, 2)).$customId,
            'email' => $request->email,
            'email_verified_at' => now(),
            'password' => $password,
            'current_password' => $password,
            'phone_number' => $request->phone_number,
            'gender' => $request->gender,
            'marital_status' => $request->marital_status,
            'state' => $request->state,
            'address' =>  $request->address,
        ]);  
        
        /** Store information to include in mail in $data as an array */
        $data = array(
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'membership_id' => $user->membership_id,
            'password' => $password
        );

        /** Send message to the user */
        Mail::send('emails.notifyMember', $data, function ($m) use ($data) {
            $m->to($data['email'])->subject(config('app.name'));
        });

        return response()->json([
            'code' => 200,
            'message' => $user->name.' account created successfully!',
        ]);
    }

    public function member_activate(Request $request)
    {
        $user = User::find($request->user_id);

        if(!$user)
        {
            return response()->json([
                'code' => 401,
                'message' => 'No user with the ID - '.$request->user_id.' in our database.',
            ]);
        }

        $user->update([
            'status' => 'Active'
        ]);

        return response()->json([
            'code' => 200,
            'message' => $user->first_name.' '.$user->last_name. ' account activated successfully!'
        ]);
    }

    public function member_deactivate(Request $request)
    {
        $user = User::find($request->user_id);

        if(!$user)
        {
            return response()->json([
                'code' => 401,
                'message' => 'No user with the ID - '.$request->user_id.' in our database.',
            ]);
        }

        $user->update([
            'status' => 'Inactive'
        ]);

        return response()->json([
            'code' => 200,
            'message' => $user->first_name.' '.$user->last_name. ' account deactivated successfully!'
        ]);
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
            ]);
        }

        $validator = Validator::make(request()->all(), [
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ]);
        }

        if($user->email == $request->email)
        {
            $user->update([
                'first_name' => $request->name,
                'phone_number' => $request->phone_number,
                'gender' => $request->gender,
                'marital_status' => $request->marital_status,
                'state' => $request->state,
                'address' =>  $request->address,
            ]);
        } else {
            //Validate Request
            $validator = Validator::make(request()->all(), [
                'email' => ['string', 'email', 'max:255'],
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'message' => 'Please see errors parameter for all errors.',
                    'errors' => $validator->errors()
                ]);
            }

            $existing = User::where('email', $request->email)->first();
            
            if($existing)
            {
                return response()->json([
                    'code' => 401,
                    'message' => 'Email already exists.'
                ]);
            }

            $user->update([
                'first_name' => $request->first_name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'gender' => $request->gender,
                'marital_status' => $request->marital_status,
                'state' => $request->state,
                'address' =>  $request->address,
            ]); 
        }

        return response()->json([
            'code' => 200,
            'message' => $user->first_name.' '.$user->last_name. ' profile updated successfully!'
        ]);
    }

    public function member_update_password(Request $request)
    {
        $user = User::find($request->user_id);

        if(!$user)
        {
            return response()->json([
                'code' => 401,
                'message' => 'No user with the ID - '.$request->user_id.' in our database.',
            ]);
        }

        $validator = Validator::make(request()->all(), [
            'new_password' => ['required', 'string', 'min:8', 'confirmed']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ]);
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
        ]);
    }

    public function member_update_profile_picture(Request $request)
    {
        $user = User::find($request->user_id);

        if(!$user)
        {
            return response()->json([
                'code' => 401,
                'message' => 'No user with the ID - '.$request->user_id.' in our database.',
            ]);
        }

        $validator = Validator::make(request()->all(), [
            'avatar' => 'required|mimes:jpeg,png,jpg',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ]);
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
        ]);
    }

    public function member_resend_login_details(Request $request)
    {
        $user = User::find($request->user_id);

        if(!$user)
        {
            return response()->json([
                'code' => 401,
                'message' => 'No user with the ID - '.$request->user_id.' in our database.',
            ]);
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
        ]); 
    }

    public function member_view_payments($id)
    {
        $finder = Crypt::decrypt($id);

        $payments = Transaction::latest()->where('user_id', $finder)->get();

        $user = User::find($finder);

        return view('admin.member.view_payments', [
            'payments' => $payments,
            'user' => $user
        ]);
    }
}
