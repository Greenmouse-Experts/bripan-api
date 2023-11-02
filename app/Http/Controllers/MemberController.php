<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Http\Resources\UserResource;
use App\Models\Announcement;
use App\Models\Due;
use App\Models\Event;
use App\Models\Notification;
use App\Models\Subscription;
use App\Models\Transaction;
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

    // Subscription
    public function subscription(Request $request)
    {
        $subscription = Subscription::where('type', Auth::user()->account_type)->first();

        return response()->json([
            'code' => 200,
            'message' => 'My Subscription Plan',
            'data' => $subscription
        ], 200);
    }

    public function profile()
    {
        return response()->json([
            'code' => 200,
            'message' => "Profile retrieved successfully.",
            'data' => new UserResource(Auth::user())
        ], 200);
    }

    public function subscription_payment(Request $request)
    {
        $SECRET_KEY = config('app.paystack_secret_key');
        
        $curl = curl_init();

        $validator = Validator::make(request()->all(), [
            'subscription_id' => ['required', 'numeric', 'exists:subscriptions,id'],
            'ref_id' => ['required','string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($request->ref_id),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer $SECRET_KEY",
                "Cache-Control: no-cache",
            ),
        ));
        
        $paystack_response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
            
        $result = json_decode($paystack_response);
        
        // return $result;
        if ($err) {
            // there was an error contacting the Paystack API
            return response()->json([
                'code' => 401,
                'message' => 'Transaction failed.'
            ], 401);

        } else {

            $user = User::find(Auth::user()->id);

            $user->update([
                'isSubscribed' => true
            ]);

            Transaction::create([
                'user_id' => Auth::user()->id,
                'subscription_id' => $request->subscription_id,
                'amount' => ($result->data->amount / 100),
                'ref_id' => $result->data->reference,
                'paid_at' => $result->data->paid_at,
                'channel' => $result->data->channel,
                'ip_address' => $result->data->ip_address,
                'status' => $result->data->status,
            ]);

            Notification::create([
                'user_id' => Auth::user()->id,
                'title' => 'Subscription Payment',
                'body' => 'You have successfully subscribed.',
                'image' => config('app.url').'/favicon.png',
                'type' => 'Subscription Payment'
            ]);

            return response()->json([
                'code' => 200,
                'message' => 'Subscription Completed.'
            ], 200);
        }
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

    // Payment
    public function payments()
    {
        $id = Auth::user()->id;
        
        $dues = Due::latest()
                ->with(['transactions' => function ($query) use ($id) {
                    $query->where('user_id', $id);
                }])
                ->get();

        // Filter dues with empty transactions for the specific user
         $duesWithEmptyTransactions = $dues->filter(function ($due) {
            return $due->transactions->isEmpty();
        });

        // Count the filtered dues
        $countOfDuesWithEmptyTransactions = $duesWithEmptyTransactions;

        return response()->json([
            'code' => 200,
            'message' => 'All Events Retrieved Successfully.',
            'data' => $countOfDuesWithEmptyTransactions->load('category')
        ], 200);
    }

    public function handleGatewayCallback(Request $request)
    {
        $SECRET_KEY = config('app.paystack_secret_key');
        
        $curl = curl_init();

        $validator = Validator::make(request()->all(), [
            'due_id' => ['required'],
            'ref_id' => ['required','string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($request->ref_id),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer $SECRET_KEY",
                "Cache-Control: no-cache",
            ),
        ));
        
        $paystack_response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
            
        $result = json_decode($paystack_response);
        
        // return $result;
        if ($err) {
            // there was an error contacting the Paystack API
            return response()->json([
                'code' => 401,
                'message' => 'Transaction failed.'
            ], 401);

        } else {

            Transaction::create([
                'user_id' => Auth::user()->id,
                'due_id' => $request->due_id,
                'amount' => ($result->data->amount / 100),
                'ref_id' => $result->data->reference,
                'paid_at' => $result->data->paid_at,
                'channel' => $result->data->channel,
                'ip_address' => $result->data->ip_address,
                'status' => $result->data->status,
            ]);

            Notification::create([
                'user_id' => Auth::user()->id,
                'title' => 'Due Payment',
                'body' => 'You have successfully made a payment.',
                'image' => config('app.url').'/favicon.png',
                'type' => 'Due Payment'
            ]);

            return response()->json([
                'code' => 200,
                'message' => 'Payment Completed.'
            ], 200);
        }
    }

    public function uploadReceipt(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'receipt' => 'required|mimes:jpeg,png,jpg,gif,pdf|max:2048', // Define receipt validation rules
            'due_id' => ['required', 'integer', 'exists:dues,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please see errors parameter for all errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $due = Due::find($request->due_id);

        // Handle image upload
        if (request()->hasFile('receipt')) {
            $file = str_replace(' ', '', uniqid(5).'-'.$request->receipt->getClientOriginalName());
            $filename = pathinfo($file, PATHINFO_FILENAME);

            $receipt = cloudinary()->uploadFile($request->receipt->getRealPath(),
            [
                'folder' => config('app.name').'/api',
                "public_id" => $filename,
                "use_filename" => TRUE
            ])->getSecurePath();
        }

        Transaction::create([
            'user_id' => Auth::user()->id,
            'due_id' => $due->id,
            'amount' => $due->amount,
            'receipt' => $receipt ?? null,
            'ref_id' => 'manual payment',
            'paid_at' => now(),
            'channel' => 'manual payment',
            'ip_address' => config('app.name'),
            'status' => 'pending',
        ]);

        Notification::create([
            'user_id' => Auth::user()->id,
            'title' => 'Due Payment',
            'body' => 'You have successfully uploaded a payment receipt.',
            'image' => config('app.url').'/favicon.png',
            'type' => 'Due Payment'
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'Payment Uploaded.',
        ]); 
    }

    public function payments_approved()
    {
        $approvedPayments = Transaction::latest()->where(['user_id' => Auth::user()->id, 'status' => 'success'])->with('due')->get();

        return response()->json([
            'code' => 200,
            'message' => 'All Approved Payments.',
            'data' => $approvedPayments
        ], 200);
    }

    public function payments_pending()
    {
        $pendingPayments = Transaction::latest()->where(['user_id' => Auth::user()->id, 'status' => 'pending'])->with('due')->get();

        return response()->json([
            'code' => 200,
            'message' => 'All Pending Payments.',
            'data' => $pendingPayments
        ], 200);
    }
}
