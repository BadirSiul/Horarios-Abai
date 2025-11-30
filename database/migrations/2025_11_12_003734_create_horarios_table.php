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
       Schema::create('horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained()->onDelete('cascade');
            $table->date('fecha')->nullable();
            $table->string('dia')->nullable();
            $table->string('transacciones')->nullable();
            $table->string('hi')->nullable();
            $table->string('hf')->nullable();
            $table->string('hid1')->nullable();
            $table->string('hfd1')->nullable();
            $table->string('hid2')->nullable();
            $table->string('hfd2')->nullable();
            $table->string('hid3')->nullable();
            $table->string('hfd3')->nullable();
            $table->string('hia')->nullable(); // almuerzo inicio
            $table->string('hfa')->nullable(); // almuerzo fin
            $table->string('hic1')->nullable();
            $table->string('hfc1')->nullable();
            $table->string('hrs_prog')->nullable();
            $table->string('servicio')->nullable();
            $table->string('cuenta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('horarios');
    }
};
