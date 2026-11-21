<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $t) { $t->id(); $t->string('name'); $t->timestamps(); });
        Schema::create('outlets', function (Blueprint $t) { $t->id(); $t->foreignId('business_id')->constrained(); $t->string('name'); $t->string('code'); $t->timestamps(); $t->unique(['business_id', 'code']); });
        Schema::table('users', function (Blueprint $t) { $t->foreignId('business_id')->nullable()->constrained(); $t->foreignId('outlet_id')->nullable()->constrained(); $t->string('role')->default('cashier'); });
        Schema::create('members', function (Blueprint $t) { $t->id(); $t->foreignId('business_id')->constrained(); $t->string('code'); $t->string('name'); $t->string('phone'); $t->boolean('promo_consent')->default(false); $t->timestamp('consented_at')->nullable(); $t->integer('point_balance')->default(0); $t->integer('point_debt')->default(0); $t->timestamps(); $t->unique(['business_id', 'phone']); $t->unique(['business_id', 'code']); });
        Schema::create('point_rules', function (Blueprint $t) { $t->id(); $t->foreignId('business_id')->constrained(); $t->unsignedInteger('version'); $t->unsignedInteger('earn_step')->default(10000); $t->unsignedInteger('earn_points')->default(1); $t->unsignedInteger('minimum_spend')->default(10000); $t->unsignedInteger('redeem_value')->default(100); $t->unsignedInteger('minimum_redeem')->default(10); $t->unsignedInteger('maximum_redeem_percent')->default(50); $t->unsignedInteger('approval_threshold')->default(100); $t->unsignedInteger('valid_days')->default(365); $t->timestamp('effective_at'); $t->foreignId('created_by')->nullable()->constrained('users'); $t->timestamps(); $t->unique(['business_id', 'version']); });
        Schema::create('transactions', function (Blueprint $t) { $t->id(); $t->foreignId('business_id')->constrained(); $t->foreignId('outlet_id')->constrained(); $t->foreignId('member_id')->nullable()->constrained(); $t->foreignId('cashier_id')->constrained('users'); $t->foreignId('point_rule_id')->nullable()->constrained(); $t->string('reference'); $t->string('idempotency_key'); $t->string('status')->default('paid'); $t->unsignedInteger('subtotal'); $t->unsignedInteger('discount')->default(0); $t->unsignedInteger('redeem_points')->default(0); $t->unsignedInteger('redeem_rupiah')->default(0); $t->unsignedInteger('total'); $t->unsignedInteger('earned_points')->default(0); $t->integer('balance_after')->nullable(); $t->string('payment_method'); $t->text('refund_reason')->nullable(); $t->foreignId('refunded_by')->nullable()->constrained('users'); $t->timestamp('refunded_at')->nullable(); $t->timestamps(); $t->unique(['business_id', 'idempotency_key']); $t->unique(['business_id', 'reference']); });
        Schema::create('transaction_items', function (Blueprint $t) { $t->id(); $t->foreignId('transaction_id')->constrained(); $t->string('name'); $t->unsignedInteger('quantity'); $t->unsignedInteger('unit_price'); $t->unsignedInteger('line_total'); $t->boolean('eligible')->default(true); });
        Schema::create('point_lots', function (Blueprint $t) { $t->id(); $t->foreignId('business_id')->constrained(); $t->foreignId('member_id')->constrained(); $t->foreignId('source_transaction_id')->nullable()->constrained('transactions'); $t->unsignedInteger('original_points'); $t->unsignedInteger('remaining_points'); $t->timestamp('expires_at'); $t->timestamps(); $t->index(['member_id', 'expires_at']); });
        Schema::create('point_allocations', function (Blueprint $t) { $t->id(); $t->foreignId('transaction_id')->constrained(); $t->foreignId('point_lot_id')->constrained(); $t->unsignedInteger('points'); $t->unique(['transaction_id', 'point_lot_id']); });
        Schema::create('point_ledger_entries', function (Blueprint $t) { $t->id(); $t->foreignId('business_id')->constrained(); $t->foreignId('member_id')->constrained(); $t->foreignId('transaction_id')->nullable()->constrained(); $t->foreignId('point_lot_id')->nullable()->constrained(); $t->foreignId('actor_id')->nullable()->constrained('users'); $t->string('type'); $t->integer('points_delta'); $t->string('note')->nullable(); $t->timestamps(); $t->index(['member_id', 'created_at']); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->id(); $t->foreignId('business_id')->constrained(); $t->foreignId('actor_id')->constrained('users'); $t->foreignId('outlet_id')->nullable()->constrained(); $t->string('action'); $t->string('subject_type'); $t->unsignedBigInteger('subject_id'); $t->json('before')->nullable(); $t->json('after')->nullable(); $t->timestamps(); });
    }

    public function down(): void
    {
        foreach (['audit_logs','point_ledger_entries','point_allocations','point_lots','transaction_items','transactions','point_rules','members'] as $table) Schema::dropIfExists($table);
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('outlet_id'));
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('business_id'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('role'));
        Schema::dropIfExists('outlets'); Schema::dropIfExists('businesses');
    }
};
