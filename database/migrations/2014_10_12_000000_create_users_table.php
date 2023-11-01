<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('membership_id')->nullable()->unique();
            $table->string('account_type');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('username')->nullable()->unique();
            $table->string('email')->unique();
            $table->string('phone_number')->nullable();
            $table->string('gender')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('passport')->nullable();
            $table->string('certificates')->nullable();
            $table->string('password');
            $table->string('current_password')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('state')->nullable();
            $table->text('address')->nullable();
            $table->string('place_business_employment')->nullable();
            $table->string('nature_business_employment')->nullable();
            $table->string('membership_professional_bodies')->nullable();
            $table->string('previous_insolvency_work_experience')->nullable();
            $table->string('referee_email_address')->nullable();
            $table->string('role')->nullable();
            $table->enum('status', ['Active', 'Inactive', 'Pending', 'Unsubscribe'])->default('Active')->index();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
