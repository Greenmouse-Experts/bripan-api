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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->onDelete('cascade');
            $table->foreignId('due_id')->nullable()->onDelete('cascade');
            $table->foreignId('subscription_id')->nullable()->onDelete('cascade');
            $table->double('amount')->nullable();
            $table->string('receipt')->nullable();
            $table->string('ref_id')->nullable();
            $table->string('paid_at')->nullable();
            $table->string('channel')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
