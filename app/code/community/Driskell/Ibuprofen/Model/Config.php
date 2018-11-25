<?php
/**
 * @copyright See LICENCE.md
 * @package   Driskell_Ibuprofen
 * @author    Jason Woods <devel@jasonwoods.me.uk>
 */

/**
 * Configuration
 */
class Driskell_Ibuprofen_Model_Config
{
    const XML_PATH_ACTIVE = 'driskell_ibuprofen/general/active';
    const XML_PATH_ALL_BLOCKS = 'driskell_ibuprofen/general/all_blocks';
    const XML_PATH_SEPARATED_CONTROLLER = 'driskell_ibuprofen/general/separated_controller';
    const XML_PATH_SOURCEMAPS = 'driskell_ibuprofen/general/sourcemaps';
    const XML_PATH_DEBUG = 'driskell_ibuprofen/general/debug';
    const XML_PATH_MINIFICATION = 'driskell_ibuprofen/general/minification';

    /**
     * Are we active?
     *
     * @return boolean
     */
    public function isActive()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ACTIVE);
    }

    /**
     * Should we process all blocks using the head class or just the main one?
     *
     * @return boolean
     */
    public function isAllBlocks()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ALL_BLOCKS);
    }

    /**
     * Should a bundle be generated for the controller handle instead of just
     * for the first handle and then a second bundle for the remaining handles
     *
     * @return boolean
     */
    public function isSeparatedController()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_SEPARATED_CONTROLLER);
    }

    /**
     * Get minification type
     *
     * @return boolean
     */
    public function getMinification()
    {
        return Mage::getStoreConfig(self::XML_PATH_MINIFICATION);
    }

    /**
     * Enable source map processing
     *
     * @return boolean
     */
    public function isSourceMaps()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_SOURCEMAPS);
    }

    /**
     * Enable debug wrapping
     *
     * @return boolean
     */
    public function isDebugActive()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_DEBUG);
    }
}
