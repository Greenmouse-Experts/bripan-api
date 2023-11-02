<?php

namespace App\Http\Resources;

use App\Models\Announcement;
use App\Models\Due;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'membership_id' => $this->membership_id,
            'account_type' => $this->account_type,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'gender' => $this->gender,
            'avatar' => $this->avatar,
            'passport' => $this->passport,
            'certificates' => $this->certificates,
            'current_password' => $this->current_password,
            'marital_status' => $this->marital_status,
            'state' => $this->state,
            'address' => $this->address,
            'place_business_employment' => $this->place_business_employment,
            'nature_business_employment' => $this->nature_business_employment,
            'membership_professional_bodies' => $this->membership_professional_bodies,
            'previous_insolvency_work_experience' => $this->previous_insolvency_work_experience,
            'referee_email_address' => $this->referee_email_address,
            'role' => $this->role,
            'created_at' => $this->created_at,
            'totalFellow' => User::where('account_type', 'Fellow')->get()->count(),
            'totalAssociate' => User::where('account_type', 'Associate')->get()->count(),
            'totalDues' => Due::get()->count(),
            'totalPendingPayment' => Transaction::latest()->where('status', 'pending')->get()->count(),
            'totalApprovedPayment' => Transaction::latest()->where('status', 'success')->get()->count(),
            'latestSixMember' => User::latest()->get()->take(6),
            'latestSixAnnouncement' => Announcement::latest()->get()->take(6),
        ];  
    }
}
