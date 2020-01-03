<?php

/**
 * @coversDefaultClass Bolt_Boltpay_Model_Source_Themes
 */
class Bolt_Boltpay_Model_Source_ThemesTest extends PHPUnit_Framework_TestCase
{

    /**
     * @test
     * that toOptionArray returns expected theme options array
     *
     * @covers ::toOptionArray
     */
    public function toOptionArray_always_returnsThemeOptionArray()
    {
        $current = new Bolt_Boltpay_Model_Source_Themes();
        $this->assertEquals(
            array(
                array(
                    'value' => 'light',
                    'label' => 'Light, for dark backgrounds'
                ),
                array(
                    'value' => 'dark',
                    'label' => 'Dark, for light backgrounds'
                ),
            ),
            $current->toOptionArray()
        );
    }
}
