<?php

namespace modules\cachepurge;

use Craft;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\events\ModelEvent;
use modules\cachepurge\jobs\PurgeNetlifyCacheJob;
use yii\base\Event;

/**
 * Netlify Cache Purge Module
 *
 * Purges the entire Netlify CDN cache whenever a published entry is saved or
 * deleted. This ensures the ISR-cached pages on the frontend are immediately
 * invalidated after a CMS change, rather than waiting for stale-while-revalidate
 * to expire.
 *
 * Required environment variables:
 *   NETLIFY_SITE_ID   — found in Netlify UI → Project configuration → General → Project ID
 *   NETLIFY_PURGE_TOKEN — a Netlify personal access token (user settings → OAuth applications)
 */
class Module extends \yii\base\Module
{
    public function init(): void
    {
        parent::init();

        Craft::setAlias('@modules/cachepurge', __DIR__);

        // Run on web and console requests (e.g. queue workers saving entries)
        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest()) {
            return;
        }

        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_SAVE,
            function (ModelEvent $event) {
                /** @var Entry $entry */
                $entry = $event->sender;

                // Skip drafts, revisions, and disabled entries — only purge when
                // a live, published entry changes.
                if ($entry->getIsDraft() || $entry->getIsRevision() || !$entry->enabled) {
                    return;
                }

                $this->queuePurge();
            }
        );

        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_DELETE,
            function (Event $event) {
                /** @var Entry $entry */
                $entry = $event->sender;

                if ($entry->getIsDraft() || $entry->getIsRevision()) {
                    return;
                }

                $this->queuePurge();
            }
        );

        Event::on(
            GlobalSet::class,
            GlobalSet::EVENT_AFTER_SAVE,
            function (ModelEvent $event) {
                $this->queuePurge();
            }
        );
    }

    private function queuePurge(): void
    {
        Craft::$app->getQueue()->push(new PurgeNetlifyCacheJob());
    }
}
