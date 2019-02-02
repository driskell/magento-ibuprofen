# Changelog

## 1.3.8

* Drop PHP 5.6/7.0/7.1 from README as they are EOL
* Fix incorrect sourceRoot of existing sourcemaps
* Upgrade terser to get patch for missing sourcemap sources (terser-js/terser#248)
* Various sourcemap generation improvements

## 1.3.7

* Execute permissions are now not required for node_modules terser binary during composer install
* Switch from modman to extra/map
* Sourcemaps producted by Terser now contain the original sources if present in the original source maps

## 1.3.6

* Fix prototype errors in checkout due to mangled prototype variables

## 1.3.5

* Update to latest composer-npm-bridge to fix initial install issues which meant node_modules wasn't copied to the Magento root

## 1.3.4

* Switch from UglifyJS to TerserJS which appears to be better and now the default in Webpack 4

## 1.3.3

* Fix system configuration minification options not appearing or working correctly

## 1.3.2

* Fix minification not working correctly due to referencing the wrong configuration in mapper

## 1.3.1

* Fix missing composer dependency for php-minify

## 1.3.0

* CSS minification with PHP Minify
* Can also use PHP Minify for JS but will not have source maps available - for cases where NodeJS is unavailable

## 1.2.1

* Fix PHP warning about parameter count

## 1.2.0

* Add UglifyJS minification

## 1.1.3

* Fix sourcemaps getting reset to empty file when block cache expires and Magento performs a merge which skips merge because files have not changed

## 1.1.2

* Fix line number mappings in sourcemaps

## 1.1.1

* Support CSS sourcemaps

## 1.1.0

* Add sourcemap generation
* Add image to introduction

## 1.0.2

* Fix extension not working on default installations due to head block extending a third-party class that won't exist

## 1.0.1

* Add missing modman and update README

## 1.0.0

* Initial release with JS merge per controller handle
