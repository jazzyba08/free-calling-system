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
        // First, check if the table exists
        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                // Add columns if they don't exist
                if (!Schema::hasColumn('messages', 'sender_id')) {
                    $table->unsignedBigInteger('sender_id')->after('id');
                    $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
                }
                
                if (!Schema::hasColumn('messages', 'receiver_id')) {
                    $table->unsignedBigInteger('receiver_id')->after('sender_id');
                    $table->foreign('receiver_id')->references('id')->on('users')->onDelete('cascade');
                }
                
                if (!Schema::hasColumn('messages', 'message')) {
                    $table->text('message')->after('receiver_id');
                }
                
                if (!Schema::hasColumn('messages', 'is_read')) {
                    $table->boolean('is_read')->default(false)->after('message');
                }
                
                // Add indexes for better performance
                $table->index(['sender_id', 'receiver_id']);
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                // Drop foreign keys first
                $table->dropForeign(['sender_id']);
                $table->dropForeign(['receiver_id']);
                
                // Then drop columns
                $table->dropColumn(['sender_id', 'receiver_id', 'message', 'is_read']);
            });
        }
    }
};