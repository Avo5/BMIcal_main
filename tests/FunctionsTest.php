<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/functions.php';

class FunctionsTest extends TestCase
{
    public function testCalcBmi()
    {
        $bmi = calc_bmi(70.0, 175.0);
        $this->assertEquals(22.86, $bmi);
    }

    public function testActivityFactorDefault()
    {
        $this->assertEquals(1.2, activity_factor_from_level(null));
        $this->assertEquals(1.2, activity_factor_from_level(''));
        $this->assertEquals(1.55, activity_factor_from_level('medium'));
    }

    public function testInvertBmr()
    {
        // male, assume age 30, height 175, choose expected weight via formula
        $age = 30;
        $height = 175.0;
        $weight = 70.0;
        $bmr = calc_bmr('male', $weight, $height, $age);
        $inv = weight_from_bmr('male', (float)$bmr, $height, $age);
        $this->assertEqualsWithDelta($weight, $inv, 0.5);
    }
}
