<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SqliteCompatibilityFunctionsTest extends TestCase
{
    public function test_mysql_compatibility_functions_work_in_sqlite(): void
    {
        $result = DB::selectOne("
            SELECT 
                FLOOR(4.9) as floor_val,
                CEIL(4.1) as ceil_val,
                CEILING(4.1) as ceiling_val,
                CONCAT('Hola', ' ', 'Mundo') as concat_val,
                LEAST(10, 5, 20) as least_val,
                GREATEST(10, 5, 20) as greatest_val,
                IF(1 = 1, 'VERDADERO', 'FALSO') as if_true,
                IF(1 = 0, 'VERDADERO', 'FALSO') as if_false,
                DATEDIFF('2026-11-15', '2026-11-10') as datediff_val,
                TIMESTAMPDIFF(DAY, '2026-11-10', '2026-11-15') as diff_days,
                TIMESTAMPDIFF('MONTH', '2026-01-01', '2026-06-01') as diff_months,
                DATE_SUB('2026-11-15 00:00:00', INTERVAL 5 DAY) as date_sub_val,
                DATE_ADD('2026-11-10 00:00:00', INTERVAL 5 DAY) as date_add_val,
                DATE_FORMAT('2026-11-11 10:30:00', '%Y-%m-%d') as formatted_date
        ");

        $this->assertEquals(4, (int) $result->floor_val);
        $this->assertEquals(5, (int) $result->ceil_val);
        $this->assertEquals(5, (int) $result->ceiling_val);
        $this->assertEquals('Hola Mundo', $result->concat_val);
        $this->assertEquals(5, (float) $result->least_val);
        $this->assertEquals(20, (float) $result->greatest_val);
        $this->assertEquals('VERDADERO', $result->if_true);
        $this->assertEquals('FALSO', $result->if_false);
        $this->assertEquals(5, (int) $result->datediff_val);
        $this->assertEquals(5, (int) $result->diff_days);
        $this->assertEquals(5, (int) $result->diff_months);
        $this->assertEquals('2026-11-10 00:00:00', $result->date_sub_val);
        $this->assertEquals('2026-11-15 00:00:00', $result->date_add_val);
        $this->assertEquals('2026-11-11', $result->formatted_date);
    }
}
