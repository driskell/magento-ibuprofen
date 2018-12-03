<?php
/**
 * @copyright See LICENCE.md
 * @package   Driskell_Ibuprofen
 * @author    Jason Woods <devel@jasonwoods.me.uk>
 */

use MatthiasMullie\Minify;

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
        $this->init($srcFiles, $targetFile, $beforeMergeCallback);

        $config = Mage::getSingleton('driskell_ibuprofen/config');
        if ($this->targetType == 'js') {
            $minify = $config->getMinificationJs();
        } else if ($this->targetType == 'css') {
            $minify = $config->getMinificationCss();
        } else {
            $minify = false;
        }
        if (!$config->isSourceMaps() && !$minify) {
            return parent::_mergeFiles($srcFiles, $targetFile, $mustMerge, $beforeMergeCallback, $extensionsFilter);
        }

        if (file_exists($targetFile)) {
            $filemtime = filemtime($targetFile);
        } else {
            $filemtime = null;
        }

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
                $this->minify($targetFile);
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
                $this->minify($targetFile);
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
    private function init($srcFiles, $targetFile, $beforeMergeCallback)
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
    private function reset()
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
    private function writeSourceMap()
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
    private function minify($file)
    {
        switch ($this->targetType) {
            case 'js':
                $type = Mage::getSingleton('driskell_ibuprofen/config')->getMinificationJs();
                switch ($type) {
                    case 'uglifyjs':
                        $this->uglify($file, true);
                        break;
                    case 'uglifyjs-m':
                        $this->uglify($file, false);
                        break;
                    case 'minify':
                        $this->phpMinify($file, $this->targetType);
                        break;
                }
                break;
            case 'css':
                $type = Mage::getSingleton('driskell_ibuprofen/config')->getMinificationCss();
                switch ($type) {
                    case 'minify':
                        $this->phpMinify($file, $this->targetType);
                        break;
                }
                break;
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
    private function uglify($file, $compress)
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
     * Minify
     *
     * @param string $file Filename of file being merged
     * @param string $type Type of file (js or css)
     *
     * @return void
     */
    private function phpMinify($file, $type)
    {
        if ($type == 'js') {
            $minifier = new Minify\JS($file);
        } else if ($type == 'css') {
            $minifier = new Minify\CSS($file);
        } else {
            return;
        }

        $result = $minifier->minify();
        file_put_contents($file, $result, LOCK_EX);
        if ($config->isSourceMaps()) {
            // Not supported when using PHP minify
            unlink($file . '.map');
        }
    }

    /**
     * Get source map URL
     *
     * @param string $file File to get the URL for
     *
     * @return string
     */
    private function getSourceMapUrl($file)
    {
        return Mage::getBaseUrl('media', Mage::app()->getRequest()->isSecure()) .
            basename(dirname($file)) .
            '/' .
            basename($file) .
            '.map';
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
}
