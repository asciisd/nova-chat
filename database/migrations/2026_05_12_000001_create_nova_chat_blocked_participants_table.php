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
            // jobs / system-issued blocks remain valid rows. We pass an explicit
            // index name because the auto-generated one (table prefix + col1 +
            // col2 + "_index") would be 67 chars and exceed MySQL's 64-char
            // identifier cap.
            $table->nullableMorphs('blocked_by', 'nova_chat_blocked_by_idx');

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
