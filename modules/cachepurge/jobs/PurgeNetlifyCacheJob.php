<?php

namespace modules\cachepurge\jobs;

use Craft;
use craft\queue\BaseJob;

class PurgeNetlifyCacheJob extends BaseJob
{
    public function execute($queue): void
    {
        // Clear the debounce flag so future saves can schedule a new purge.
        Craft::$app->getCache()->delete('cachepurge:netlify:pending');

        $siteId = getenv('NETLIFY_SITE_ID');
        $token  = getenv('NETLIFY_PURGE_TOKEN');

        if (!$siteId || !$token) {
            Craft::warning(
                'Netlify cache purge skipped: NETLIFY_SITE_ID or NETLIFY_PURGE_TOKEN not set.',
                __METHOD__
            );
            return;
        }

        $client = Craft::createGuzzleClient();
        $client->post('https://api.netlify.com/api/v1/purge', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'json'            => ['site_id' => $siteId],
            'timeout'         => 10,
            'connect_timeout' => 5,
        ]);

        Craft::info('Netlify CDN cache purged.', __METHOD__);
    }

    protected function defaultDescription(): ?string
    {
        return 'Frontend wird aktualisiert';
    }
}
