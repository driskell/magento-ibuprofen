<?php
/**
 * @copyright See LICENCE.md
 * @package   Driskell_Ibuprofen
 * @author    Jason Woods <devel@jasonwoods.me.uk>
 */

/**
 * Head block
 */
class Driskell_Ibuprofen_Block_Head extends Fishpig_Wordpress_Addon_OtherMedia_Block_Html_Head
{
    /**
     * Give the head some ibuprofen to relive the painful loading experience
     *
     * @return string
     */
    public function getCssJsHtml()
    {
        $config = Mage::getModel('driskell_ibuprofen/config');
        if (!$config->isActive()) {
            return parent::getCssJsHtml();
        }

        $shouldMergeJs = Mage::getStoreConfigFlag('dev/js/merge_files');
        $shouldMergeCss = Mage::getStoreConfigFlag('dev/css/merge_css_files');
        if (!$shouldMergeJs && !$shouldMergeCss) {
            // Nothing to do - everything is staying separate anyway
            return parent::getCssJsHtml();
        }

        if ($this->getNameInLayout() != 'head' || $this->getParentBlock()->getNameInLayout() != 'root') {
            if (!$config->isAllBlocks()) {
                // We haven't enabled non-default blocks so do default on those
                return parent::getCssJsHtml();
            }
        }

        // Start with just a bundle for default, and then the remaining into another
        // There are some options that generate more bundles, however
        $bundles = array(
            $this->getDefaultHandles(),
        );

        if ($config->isSeparatedController()) {
            // We want a separate bundle for controller specific stuff
            $request = Mage::app()->getRequest();
            if ($request) {
                $controllerHandle = strtolower(implode('_', array(
                    $request->getModuleName(),
                    $request->getControllerName(),
                    $request->getActionName()
                )));
                $bundles[] = array_merge($bundles[0], array($controllerHandle));
            }
        }

        $originalItems = $this->_data['items'];
        $processedHandles = array();
        $processedKeys = array();
        $handleList = array();
        $html = '';

        // Process each handle one by one generating the css/js/other for that handle
        // (Each iteration we build the handle list up, excluding already processed items)
        foreach ($bundles as $handleList) {
            if ($config->isDebugActive()) {
                $debugHandleList = implode(',', array_diff($handleList, $processedHandles));
                $startTime = microtime(true)*1000;
                $html .= '<!-- START IBUPROFEN FOR: ' . $debugHandleList . ' -->' . "\n";
            }

            $this->_data['items'] = $this->getUnprocessedHandleItems($handleList, $processedKeys);
            $html .= parent::getCssJsHtml();

            if ($config->isDebugActive()) {
                $duration = number_format(microtime(true)*1000 - $startTime, 0);
                $html .= '<!-- END PAIN RELIEF FOR: ' . $debugHandleList . ' (SPENT ' . $duration . 'ms) -->' . "\n";
            }

            $processedHandles = array_merge($processedHandles, $handleList);
        }

        // Retrieve remaining items from the original list
        $startTime = microtime(true)*1000;
        $remainingItems = array();
        foreach ($originalItems as $item) {
            if (array_key_exists($this->generateItemKey($item), $processedKeys)) {
                continue;
            }
            $remainingItems[] = $item;
        }

        if (!empty($remainingItems)) {
            $this->_data['items'] = $remainingItems;

            if ($config->isDebugActive()) {
                $debugHandleList = implode(',', array_diff($this->getLayout()->getUpdate()->getHandles(), $processedHandles));
                $html .= '<!-- START FINAL IBUPROFEN FOR: ' . $debugHandleList . ' -->' . "\n";
            }

            $html .= parent::getCssJsHtml();

            if ($config->isDebugActive()) {
                $duration = number_format(microtime(true)*1000 - $startTime, 0);
                $html .= '<!-- END FINAL PAIN RELIEF FOR: ' . $debugHandleList . ' (SPENT ' . $duration . 'ms) -->' . "\n";
            }
        }

        $this->_data['items'] = $originalItems;
        return $html;
    }

    /**
     * Return registered assets
     *
     * @return array
     */
    public function getAllAssets()
    {
        return $this->_data['items'];
    }

    /**
     * Return list of unprocessed items for given handle collection
     *
     * @param array $handles List of handles, call this function once for each set, adding a new handle each call
     * @param array $processedKeys Key array from last call
     * @return array
     */
    private function getUnprocessedHandleItems(array $handles, array &$processedKeys)
    {
        $itemList = $this->fetchHandleAssets($handles);
        $unprocessedItems = array();
        foreach ($itemList as $item) {
            if (!isset($item['name'])) {
                continue;
            }
            $key = $this->generateItemKey($item);
            if (array_key_exists($key, $processedKeys)) {
                continue;
            }
            $processedKeys[$key] = 1;
            $unprocessedItems[] = $item;
        }
        return $unprocessedItems;
    }

    /**
     * Simulate a fresh layout for a set of handles to fetch their asset elements
     *
     * @param string[] $handles The handles to load
     * @return array
     */
    private function fetchHandleAssets(array $handles)
    {
        $hemaview = Mage::getModel('driskell_ibuprofen/hemaview');
        return $hemaview->getAllAssets($this->getNameInLayout(), $handles);
    }

    /**
     * Get default handles from the active list this will include store and theme
     * and potentially others that appears on every page
     * We work from the given list in case something removed something
     * (Some logic here taken from the Varien_Action controller)
     *
     * @return string[]
     */
    private function getDefaultHandles()
    {
        $defaultHandles = array(
            'default',
            'STORE_'.Mage::app()->getStore()->getCode(),
        );

        $package = Mage::getSingleton('core/design_package');
        $defaultHandles[] = 'THEME_'.$package->getArea().'_'.$package->getPackageName().'_'.$package->getTheme('layout');

        // Make sure we're not including things that shouldn't be there
        $allHandles = $this->getLayout()->getUpdate()->getHandles();
        $defaultHandles = array_intersect($defaultHandles, $allHandles);

        // If nothing left, just pop the first as the default bundle
        if (empty($defaultHandles)) {
            $defaultHandles = $allHandles;
            return $defaultHandles[0];
        }

        return $defaultHandles;
    }

    /**
     * Generate unique key for an asset item
     *
     * @param array $item
     * @return string
     */
    private function generateItemKey(array $item)
    {
        return md5('ITEM_' . json_encode($item));
    }
}
