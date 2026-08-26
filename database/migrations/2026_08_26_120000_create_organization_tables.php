<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->string('manager_name', 191)->nullable();
            $table->string('manager_phone', 30)->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('supervisors', function (Blueprint $table) {
            $table->id();
            $table->string('supervisor_code', 50)->unique();
            $table->string('name', 191);
            $table->string('phone', 30)->nullable();
            $table->string('email', 191)->nullable();
            $table->foreignId('region_id')->constrained('regions')->restrictOnDelete();
            $table->string('status', 30)->default('active')->index();
            $table->date('assignment_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['region_id', 'status']);
        });

        Schema::create('supervisor_assignment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_id')->constrained('supervisors')->cascadeOnDelete();
            $table->foreignId('previous_region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->foreignId('new_region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('change_type', 50);
            $table->string('reason', 191)->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('effective_at');
            $table->timestamps();

            $table->index(['supervisor_id', 'effective_at']);
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('code', 50)->unique();
            $table->string('contact_person', 191)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 191)->nullable();
            $table->text('address')->nullable();
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->string('contract_status', 30)->default('pending')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('code', 50)->unique();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('region_id')->constrained('regions')->restrictOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('supervisors')->nullOnDelete();
            $table->string('physical_location', 191)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('site_contact_person', 191)->nullable();
            $table->string('site_contact_phone', 30)->nullable();
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->unsignedInteger('required_guards')->default(0);
            $table->unsignedInteger('required_day_guards')->default(0);
            $table->unsignedInteger('required_night_guards')->default(0);
            $table->unsignedInteger('number_of_posts')->default(0);
            $table->string('status', 30)->default('pending')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['region_id', 'status']);
            $table->index(['client_id', 'status']);
            $table->index(['supervisor_id', 'status']);
        });

        Schema::create('site_manpower_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->unsignedInteger('required_total')->default(0);
            $table->unsignedInteger('required_day')->default(0);
            $table->unsignedInteger('required_night')->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_current')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['site_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_manpower_requirements');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('supervisor_assignment_histories');
        Schema::dropIfExists('supervisors');
        Schema::dropIfExists('regions');
    }
};
