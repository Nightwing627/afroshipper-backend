<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->enum('type', ['dispatch', 'shipping', 'purchase'])->default('shipping');
            $table->enum('status', ['created', 'shipped', 'received', 'dispatched', 'collected'])->default('created');
            $table->float('weight');
            $table->string('weight_unit')->default('kg');
            $table->float('width')->nullable();
            $table->float('height')->nullable();
            $table->string('total')->nullable();
            $table->float('cost', 12, 2);
            $table->float('insurance', 12, 2)->default(0);
            $table->float('service_fee', 12, 2)->default(0);
            $table->json('notes')->nullable();
            $table->boolean('paid')->default(false);
            $table->string('ref_paystack')->nullable();
            $table->bigInteger('created_by');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('shipped_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->dateTime('dispatched_at')->nullable();
            $table->dateTime('collected_at')->nullable();
            $table->date('paid_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('invoices');
    }
}
