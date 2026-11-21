<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pos_connections', function (Blueprint $t) { $t->id(); $t->foreignId('business_id')->constrained(); $t->foreignId('created_by')->constrained('users'); $t->string('name'); $t->string('secret_hash',64); $t->boolean('active')->default(true); $t->timestamp('last_used_at')->nullable(); $t->timestamps(); });
        Schema::create('pos_sale_links', function (Blueprint $t) { $t->id(); $t->foreignId('pos_connection_id')->constrained(); $t->string('external_sale_id',100); $t->string('payload_hash',64); $t->foreignId('transaction_id')->constrained(); $t->timestamps(); $t->unique(['pos_connection_id','external_sale_id']); $t->unique('transaction_id'); });
    }
    public function down(): void { Schema::dropIfExists('pos_sale_links'); Schema::dropIfExists('pos_connections'); }
};
