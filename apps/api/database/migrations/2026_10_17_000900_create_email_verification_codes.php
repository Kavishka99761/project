<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Common Platform Layer — one-time email codes.
     *
     * Registration sends a code to prove the address exists; "forgot password"
     * sends one to prove the account is yours. Only a hash of the code is kept
     * (same as a password), together with the number of wrong guesses so a code
     * can be thrown away before it can be brute-forced.
     */
    public function up(): void
    {
        Schema::create('email_verification_codes', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('code');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('created_at')->nullable();
        });

        // The reset table now holds a hashed 6-digit code rather than a long
        // URL token, so the column is named for what it stores and matches the
        // verification table above.
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('token');
        });

        if (Schema::hasColumn('password_reset_tokens', 'token')) {
            Schema::table('password_reset_tokens', function (Blueprint $table) {
                $table->renameColumn('token', 'code');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_codes');

        if (Schema::hasColumn('password_reset_tokens', 'code')) {
            Schema::table('password_reset_tokens', function (Blueprint $table) {
                $table->renameColumn('code', 'token');
            });
        }

        if (Schema::hasColumn('password_reset_tokens', 'attempts')) {
            Schema::table('password_reset_tokens', function (Blueprint $table) {
                $table->dropColumn('attempts');
            });
        }
    }
};
