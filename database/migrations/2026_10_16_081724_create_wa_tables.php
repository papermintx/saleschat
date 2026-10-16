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
        Schema::create('wa_conversations', function (Blueprint $t) {
            $t->id();
            $t->string('wa_id')->unique();                   // nomor customer, mis. 6281234567890
            $t->string('bsuid')->nullable()->index();        // cadangan bila Meta tidak mengirim nomor
            $t->string('contact_name')->nullable();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status')->default('unassigned');     // unassigned | open | closed
            $t->timestamp('assigned_at')->nullable();
            $t->timestamp('last_customer_message_at')->nullable(); // untuk aturan 24 jam
            $t->timestamp('last_message_at')->nullable();
            $t->text('last_message')->nullable();
            $t->timestamps();
            $t->index(['assigned_to', 'status']);
        });

        Schema::create('wa_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('wa_conversations')->cascadeOnDelete();
            $t->string('direction');                         // in | out
            $t->string('type')->default('text');
            $t->text('body')->nullable();
            $t->string('wa_message_id')->nullable()->unique(); // cegah duplikat saat Meta kirim ulang
            $t->string('status')->nullable();                // sent | delivered | read | failed
            $t->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('wa_assignment_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('wa_conversations')->cascadeOnDelete();
            $t->foreignId('from_user_id')->nullable();
            $t->foreignId('to_user_id')->nullable();
            $t->foreignId('by_user_id')->nullable();
            $t->string('type');                              // auto | claim | transfer | release
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_tables');
    }
};
