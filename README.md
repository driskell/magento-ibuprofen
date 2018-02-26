# Ibuprofen for Magento

Medicine for the migraines of Magento 1's JS/CSS concatenation.

## Overview

When enabling the native JS and CSS concatenation in Magento 1, bundles are created for all assets in the header of the page. However, this is performed on a page-by-page basis. If the assets in the header are identical across several pages, then the bundle referred to will be the same bundle. This takes advantage of browser caching. However, it is common for the assets within the header to differ from page to page. For example, the product page includes additional assets, the cart page, and the checkout page too. For each of these pages, with concatenation enabled, a completely different bundle containing these additional items is created. This forces the user to download the same JavaScript and CSS again in new, larger bundles, just to get the missing code. Not ideal.

Another great explanation, along with another, manual solution, can be found on the FishPig website: https://fishpig.co.uk/magento/optimisation/advanced-javascript-merging/

This extension provides a similar solution by automatically creating separate bundles for global assets used site-wide and those used on specific pages, and provides a few options to tweak the behaviour so you can get the most optimisation solution for your specific use-case.

## Installation

The recommended installation method is to use composer. Alternatively, you can just copy the files into your Magento installation as you would any other extension.

Add the following to your `composer.json` and then run `composer require driskell/magento-ibuprofen`.

```json
    "repositories": [
        ...
        {
            "type": "vcs",
            "url": "https://github.com/driskell/magento-ibuprofen"
        }
    ]
```

## Configuration

The following configuration options are currently available in System > Configuration > Driskell > Ibuprofen.

Option | Description
--- | ---
Enable | Does what it says on the tin. Won't actually do anything though unless you enable Magento's CSS or JS concatenation.
Enable for non-head blocks | Sometimes you may have other blocks in your custom theme using the `page/html_head` block class. This will enable Ibuprofen on all blocks that use that class, instead of just the default Magento one located in the layout directly under `root`. If you're not sure what this means, that's nothing to worry about, and you'll be absolutely fine to leave it disabled.
Separated controller action bundle | OK this one is super-advanced and for those with specific use-cases, and most users will not need this enabled. Essentially, the default behaviour is to make two bundles maximum per page. One will contain the site-wide bundle and the other a bundle specific to that page. In rare cases you might have lots of scripts for say, the product page, but also lots of scripts for say, different specific product types. Normally these all would appear in the second bundle, and if you have lots of scripts for the product page and only a tiny script for each product type, enabling this will pull those big product page scripts into a third bundle so the browser doesn't download them again and again. As mentioned, this is only needed in the most advanced cases.
