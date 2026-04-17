<?php

namespace modules\cachepurge;

use Craft;
use craft\elements\Entry;
use craft\events\ModelEvent;
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

                $this->purgeNetlifyCache();
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

                $this->purgeNetlifyCache();
            }
        );
    }

    /**
     * Calls the Netlify purge API to invalidate the entire site cache.
     *
     * POST https://api.netlify.com/api/v1/purge
     * {"site_id": "<NETLIFY_SITE_ID>"}
     */
    private function purgeNetlifyCache(): void
    {
        $siteId = getenv('NETLIFY_SITE_ID');
        $token  = getenv('NETLIFY_PURGE_TOKEN');

        if (!$siteId || !$token) {
            Craft::warning(
                'Netlify cache purge skipped: NETLIFY_SITE_ID or NETLIFY_PURGE_TOKEN not set.',
                __METHOD__
            );
            return;
        }

        try {
            $client = Craft::createGuzzleClient();
            $client->post('https://api.netlify.com/api/v1/purge', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'json' => ['site_id' => $siteId],
                // Short timeout — fire-and-forget; the save should not block on this
                'timeout'         => 5,
                'connect_timeout' => 3,
            ]);

            Craft::info('Netlify CDN cache purged.', __METHOD__);
        } catch (\Throwable $e) {
            // Log but never throw — a failed purge must not break the CP save flow
            Craft::error('Netlify cache purge failed: ' . $e->getMessage(), __METHOD__);
        }
    }
}
