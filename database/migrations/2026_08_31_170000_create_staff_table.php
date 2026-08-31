<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('employment_id', 32)->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('full_name');
            $table->string('gender', 16)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('alternative_phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('national_id', 50)->nullable();
            $table->date('date_employed')->nullable();
            $table->string('employment_status', 32)->default('active')->index();
            $table->string('job_title', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('monthly_salary', 14, 2)->default(0);
            $table->string('bank_name', 120)->nullable();
            $table->string('bank_account', 64)->nullable();
            $table->string('nssf_number', 40)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
