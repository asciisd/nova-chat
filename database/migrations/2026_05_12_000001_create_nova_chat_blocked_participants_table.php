<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Package-owned table that records globally-blocked chat participants.
 *
 * One row per blocked participant. The package's controller writes to it,
 * the consumer's user-side endpoint reads from it via $user->isChatBlocked().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nova_chat_blocked_participants', function (Blueprint $table) {
            $table->id();

            // Polymorphic pointer to the blocked participant (typically the user-side
            // ChatParticipant model). Stored as the morph alias when the consumer
            // populates config('nova-chat.morph_map').
            $table->string('participant_type');
            $table->unsignedBigInteger('participant_id');

            // Polymorphic pointer to the admin who issued the block. Nullable so
            // jobs / system-issued blocks remain valid rows.
            $table->nullableMorphs('blocked_by');

            $table->text('reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['participant_type', 'participant_id'],
                'nova_chat_blocked_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nova_chat_blocked_participants');
    }
};
