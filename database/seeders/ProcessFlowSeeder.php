<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProcessFlow;

class ProcessFlowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $waffleFlow = ProcessFlow::create([
            'name' => 'Flujo Combo Waffle',
            'description' => 'Flujo estándar para preparación y despacho de combos de Waffle en el local.',
            'is_active' => true,
        ]);

        $waffleFlow->phases()->createMany([
            ['name' => 'Atención y Pago', 'description' => 'Desde el saludo hasta registrar el pago.', 'sort_order' => 1],
            ['name' => 'Preparación de Masa', 'description' => 'Tiempo de cocción de la wafflera.', 'sort_order' => 2],
            ['name' => 'Picado y Decorado', 'description' => 'Fruta fresca y Nutella/arequipe.', 'sort_order' => 3],
            ['name' => 'Despacho de Helado', 'description' => 'Boleado y montaje del helado.', 'sort_order' => 4],
            ['name' => 'Despacho de Nestea', 'description' => 'Servido de bebida fría.', 'sort_order' => 5],
            ['name' => 'Entrega Final Combo', 'description' => 'Empaque y entrega de bandeja.', 'sort_order' => 6],
        ]);

        $iceCreamFlow = ProcessFlow::create([
            'name' => 'Flujo Tina o Cono Simple',
            'description' => 'Medición de tiempo para despacho de helado en tina o cono simple.',
            'is_active' => true,
        ]);

        $iceCreamFlow->phases()->createMany([
            ['name' => 'Atención y Pago', 'description' => 'Saludo, orden y cobro.', 'sort_order' => 1],
            ['name' => 'Servido de Helado', 'description' => 'Bolear helado en cono o tina.', 'sort_order' => 2],
            ['name' => 'Entrega del Helado', 'description' => 'Entrega al cliente.', 'sort_order' => 3],
        ]);
    }
}
