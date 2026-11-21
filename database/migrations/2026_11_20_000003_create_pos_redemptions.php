<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pos_point_reservations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pos_connection_id')->constrained();
            $t->string('external_key', 100);
            $t->foreignId('business_id')->constrained();
            $t->foreignId('member_id')->constrained();
            $t->foreignId('outlet_id')->constrained();
            $t->foreignId('point_rule_id')->constrained();
            $t->foreignId('transaction_id')->nullable()->constrained();
            $t->string('status')->default('reserved');
            $t->unsignedInteger('points');
            $t->unsignedInteger('rupiah');
            $t->unsignedInteger('subtotal');
            $t->unsignedInteger('discount');
            $t->unsignedInteger('eligible_subtotal');
            $t->timestamps();
            $t->unique(['pos_connection_id', 'external_key']);
        });
        Schema::create('pos_reservation_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('reservation_id')->constrained('pos_point_reservations')->cascadeOnDelete();
            $t->foreignId('point_lot_id')->constrained();
            $t->unsignedInteger('points');
            $t->unique(['reservation_id', 'point_lot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_reservation_allocations');
        Schema::dropIfExists('pos_point_reservations');
    }
};
