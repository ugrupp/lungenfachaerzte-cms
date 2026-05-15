<?php

namespace modules\cachepurge;

use Craft;
use craft\elements\Asset;
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

        Event::on(
            Asset::class,
            Asset::EVENT_AFTER_SAVE,
            function (ModelEvent $event) {
                /** @var Asset $asset */
                $asset = $event->sender;

                if (!$this->assetIsUsed($asset)) {
                    return;
                }

                $this->queuePurge();
            }
        );

        Event::on(
            Asset::class,
            Asset::EVENT_AFTER_DELETE,
            function (Event $event) {
                $this->queuePurge();
            }
        );
    }

    /**
     * Returns true if the asset is referenced by at least one entry or global set.
     */
    private function assetIsUsed(Asset $asset): bool
    {
        return Entry::find()->relatedTo($asset)->exists()
            || GlobalSet::find()->relatedTo($asset)->exists();
    }

    /**
     * Debounced purge: pushes a delayed job only if one isn't already pending.
     * Multiple saves within the delay window collapse into a single Netlify API call.
     */
    private function queuePurge(): void
    {
        $cache    = Craft::$app->getCache();
        $cacheKey = 'cachepurge:netlify:pending';
        $delay    = 10; // seconds to wait before executing

        if ($cache->exists($cacheKey)) {
            // A purge job is already queued — nothing to do
            return;
        }

        // Reserve the slot; TTL is slightly longer than the delay so the cache
        // entry is still present when the job starts and can be cleaned up there.
        $cache->set($cacheKey, true, $delay + 60);

        Craft::$app->getQueue()->delay($delay)->push(new PurgeNetlifyCacheJob());
    }
}
