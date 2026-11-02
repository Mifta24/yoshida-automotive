<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('reference', 20)->nullable()->unique()->after('id');
            $table->unsignedTinyInteger('vehicle_count')->default(1)->after('license_plate');
            $table->string('contact_type', 20)->nullable()->after('customer_phone'); // whatsapp | phone | email
        });

        DB::table('bookings')->whereNull('reference')->orderBy('id')->each(
            fn ($booking) => DB::table('bookings')->where('id', $booking->id)->update([
                'reference' => 'BK-'.str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT),
            ])
        );
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropColumn(['reference', 'vehicle_count', 'contact_type']);
        });
    }
};
