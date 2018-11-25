<?php
/**
 * @copyright See LICENCE.md
 * @package   Driskell_Ibuprofen
 * @author    Jason Woods <devel@jasonwoods.me.uk>
 */

/**
 * Minification configuration
 */
class Driskell_Ibuprofen_Model_System_Config_Source_Minification
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => '', 'label' => 'None'),
            array('value' => 'uglifyjs', 'label' => 'UglifyJS (Compress + Mangle; NodeJS required)'),
            array('value' => 'uglifyjs-m', 'label' => 'UglifyJS (Mangle only; NodeJS required')
        );
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            '' => 'None',
            'uglifyjs' => 'UglifyJS (Compress + Mangle)',
            'uglifyjs-m' => 'UglifyJS (Mangle only)',
        );
    }
}
