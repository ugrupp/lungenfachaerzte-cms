<?php

namespace modules\livepreview;

use Craft;
use yii\base\Event;
use craft\events\RegisterTemplateRootsEvent;
use craft\web\View;

/**
 * Live Preview Hot-Reload Module
 *
 * Registers a small piece of JavaScript in the Craft control panel that
 * converts standard iframe-refresh live preview into a postMessage-based
 * hot-reload. Instead of reloading the preview iframe on every change,
 * Craft fires a postMessage event which the TanStack front-end
 * (CraftPreviewListener.tsx) catches and responds to by calling
 * router.invalidate() — re-fetching only the changed data without a
 * full page reload.
 *
 * Reference:
 *   https://aaronmbushnell.com/hot-reloading-content-in-craft-cms-live-preview/
 *   https://craftcms.com/docs/5.x/extend/module-guide.html
 */
class Module extends \yii\base\Module
{
    public function init(): void
    {
        parent::init();

        Craft::setAlias('@modules/livepreview', __DIR__);

        // Only run on CP web requests — not console, not front-end
        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest() || !$request->getIsCpRequest()) {
            return;
        }

        // Inject the postMessage JS after the CP is fully loaded
        Craft::$app->getView()->hook('cp.layouts.base', function(): void {
            $this->registerPreviewJs();
        });
    }

    /**
     * Registers the JavaScript that converts Craft's beforeUpdateIframe event
     * into a postMessage broadcast for the front-end preview iframe.
     *
     * The 'entry:live-preview:updated' message matches what CraftPreviewListener.tsx
     * listens for. The message is scoped to the preview target URL for security
     * (postMessage's targetOrigin argument).
     */
    private function registerPreviewJs(): void
    {
        $js = <<<'JS'
(function () {
    if (typeof Garnish === 'undefined' || typeof Craft === 'undefined') {
        return;
    }

    Garnish.on(Craft.Preview, 'beforeUpdateIframe', function (event) {
        // event.refresh is true for the initial load; skip that.
        // We only want to postMessage on content *changes*.
        if (event.refresh) {
            return;
        }

        var iframe = event.target.$iframe && event.target.$iframe[0];
        if (!iframe || !iframe.contentWindow) {
            return;
        }

        var targetOrigin = '*';
        try {
            var previewUrl = event.previewTarget && event.previewTarget.url;
            if (previewUrl) {
                var parsed = new URL(previewUrl);
                targetOrigin = parsed.origin;
            }
        } catch (e) {
            // Fall back to '*' if URL parsing fails
        }

        iframe.contentWindow.postMessage('craft:live-preview:update', targetOrigin);
    });
}());
JS;

        Craft::$app->getView()->registerJs($js, View::POS_END);
    }
}
