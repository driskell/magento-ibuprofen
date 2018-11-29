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
        $config = Mage::getSingleton('driskell_ibuprofen/config');
        if (!$config->isSourceMaps() && !$config->getMinification()) {
            return parent::_mergeFiles($srcFiles, $targetFile, $mustMerge, $beforeMergeCallback, $extensionsFilter);
        }

        if (file_exists($targetFile)) {
            $filemtime = filemtime($targetFile);
        } else {
            $filemtime = null;
        }

        $this->init($srcFiles, $targetFile, $beforeMergeCallback);

        if (Mage::helper('core/file_storage_database')->checkDbUsage()) {
            if (!file_exists($targetFile)) {
                Mage::helper('core/file_storage_database')->saveFileToFilesystem($targetFile);
            }
            $result = Mage::helper('core')->mergeFiles(
                $srcFiles,
                $targetFile,
                $mustMerge,
                array($this, 'beforeMergeCallback'),
                $extensionsFilter
            );
            if ($result && (filemtime($targetFile) > $filemtime)) {
                // mergeFiles return true if no merge necessary, false on failure, and string data if merge was done
                $this->writeSourceMap();
                $this->uglify($targetFile);
                Mage::helper('core/file_storage_database')->saveFile($targetFile);
                if (file_exists($targetFile . '.map')) {
                    Mage::helper('core/file_storage_database')->saveFile($targetFile . '.map');
                }
            }
        } else {
            $result = Mage::helper('core')->mergeFiles(
                $srcFiles,
                $targetFile,
                $mustMerge,
                array($this, 'beforeMergeCallback'),
                $extensionsFilter
            );
            if ($result && (filemtime($targetFile) > $filemtime)) {
                $this->writeSourceMap();
                $this->uglify($targetFile);
            }
        }

        // Clear context
        $this->reset();

        return $result;
    }

    /**
     * Initialise processing
     *
     * @param string[] $srcFiles            List of source files
     * @param string   $targetFile          Target filename
     * @param callback $beforeMergeCallback Merge callback
     *
     * @return void
     */
    protected function init($srcFiles, $targetFile, $beforeMergeCallback)
    {
        $this->reset();
        $this->lastFile = count($srcFiles) ? $srcFiles[count($srcFiles) - 1] : null;
        $this->targetType = pathinfo($targetFile, PATHINFO_EXTENSION);
        $this->targetFile = $targetFile;
        $this->originalBeforeMergeCallback = $beforeMergeCallback;
    }

    /**
     * Reset processing
     * Frees memory since we will likely be a singleton
     *
     * @return void
     */
    protected function reset()
    {
        $this->lastFile = null;
        $this->targetType = null;
        $this->targetFile = null;
        $this->originalBeforeMergeCallback = null;
        $this->currentSource = 0;
        $this->sourceLine = 0;
        $this->sourceColumn = 0;
        $this->sources = array();
    }

    /**
     * Write source map result
     *
     * @return void
     */
    protected function writeSourceMap()
    {
        // Sanity check
        if (!Mage::getSingleton('driskell_ibuprofen/config')->isSourceMaps() || is_null($this->lastFile) || !in_array($this->targetType, array('js', 'css'))) {
            return;
        }

        // https://sourcemaps.info/spec.html
        $targetUrl = preg_replace('#^' . preg_quote(Mage::getBaseDir(), '/') . '#', '', $this->targetFile);
        $sourceMap = array(
            'version' => '3',
            'file' => $targetUrl,
            'sections' => $this->sources
        );

        file_put_contents($this->targetFile . '.map', json_encode($sourceMap), LOCK_EX);
    }

    /**
     * Minify
     *
     * @param string $file Filename of file being merged
     *
     * @return void
     */
    protected function minify($file)
    {
        $type = Mage::getSingleton('driskell_ibuprofen/config')->getMinification();
        if (!$type || $this->targetType !== 'js') {
            return;
        }

        if ($type == 'uglifyjs') {
            $this->uglify($file, true);
        } else if ($type = 'uglifyjs-m') {
            $this->uglify($file, false);
        }
    }

    /**
     * Uglify
     *
     * @param string $file Filename of file being merged
     * @param bool $compress Enable or disable UglifyJS compression
     *
     * @return void
     */
    protected function uglify($file, $compress)
    {
        $config = Mage::getSingleton('driskell_ibuprofen/config');
        $tmpFile = tempnam(sys_get_temp_dir(), 'uglifyjs');

        // This call is direct into the module as the .bin folder uses symlinks
        // and it is usual for modman to lose them during the copy to Magento root
        $command = dirname(dirname(__FILE__)) . DS . 'node_modules' . DS . 'uglify-js' . DS . 'bin' . DS . 'uglifyjs';
        chmod($command, 0755);

        $command .= ' ' . $file;
        $command .= ' --output ' . $tmpFile;

        if ($compress) {
            // Compress
            $command .= ' -c';
        }

        // Mangle
        $command .= ' -m';
        // Keep some important comments
        $command .= ' --comments "/^\**!|@preserve|@license|@cc_on/i"';

        if ($config->isSourceMaps()) {
            // Generate a source map using the original input sourcemap we have built
            $command .= ' --source-map "content=\'' . $file . '.map\',url=\'' . $this->getSourceMapUrl($file) . '\'"';
        }

        $fd = popen($command, 'r');
        $status = pclose($fd);
        if ($status !== 0) {
            return;
        }

        $result = file_get_contents($tmpFile);
        file_put_contents($file, $result, LOCK_EX);
        unlink($tmpFile);

        if ($config->isSourceMaps()) {
            $resultMap = file_get_contents($tmpFile . '.map');
            file_put_contents($file . '.map', $resultMap, LOCK_EX);
            unlink($tmpFile . '.map');
        }
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

        if (!Mage::getSingleton('driskell_ibuprofen/config')->isSourceMaps()) {
            return $contents;
        }

        $this->sources[$this->currentSource] = array(
            'offset' => array(
                'line' => $this->sourceLine,
                'column' => $this->sourceColumn,
            )
        );

        if ($this->targetType == 'css') {
            $contents = preg_replace_callback('#(?<=\r\n|\n|\r)\s*/\\*(?:\\#|@) sourceMappingURL=([^ \r\n]*)\s*\\*/$#', array($this, 'replaceSourceMappingUrl'), $contents);
        } else {
            $contents = preg_replace_callback('#(?<=\r\n|\n|\r)\s*//(?:\\#|@) sourceMappingURL=([^ \r\n]*)\s*$#', array($this, 'replaceSourceMappingUrl'), $contents);
        }
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

        $this->sourceLine += count($contentsLines) - 1;
        $this->sourceColumn += strlen($contentsLines[count($contentsLines) - 1]);
        $this->currentSource++;

        // Append source mapping to last file
        if ($file == $this->lastFile) {
            if ($this->targetType == 'css') {
                $contents .= "\n/*# sourceMappingURL=" . $this->getSourceMapUrl($this->targetFile) . "*/\n";
            } else {
                $contents .= "\n//# sourceMappingURL=" . $this->getSourceMapUrl($this->targetFile) . "\n";
            }
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

    /**
     * Get source map URL
     *
     * @param string $file File to get the URL for
     *
     * @return string
     */
    protected function getSourceMapUrl($file)
    {
        return Mage::getBaseUrl('media', Mage::app()->getRequest()->isSecure()) .
            basename(dirname($file)) .
            '/' .
            basename($file) .
            '.map';
    }
}
