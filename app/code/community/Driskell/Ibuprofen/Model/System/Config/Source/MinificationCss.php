<?php
/**
 * @copyright See LICENCE.md
 * @package   Driskell_Ibuprofen
 * @author    Jason Woods <devel@jasonwoods.me.uk>
 */

/**
 * Minification configuration for CSS
 */
class Driskell_Ibuprofen_Model_System_Config_Source_MinificationCss
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
            array('value' => 'php-minify', 'label' => 'PHP minify (No sourcemap)')
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
            'php-minify' => 'PHP minify'
        );
    }
}
