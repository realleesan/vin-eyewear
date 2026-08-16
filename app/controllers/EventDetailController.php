<?php

/**
 * EventDetailController — chi tiết sự kiện (/su-kien/{slug}).
 *
 * Port từ src/routes/su-kien.$slug.tsx.
 */

class EventDetailController extends BaseController
{
    /**
     * @param string $slug lấy từ route 'su-kien/{slug}'
     */
    public function show(string $slug = ''): void
    {
        $event = $slug === '' ? null : EventModel::findVisibleBySlug($slug);

        if ($event === null) {
            http_response_code(404);
            (new ErrorController())->notFound();
            return;
        }

        $this->renderView('event/detail', [
            'pageTitle' => $event['title'] . ' — Vin Eyewear',
            'metaDesc'  => excerpt($event['excerpt'] ?? $event['content'] ?? '', 155),
            'event'     => $event,
            'others'    => EventModel::others($event['id'], 3),
        ]);
    }
}
