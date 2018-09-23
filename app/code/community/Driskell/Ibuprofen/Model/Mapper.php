<?php
/**
 * @copyright See LICENCE.md
 * @package   Driskell_Ibuprofen
 * @author    Jason Woods <devel@jasonwoods.me.uk>
 */

/**
 * Source mapper
 */
class Driskell_Ibuprofen_Model_Mapper extends Mage_Core_Model_Design_Package
{
    /**
     * Merges files into one and saves it into DB (if DB file storage is on)
     *
     * @param array        $srcFiles            -
     * @param string|bool  $targetFile          File path to be written
     * @param bool         $mustMerge           -
     * @param callback     $beforeMergeCallback -
     * @param array|string $extensionsFilter    -
     *
     * @see Mage_Core_Helper_Data::mergeFiles()
     *
     * @return bool|string
     */
    protected function _mergeFiles(array $srcFiles, $targetFile = false,
        $mustMerge = false, $beforeMergeCallback = null, $extensionsFilter = array()
    ) {
        $config = Mage::getModel('driskell_ibuprofen/config');
        if (!$config->isSourceMaps()) {
            return parent::_mergeFiles($srcFiles, $targetFile, $mustMerge, $beforeMergeCallback, $extensionsFilter);
        }

        if (Mage::helper('core/file_storage_database')->checkDbUsage()) {
            if (!file_exists($targetFile)) {
                Mage::helper('core/file_storage_database')->saveFileToFilesystem($targetFile);
            }
            if (file_exists($targetFile)) {
                $filemtime = filemtime($targetFile);
            } else {
                $filemtime = null;
            }
            $this->initSourceMap($srcFiles, $targetFile, $beforeMergeCallback);
            $result = Mage::helper('core')->mergeFiles(
                $srcFiles,
                $targetFile,
                $mustMerge,
                array($this, 'beforeMergeCallback'),
                $extensionsFilter
            );
            if ($result && (filemtime($targetFile) > $filemtime)) {
                Mage::helper('core/file_storage_database')->saveFile($targetFile);
                if ($this->writeSourceMap()) {
                    Mage::helper('core/file_storage_database')->saveFile($targetFile . '.map');
                }
            }
        } else {
            $this->initSourceMap($srcFiles, $targetFile, $beforeMergeCallback);
            $result = Mage::helper('core')->mergeFiles(
                $srcFiles,
                $targetFile,
                $mustMerge,
                array($this, 'beforeMergeCallback'),
                $extensionsFilter
            );
            $this->writeSourceMap();
        }
        return $result;
    }

    /**
     * Initialise source map processing
     *
     * @param string[] $srcFiles            List of source files
     * @param string   $targetFile          Target filename
     * @param callback $beforeMergeCallback Merge callback
     *
     * @return void
     */
    protected function initSourceMap($srcFiles, $targetFile, $beforeMergeCallback)
    {
        $this->lastFile = count($srcFiles) ? $srcFiles[count($srcFiles) - 1] : null;
        $this->targetFile = $targetFile;
        $this->originalBeforeMergeCallback = $beforeMergeCallback;
        $this->currentSource = 0;
        $this->sourceLine = 0;
        $this->sourceColumn = 0;
        $this->sources = array();
    }

    /**
     * Write source map result
     *
     * @return bool
     */
    protected function writeSourceMap()
    {
        // Sanity check
        if (is_null($this->lastFile)) {
            return false;
        }

        $targetUrl = preg_replace('#^' . preg_quote(Mage::getBaseDir(), '/') . '#', '', $this->targetFile);
        $sourceMap = array(
            'version' => '3',
            'file' => $targetUrl,
            'sections' => $this->sources
        );

        file_put_contents($this->targetFile . '.map', json_encode($sourceMap), LOCK_EX);

        // Clear results
        $this->initSourceMap(null);
    }

    /**
     * Handle source mapping
     *
     * @param string $file     Filename of file being merged
     * @param string $contents Contents of file being merged
     *
     * @return string
     */
    public function beforeMergeCallback($file, $contents)
    {
        if ($this->originalBeforeMergeCallback && is_callable($this->originalBeforeMergeCallback)) {
            $contents = call_user_func($this->originalBeforeMergeCallback, $file, $contents);
        }

        $this->sources[$this->currentSource] = array(
            'offset' => array(
                'line' => $this->sourceLine,
                'column' => $this->sourceColumn,
            )
        );

        $contents = preg_replace_callback('#(?<=\r\n|\n|\r)\s*//(?:\\#|@) sourceMappingURL=([^ \r\n]*)\s*$#', array($this, 'replaceSourceMappingUrl'), $contents);
        $contentsLines = preg_split('#\r\n|\n|\r#', $contents);

        if (isset($this->sources[$this->currentSource]['url'])) {
            // Make sourceMappingURL absolute if it is relative
            $sourceMappingUrl = parse_url($this->sources[$this->currentSource]['url']);
            if (isset($sourceMappingUrl['path'])) {
                if (substr($sourceMappingUrl['path'], 0, 1) != '/') {
                    $this->sources[$this->currentSource]['url'] = dirname($file) . '/' . $sourceMappingUrl['path'];
                }
            }

            // Chrome does not support 'url' yet (I checked Chromium source) so load and embed
            // https://github.com/chromium/chromium/blob/master/third_party/blink/renderer/devtools/front_end/sdk/SourceMap.js#L386
            $sourceMapJson = file_get_contents($this->sources[$this->currentSource]['url']);
            $sourceMap = $sourceMapJson ? json_decode($sourceMapJson, true) : false;
            if ($sourceMap) {
                unset($this->sources[$this->currentSource]['url']);
                $this->sources[$this->currentSource]['map'] = $sourceMap;
            } else {
                $this->sources[$this->currentSource]['url'] = '';
            }
        }

        if (!isset($this->sources[$this->currentSource]['map'])) {
            $this->sources[$this->currentSource]['map'] = array(
                'version' => 3,
                'file' => 'x',
                'sources' => [preg_replace('#^' . preg_quote(Mage::getBaseDir(), '/') . '#', '', $file)],
                'names' => [],
                'mappings' => count($contentsLines) ? 'AAAA' . str_repeat(';AACA', count($contentsLines) - 1) : ''
            );
        }

        $this->sourceLine += count($contentsLines);
        $this->sourceColumn += strlen($contentsLines[count($contentsLines) - 1]);
        $this->currentSource++;

        // Append source mapping to last file
        if ($file == $this->lastFile) {
            $sourceMapUrl = Mage::getBaseUrl('media', Mage::app()->getRequest()->isSecure()) . basename(dirname($this->targetFile)) . '/' . basename($this->targetFile);
            $contents .= "\n//# sourceMappingURL=" . $sourceMapUrl . ".map\n";
        }

        return $contents;
    }

    /**
     * Register source map and replace with blank
     *
     * @param array $match Regex match
     *
     * @return string
     */
    public function replaceSourceMappingUrl($match)
    {
        $this->sources[$this->currentSource]['url'] = $match[1];
        return '';
    }
}
