<?php

namespace Tests\Unit\Services;

use App\Services\PrinterService;
use PHPUnit\Framework\TestCase;

class PrinterServiceTest extends TestCase
{
    public function test_cantidad_item_muestra_kg_con_tres_decimales_en_pesables()
    {
        $this->assertEquals('1,333', PrinterService::cantidadItem([
            'cantidad' => 1.3333,
            'pesable' => true,
        ]));

        $this->assertEquals('0,800', PrinterService::cantidadItem([
            'cantidad' => 0.8,
            'pesable' => true,
        ]));
    }

    public function test_cantidad_item_deja_la_cantidad_tal_cual_en_no_pesables()
    {
        $this->assertEquals('3', PrinterService::cantidadItem([
            'cantidad' => 3,
            'pesable' => false,
        ]));
    }
}
