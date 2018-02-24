<?php
/**
 * @copyright See LICENCE.md
 * @package   Driskell_Ibuprofen
 * @author    Jason Woods <devel@jasonwoods.me.uk>
 */

/**
 * Hemaview analysis of the head
 */
class Driskell_Ibuprofen_Model_Hemaview extends Mage_Core_Model_Layout
{
    /**
     * Get all assets from head block of given handle set
     *
     * @param string $blockName Name of the block to process
     * @param array $handles
     * @return array
     */
    public function getAllAssets($blockName, array $handles)
    {
        $this->getUpdate()->load($handles);
        $this->generateXml();
        $this->generateBlocks();
        $head = $this->getBlock($blockName);
        if (!$head) {
            return array();
        }

        return $head->getAllAssets();
    }

    /**
     * Create layout blocks hierarchy from layout xml configuration
     * But exclude everything except the head block tree
     *
     * @param Mage_Core_Layout_Element|null $parent
     * @param bool $actionsOnly
     */
    public function generateBlocks($parent=null, $actionsOnly = false)
    {
        if (empty($parent)) {
            $parent = $this->getNode();
        }

        // Head block is always root block, so it should be here
        // We only process that block and it's actions
        foreach ($parent as $node) {
            $attributes = $node->attributes();
            if ((bool)$attributes->ignore) {
                continue;
            }

            if ($actionsOnly) {
                if ($node->getName() != 'action') {
                    continue;
                }
                $this->_generateAction($node, $parent);
                continue;
            }

            // Only process head block and parents
            if ($node->getName() != 'block' && $node->getName() != 'reference') {
                continue;
            }

            // Process head block, iterate only the actions within by flagging $actionsOnly
            if ((string)$node['name'] == 'head') {
                if ($node->getName() == 'block') {
                    $this->_generateBlock($node, $parent);
                }
                $this->generateBlocks($node, true);
            }

            // Process parent block of the head, only blocks no references
            if ((string)$node['name'] == 'root') {
                if ($node->getName() != 'block') {
                    continue;
                }
                $this->_generateBlock($node, $parent);
                $this->generateBlocks($node);
                continue;
            }
        }
    }
}
