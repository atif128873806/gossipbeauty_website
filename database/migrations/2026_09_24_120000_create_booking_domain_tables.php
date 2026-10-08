<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('summary')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_category_id')->constrained()->restrictOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('summary')->nullable();
            $table->text('inclusions')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedSmallInteger('buffer_before_minutes')->default(0);
            $table->unsignedSmallInteger('buffer_after_minutes')->default(0);
            $table->string('price_mode', 16)->default('quote');
            $table->unsignedInteger('price_minor')->nullable();
            $table->string('price_note')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_online_bookable')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['service_category_id', 'is_active', 'position']);
            $table->index(['is_online_bookable', 'is_active']);
        });

        Schema::create('add_ons', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(0);
            $table->string('price_mode', 16)->default('quote');
            $table->unsignedInteger('price_minor')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_standalone')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_add_on', function (Blueprint $table) {
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('add_on_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->primary(['service_id', 'add_on_id']);
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('title')->nullable();
            $table->text('public_bio')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_bookable')->default(false);
            $table->boolean('accepts_new_clients')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 24)->default('specialist')->index();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
        });

        Schema::create('staff_service', function (Blueprint $table) {
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('duration_override_minutes')->nullable();
            $table->unsignedInteger('price_override_minor')->nullable();
            $table->boolean('accepts_new_clients')->default(true);
            $table->primary(['staff_id', 'service_id']);
        });

        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->index(['staff_id', 'weekday', 'effective_from', 'effective_until']);
        });

        Schema::create('schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->cascadeOnDelete();
            $table->string('kind', 24);
            $table->dateTime('starts_at_utc');
            $table->dateTime('ends_at_utc');
            $table->string('label')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['staff_id', 'starts_at_utc', 'ends_at_utc']);
            $table->index(['kind', 'starts_at_utc', 'ends_at_utc']);
        });

        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('booking_configuration', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('timezone', 64)->default('America/Toronto');
            $table->unsignedSmallInteger('booking_horizon_days')->default(14);
            $table->unsignedSmallInteger('minimum_notice_minutes')->default(0);
            $table->unsignedSmallInteger('slot_increment_minutes')->default(30);
            $table->boolean('online_booking_enabled')->default(false);
            $table->boolean('instant_confirmation')->default(true);
            $table->boolean('allow_online_cancellation')->default(false);
            $table->boolean('allow_online_rescheduling')->default(false);
            $table->json('policy_text')->nullable();
            $table->timestamps();
        });

        Schema::create('service_resource', function (Blueprint $table) {
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('units_required')->default(1);
            $table->primary(['service_id', 'resource_id']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->text('first_name');
            $table->text('last_name')->nullable();
            $table->text('phone');
            $table->char('phone_search_hash', 64)->nullable()->unique();
            $table->text('email')->nullable();
            $table->char('email_search_hash', 64)->nullable()->index();
            $table->text('admin_notes')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('public_reference', 24)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->dateTime('starts_at_utc');
            $table->dateTime('ends_at_utc');
            $table->dateTime('busy_starts_at_utc');
            $table->dateTime('busy_ends_at_utc');
            $table->string('timezone', 64)->default('America/Toronto');
            $table->string('status', 32)->default('confirmed');
            $table->string('origin', 24)->default('website');
            $table->string('idempotency_key', 80)->nullable()->unique();
            $table->string('price_mode', 16)->default('quote');
            $table->unsignedInteger('price_minor')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(0);
            $table->json('policy_snapshot')->nullable();
            $table->text('customer_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['staff_id', 'busy_starts_at_utc', 'busy_ends_at_utc']);
            $table->index(['status', 'starts_at_utc']);
            $table->index(['customer_id', 'starts_at_utc']);
        });

        Schema::create('appointment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('add_on_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_type', 16);
            $table->string('name_snapshot');
            $table->unsignedSmallInteger('duration_snapshot_minutes')->default(0);
            $table->string('price_mode_snapshot', 16)->default('quote');
            $table->unsignedInteger('price_snapshot_minor')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['appointment_id', 'position']);
        });

        Schema::create('appointment_resources', function (Blueprint $table) {
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('units_reserved')->default(1);
            $table->primary(['appointment_id', 'resource_id']);
            $table->index('resource_id');
        });

        Schema::create('booking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 24)->default('system');
            $table->string('event_type', 48);
            $table->json('changes')->nullable();
            $table->string('source', 24)->nullable();
            $table->timestamps();

            $table->index(['appointment_id', 'created_at']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notification_type', 48);
            $table->string('channel', 16);
            $table->text('recipient');
            $table->string('status', 16)->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('provider_reference')->nullable();
            $table->text('last_error')->nullable();
            $table->dateTime('last_attempt_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['appointment_id', 'notification_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('booking_events');
        Schema::dropIfExists('appointment_resources');
        Schema::dropIfExists('appointment_items');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('service_resource');
        Schema::dropIfExists('resources');
        Schema::dropIfExists('booking_configuration');
        Schema::dropIfExists('schedule_exceptions');
        Schema::dropIfExists('working_hours');
        Schema::dropIfExists('staff_service');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_id');
            $table->dropColumn('role');
        });
        Schema::table('staff', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
        Schema::dropIfExists('staff');
        Schema::dropIfExists('service_add_on');
        Schema::dropIfExists('add_ons');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
    }
};
