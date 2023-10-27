<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class MemberController extends Controller
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
        $validator = Validator::make(request()->all(), [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:255', 'regex:/^([0-9\s\-\+\(\)]*)$/', 'min:10'],
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

        $user = User::find(Auth::user()->id);

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

        if ($user->email == $request->email) {
            $user->update([
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
            'message' => 'Profile updated successfully.',
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

        $user = User::find(Auth::user()->id);

        if($user->current_password !== $request->old_password)
        {
            return response()->json([
                'code' => 401,
                'message' => "Old password doesn't match, try again.",
            ], 401);
        }

        $user->password = Hash::make($request->new_password);
        $user->current_password = $request->new_password;
        $user->save();

        Notification::create([
            'user_id' => $user->id,
            'title' => config('app.name'),
            'body' => 'Your '.config('app.name').' password changed successfully.',
            'image' => config('app.url').'/favicon.png',
            'type' => 'Password Changed'
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'Password Updated Successfully',
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
        $user = User::find(Auth::user()->id);

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
            'message' => 'Profile Picture Uploaded Successfully!',
        ], 200);
    }

    public function get_all_notifications()
    {
        $notifications = Notification::latest()->where('user_id', Auth::user()->id)->with(['from_who', 'user'])->get();

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

    public function announcements()
    {
        $announcements = Announcement::latest()->get();

        return response()->json([
            'code' => 200,
            'message' => 'All Annoucements Retrieved Successfully.',
            'data' => $announcements
        ], 200);
    }

    public function events()
    {
        $events = Event::latest()->get();

        return response()->json([
            'code' => 200,
            'message' => 'All Events Retrieved Successfully.',
            'data' => $events
        ], 200);
    }

}
