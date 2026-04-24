<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'receipt_no')) {
                $table->string('receipt_no')->nullable()->after('utm_term');
            }
            if (! Schema::hasColumn('leads', 'receipt_date')) {
                $table->date('receipt_date')->nullable()->after('receipt_no');
            }
            if (! Schema::hasColumn('leads', 'customer_code')) {
                $table->string('customer_code')->nullable()->after('receipt_date');
            }
            if (! Schema::hasColumn('leads', 'payment_against')) {
                $table->string('payment_against')->nullable()->after('customer_code');
            }
            if (! Schema::hasColumn('leads', 'cheque_no')) {
                $table->string('cheque_no')->nullable()->after('payment_against');
            }
            if (! Schema::hasColumn('leads', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('cheque_no');
            }
            if (! Schema::hasColumn('leads', 'transaction_description')) {
                $table->text('transaction_description')->nullable()->after('bank_name');
            }
            if (! Schema::hasColumn('leads', 'transaction_amount')) {
                $table->decimal('transaction_amount', 14, 2)->nullable()->after('transaction_description');
            }
            if (! Schema::hasColumn('leads', 'amount_in_words')) {
                $table->string('amount_in_words')->nullable()->after('transaction_amount');
            }
            if (! Schema::hasColumn('leads', 'receipt_notes')) {
                $table->text('receipt_notes')->nullable()->after('amount_in_words');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $cols = [
                'receipt_no',
                'receipt_date',
                'customer_code',
                'payment_against',
                'cheque_no',
                'bank_name',
                'transaction_description',
                'transaction_amount',
                'amount_in_words',
                'receipt_notes',
            ];

            foreach ($cols as $c) {
                if (Schema::hasColumn('leads', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};

