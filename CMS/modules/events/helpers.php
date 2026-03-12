<?php
// File: modules/events/helpers.php
require_once __DIR__ . '/../../includes/data.php';

if (!function_exists('events_data_paths')) {
    function events_data_paths(): array
    {
        $baseDir = __DIR__ . '/../../data';
        return [
            'events' => $baseDir . '/events.json',
            'orders' => $baseDir . '/event_orders.json',
            'categories' => $baseDir . '/event_categories.json',
            'forms' => $baseDir . '/event_forms.json',
        ];
    }
}

if (!function_exists('events_default_forms')) {
    function events_default_forms(): array
    {
        return [
            [
                'id' => 'evt_form_registration',
                'name' => 'Event registration',
            ],
            [
                'id' => 'evt_form_vip_rsvp',
                'name' => 'VIP RSVP',
            ],
            [
                'id' => 'evt_form_webinar',
                'name' => 'Webinar signup',
            ],
            [
                'id' => 'evt_form_waitlist',
                'name' => 'Waitlist request',
            ],
        ];
    }
}

if (!function_exists('events_ensure_storage')) {
    function events_ensure_storage(): void
    {
        $paths = events_data_paths();
        foreach ($paths as $key => $path) {
            if (!is_file($path)) {
                if ($key === 'forms') {
                    write_json_file($path, events_default_forms());
                    continue;
                }
                file_put_contents($path, "[]\n");
                continue;
            }
            if ($key === 'forms') {
                $forms = read_json_file($path);
                if (!is_array($forms) || empty($forms)) {
                    write_json_file($path, events_default_forms());
                }
            }
        }
    }
}

if (!function_exists('events_slugify')) {
    function events_slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
        $value = trim((string) $value, '-');
        if ($value === '') {
            return uniqid('category_', false);
        }
        return $value;
    }
}

if (!function_exists('events_unique_slug')) {
    function events_unique_slug(string $desired, array $categories, ?string $currentId = null): string
    {
        $slug = events_slugify($desired);
        $base = $slug;
        if ($base === '') {
            $base = uniqid('category_', false);
        }
        $slug = $base;
        $existing = [];
        foreach ($categories as $category) {
            if (!is_array($category)) {
                continue;
            }
            $id = (string) ($category['id'] ?? '');
            if ($currentId !== null && $id === $currentId) {
                continue;
            }
            $key = strtolower((string) ($category['slug'] ?? ''));
            if ($key !== '') {
                $existing[$key] = true;
            }
        }
        $candidate = strtolower($slug);
        $suffix = 2;
        while ($candidate === '' || isset($existing[$candidate])) {
            $slug = $base . '-' . $suffix;
            $candidate = strtolower($slug);
            $suffix++;
        }
        return $slug;
    }
}

if (!function_exists('events_read_events')) {
    function events_read_events(): array
    {
        events_ensure_storage();
        $paths = events_data_paths();
        $events = read_json_file($paths['events']);
        if (!is_array($events)) {
            return [];
        }
        return array_values(array_filter($events, static function ($item) {
            return is_array($item) && isset($item['id']);
        }));
    }
}

if (!function_exists('events_read_orders')) {
    function events_read_orders(): array
    {
        events_ensure_storage();
        $paths = events_data_paths();
        $orders = read_json_file($paths['orders']);
        if (!is_array($orders)) {
            return [];
        }
        return array_values(array_filter($orders, static function ($item) {
            return is_array($item) && isset($item['id']);
        }));
    }
}

if (!function_exists('events_read_categories')) {
    function events_read_categories(): array
    {
        events_ensure_storage();
        $paths = events_data_paths();
        $categories = read_json_file($paths['categories']);
        if (!is_array($categories)) {
            return [];
        }
        return events_sort_categories($categories);
    }
}

if (!function_exists('events_read_forms')) {
    function events_read_forms(): array
    {
        events_ensure_storage();
        $paths = events_data_paths();
        $forms = read_json_file($paths['forms']);
        if (!is_array($forms) || empty($forms)) {
            $forms = events_default_forms();
        }
        $normalized = [];
        foreach ($forms as $form) {
            if (!is_array($form)) {
                continue;
            }
            $id = (string) ($form['id'] ?? '');
            $name = trim((string) ($form['name'] ?? ''));
            if ($id === '' || $name === '') {
                continue;
            }
            $normalized[] = [
                'id' => $id,
                'name' => $name,
            ];
        }
        usort($normalized, static function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        return array_values($normalized);
    }
}

if (!function_exists('events_write_events')) {
    function events_write_events(array $events): bool
    {
        $paths = events_data_paths();
        return write_json_file($paths['events'], array_values($events));
    }
}

if (!function_exists('events_write_orders')) {
    function events_write_orders(array $orders): bool
    {
        $paths = events_data_paths();
        return write_json_file($paths['orders'], array_values($orders));
    }
}

if (!function_exists('events_sort_categories')) {
    function events_sort_categories(array $categories): array
    {
        $normalized = [];
        foreach ($categories as $category) {
            if (!is_array($category)) {
                continue;
            }
            $id = (string) ($category['id'] ?? '');
            $name = trim((string) ($category['name'] ?? ''));
            $slug = (string) ($category['slug'] ?? '');
            if ($id === '' || $name === '') {
                continue;
            }
            $normalized[] = [
                'id' => $id,
                'name' => $name,
                'slug' => $slug,
                'created_at' => $category['created_at'] ?? null,
                'updated_at' => $category['updated_at'] ?? null,
            ];
        }

        usort($normalized, static function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return array_values($normalized);
    }
}

if (!function_exists('events_write_categories')) {
    function events_write_categories(array $categories): bool
    {
        $paths = events_data_paths();
        return write_json_file($paths['categories'], events_sort_categories($categories));
    }
}

if (!function_exists('events_find_event')) {
    function events_find_event(array $events, $id): ?array
    {
        foreach ($events as $event) {
            if ((string) ($event['id'] ?? '') === (string) $id) {
                return $event;
            }
        }
        return null;
    }
}

if (!function_exists('events_find_order')) {
    function events_find_order(array $orders, $id): ?array
    {
        foreach ($orders as $order) {
            if ((string) ($order['id'] ?? '') === (string) $id) {
                return $order;
            }
        }
        return null;
    }
}

if (!function_exists('events_ticket_datetime_to_timestamp')) {
    function events_ticket_datetime_to_timestamp($value): ?int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (is_numeric($value)) {
            $numeric = (int) $value;
            return $numeric > 0 ? $numeric : null;
        }
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? null : $timestamp;
    }
}

if (!function_exists('events_normalize_ticket_datetime')) {
    function events_normalize_ticket_datetime($value): string
    {
        $timestamp = events_ticket_datetime_to_timestamp($value);
        if ($timestamp === null) {
            return '';
        }
        return gmdate('c', $timestamp);
    }
}

if (!function_exists('events_normalize_ticket')) {
    function events_normalize_ticket(array $ticket): array
    {
        $ticket['id'] = $ticket['id'] ?? uniqid('tkt_', true);
        $ticket['name'] = trim((string) ($ticket['name'] ?? ''));
        $ticket['price'] = (float) ($ticket['price'] ?? 0);
        $ticket['quantity'] = max(0, (int) ($ticket['quantity'] ?? 0));
        $ticket['enabled'] = filter_var($ticket['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $onSale = events_normalize_ticket_datetime($ticket['on_sale'] ?? '');
        $offSale = events_normalize_ticket_datetime($ticket['off_sale'] ?? '');
        if ($onSale !== '' && $offSale !== '' && strtotime($onSale) > strtotime($offSale)) {
            $offSale = $onSale;
        }
        $ticket['on_sale'] = $onSale;
        $ticket['off_sale'] = $offSale;
        return $ticket;
    }
}

if (!function_exists('events_ticket_is_available')) {
    function events_ticket_is_available(array $ticket, ?int $referenceTime = null): bool
    {
        if (empty($ticket['enabled'])) {
            return false;
        }
        $referenceTime = $referenceTime ?? time();
        $onSale = events_ticket_datetime_to_timestamp($ticket['on_sale'] ?? '');
        if ($onSale !== null && $referenceTime < $onSale) {
            return false;
        }
        $offSale = events_ticket_datetime_to_timestamp($ticket['off_sale'] ?? '');
        if ($offSale !== null && $referenceTime > $offSale) {
            return false;
        }
        return true;
    }
}

if (!function_exists('events_filter_category_ids')) {
    function events_filter_category_ids($categoryIds, array $categories): array
    {
        if (!is_array($categoryIds)) {
            return [];
        }
        $validIds = [];
        $known = [];
        foreach ($categories as $category) {
            if (!is_array($category)) {
                continue;
            }
            $id = (string) ($category['id'] ?? '');
            if ($id !== '') {
                $known[$id] = true;
            }
        }
        foreach ($categoryIds as $categoryId) {
            $categoryId = (string) $categoryId;
            if ($categoryId === '' || !isset($known[$categoryId])) {
                continue;
            }
            if (!in_array($categoryId, $validIds, true)) {
                $validIds[] = $categoryId;
            }
        }
        return $validIds;
    }
}

if (!function_exists('events_apply_schedule')) {
    function events_apply_schedule(array $event, ?int $referenceTime = null): array
    {
        $now = $referenceTime ?? time();
        $status = in_array($event['status'] ?? '', ['draft', 'published', 'ended'], true)
            ? (string) $event['status']
            : 'draft';

        $publishAtRaw = trim((string) ($event['publish_at'] ?? ''));
        $unpublishAtRaw = trim((string) ($event['unpublish_at'] ?? ''));

        $publishAt = $publishAtRaw !== '' ? strtotime($publishAtRaw) : false;
        $unpublishAt = $unpublishAtRaw !== '' ? strtotime($unpublishAtRaw) : false;

        if ($unpublishAt !== false && $unpublishAt <= $now) {
            $status = 'ended';
        } elseif ($publishAt !== false && $publishAt <= $now && $status !== 'ended') {
            $status = 'published';
        } elseif ($publishAt !== false && $publishAt > $now && $status === 'published') {
            $status = 'draft';
        }

        $event['publish_at'] = $publishAtRaw;
        $event['unpublish_at'] = $unpublishAtRaw;
        $event['status'] = $status;

        return $event;
    }
}

if (!function_exists('events_normalize_event')) {
    function events_normalize_event(array $event, array $categories = []): array
    {
        $now = gmdate('c');
        if (empty($event['id'])) {
            $event['id'] = uniqid('evt_', true);
            $event['created_at'] = $now;
        }
        $event['title'] = trim((string) ($event['title'] ?? 'Untitled Event'));
        $event['description'] = (string) ($event['description'] ?? '');
        $event['location'] = trim((string) ($event['location'] ?? ''));
        $event['image'] = trim((string) ($event['image'] ?? ''));
        $event['start'] = (string) ($event['start'] ?? '');
        $event['end'] = (string) ($event['end'] ?? '');
        $formId = isset($event['form_id']) ? trim((string) $event['form_id']) : '';
        $event['form_id'] = $formId !== '' ? $formId : '';
        $event['status'] = in_array($event['status'] ?? '', ['draft', 'published', 'ended'], true)
            ? (string) $event['status']
            : 'draft';

        $publishAt = trim((string) ($event['publish_at'] ?? ''));
        $publishAtTime = $publishAt !== '' ? strtotime($publishAt) : false;
        if ($publishAt !== '' && $publishAtTime === false) {
            $publishAt = '';
        }
        $event['publish_at'] = $publishAt;

        $unpublishAt = trim((string) ($event['unpublish_at'] ?? ''));
        $unpublishAtTime = $unpublishAt !== '' ? strtotime($unpublishAt) : false;
        if ($unpublishAt !== '' && $unpublishAtTime === false) {
            $unpublishAt = '';
        }
        $event['unpublish_at'] = $unpublishAt;

        $event = events_apply_schedule($event);

        $event['tickets'] = array_values(array_map('events_normalize_ticket', $event['tickets'] ?? []));
        $event['categories'] = events_filter_category_ids($event['categories'] ?? [], $categories);
        if (!isset($event['published_at']) && $event['status'] === 'published') {
            $event['published_at'] = $now;
        }
        $event['updated_at'] = $now;
        return $event;
    }
}

if (!function_exists('events_ticket_capacity')) {
    function events_ticket_capacity(array $event, bool $onlyEnabled = false): int
    {
        $tickets = $event['tickets'] ?? [];
        $capacity = 0;
        $referenceTime = time();
        foreach ($tickets as $ticket) {
            if (!is_array($ticket)) {
                continue;
            }
            if ($onlyEnabled && !events_ticket_is_available($ticket, $referenceTime)) {
                continue;
            }
            $capacity += max(0, (int) ($ticket['quantity'] ?? 0));
        }
        return $capacity;
    }
}

if (!function_exists('events_ticket_price_lookup')) {
    function events_ticket_price_lookup(array $event): array
    {
        $lookup = [];
        foreach ($event['tickets'] ?? [] as $ticket) {
            $ticketId = (string) ($ticket['id'] ?? '');
            if ($ticketId === '') {
                continue;
            }
            $lookup[$ticketId] = [
                'name' => (string) ($ticket['name'] ?? ''),
                'price' => (float) ($ticket['price'] ?? 0),
            ];
        }
        return $lookup;
    }
}

if (!function_exists('events_event_ticket_options')) {
    function events_event_ticket_options(?array $event): array
    {
        if (!is_array($event)) {
            return [];
        }
        $options = [];
        $referenceTime = time();
        foreach ($event['tickets'] ?? [] as $ticket) {
            if (!is_array($ticket)) {
                continue;
            }
            $ticketId = (string) ($ticket['id'] ?? '');
            if ($ticketId === '') {
                continue;
            }
            if (!events_ticket_is_available($ticket, $referenceTime)) {
                continue;
            }
            $options[] = [
                'ticket_id' => $ticketId,
                'name' => (string) ($ticket['name'] ?? 'Ticket'),
                'price' => (float) ($ticket['price'] ?? 0),
            ];
        }
        usort($options, static function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        return array_values($options);
    }
}

if (!function_exists('events_normalize_order_tickets')) {
    function events_normalize_order_tickets(array $tickets, array $events, string $eventId): array
    {
        $lookup = [];
        if ($eventId !== '') {
            $event = events_find_event($events, $eventId);
            if ($event) {
                $lookup = events_ticket_price_lookup($event);
            }
        }
        $normalized = [];
        foreach ($tickets as $ticket) {
            if (!is_array($ticket)) {
                continue;
            }
            $ticketId = (string) ($ticket['ticket_id'] ?? '');
            if ($ticketId === '') {
                continue;
            }
            $quantity = max(0, (int) ($ticket['quantity'] ?? 0));
            $price = isset($ticket['price']) ? (float) $ticket['price'] : ($lookup[$ticketId]['price'] ?? 0);
            if ($quantity === 0) {
                continue;
            }
            if (!isset($normalized[$ticketId])) {
                $normalized[$ticketId] = [
                    'ticket_id' => $ticketId,
                    'quantity' => $quantity,
                    'price' => $price,
                ];
            } else {
                $normalized[$ticketId]['quantity'] += $quantity;
                $normalized[$ticketId]['price'] = $price;
            }
        }
        return array_values($normalized);
    }
}

if (!function_exists('events_normalize_order')) {
    function events_normalize_order(array $order, array $events, ?array $original = null): array
    {
        $id = isset($order['id']) ? trim((string) $order['id']) : '';
        if ($id === '' && $original) {
            $id = (string) ($original['id'] ?? '');
        }
        $order['id'] = $id;
        $eventId = isset($order['event_id']) ? trim((string) $order['event_id']) : '';
        if ($eventId === '' && $original) {
            $eventId = (string) ($original['event_id'] ?? '');
        }
        $order['event_id'] = $eventId;
        $order['buyer_name'] = trim((string) ($order['buyer_name'] ?? ($original['buyer_name'] ?? '')));
        $order['buyer_email'] = trim((string) ($order['buyer_email'] ?? ($original['buyer_email'] ?? '')));
        $order['buyer_phone'] = trim((string) ($order['buyer_phone'] ?? ($original['buyer_phone'] ?? '')));
        $status = strtolower((string) ($order['status'] ?? ($original['status'] ?? 'paid')));
        $allowed = ['paid', 'pending', 'refunded'];
        if (!in_array($status, $allowed, true)) {
            $status = 'paid';
        }
        $order['status'] = $status;
        $orderedAt = $order['ordered_at'] ?? ($original['ordered_at'] ?? '');
        if ($orderedAt !== '') {
            $timestamp = strtotime((string) $orderedAt);
            if ($timestamp !== false) {
                $order['ordered_at'] = gmdate('c', $timestamp);
            } elseif ($original && isset($original['ordered_at'])) {
                $order['ordered_at'] = $original['ordered_at'];
            }
        } elseif ($original && isset($original['ordered_at'])) {
            $order['ordered_at'] = $original['ordered_at'];
        } else {
            $order['ordered_at'] = '';
        }

        $order['tickets'] = events_normalize_order_tickets($order['tickets'] ?? [], $events, $eventId);

        $amount = 0.0;
        foreach ($order['tickets'] as $ticket) {
            $amount += (float) $ticket['price'] * (int) $ticket['quantity'];
        }
        $order['amount'] = round($amount, 2);

        $now = gmdate('c');
        if ($original && isset($original['created_at'])) {
            $order['created_at'] = $original['created_at'];
        } elseif (empty($order['created_at'])) {
            $order['created_at'] = $now;
        }
        $order['updated_at'] = $now;

        return $order;
    }
}

if (!function_exists('events_order_line_items')) {
    function events_order_line_items(array $order, array $events): array
    {
        $event = events_find_event($events, $order['event_id'] ?? '');
        $lookup = $event ? events_ticket_price_lookup($event) : [];
        $lines = [];
        foreach ($order['tickets'] ?? [] as $ticket) {
            if (!is_array($ticket)) {
                continue;
            }
            $ticketId = (string) ($ticket['ticket_id'] ?? '');
            if ($ticketId === '') {
                continue;
            }
            $quantity = max(0, (int) ($ticket['quantity'] ?? 0));
            $price = (float) ($ticket['price'] ?? 0);
            if ($quantity === 0) {
                continue;
            }
            $name = $lookup[$ticketId]['name'] ?? ($ticket['name'] ?? 'Ticket');
            if ($price === 0 && isset($lookup[$ticketId]['price'])) {
                $price = (float) $lookup[$ticketId]['price'];
            }
            $lines[] = [
                'ticket_id' => $ticketId,
                'name' => $name,
                'price' => round($price, 2),
                'quantity' => $quantity,
                'subtotal' => round($price * $quantity, 2),
            ];
        }
        return $lines;
    }
}

if (!function_exists('events_order_summary')) {
    function events_order_summary(array $order, array $events): array
    {
        $lines = events_order_line_items($order, $events);
        $tickets = 0;
        $amount = 0.0;
        foreach ($lines as $line) {
            $tickets += (int) $line['quantity'];
            $amount += (float) $line['subtotal'];
        }
        $status = strtolower((string) ($order['status'] ?? 'paid'));
        $event = events_find_event($events, $order['event_id'] ?? '');
        return [
            'id' => (string) ($order['id'] ?? ''),
            'event_id' => (string) ($order['event_id'] ?? ''),
            'event' => $event['title'] ?? 'Event',
            'buyer_name' => (string) ($order['buyer_name'] ?? ''),
            'buyer_email' => (string) ($order['buyer_email'] ?? ''),
            'buyer_phone' => (string) ($order['buyer_phone'] ?? ''),
            'tickets' => $tickets,
            'amount' => round($amount, 2),
            'status' => $status,
            'ordered_at' => (string) ($order['ordered_at'] ?? ''),
            'line_items' => $lines,
        ];
    }
}

if (!function_exists('events_order_detail')) {
    function events_order_detail(array $order, array $events): array
    {
        $summary = events_order_summary($order, $events);
        $event = events_find_event($events, $summary['event_id']);
        $subtotal = (float) $summary['amount'];
        $isRefunded = $summary['status'] === 'refunded';
        $refunds = $isRefunded ? $subtotal : 0.0;
        return [
            'id' => $summary['id'],
            'event_id' => $summary['event_id'],
            'event' => [
                'id' => $event['id'] ?? '',
                'title' => $event['title'] ?? ($summary['event'] ?? 'Event'),
            ],
            'buyer_name' => $summary['buyer_name'],
            'buyer_email' => $summary['buyer_email'],
            'buyer_phone' => $summary['buyer_phone'],
            'status' => $summary['status'],
            'ordered_at' => $summary['ordered_at'],
            'line_items' => $summary['line_items'],
            'totals' => [
                'subtotal' => round($subtotal, 2),
                'refunds' => round($refunds, 2),
                'net' => round($subtotal - $refunds, 2),
            ],
            'available_tickets' => events_event_ticket_options($event),
        ];
    }
}

if (!function_exists('events_compute_sales')) {
    function events_compute_sales(array $events, array $orders): array
    {
        $salesByEvent = [];
        foreach ($events as $event) {
            $eventId = (string) ($event['id'] ?? '');
            if ($eventId === '') {
                continue;
            }
            $salesByEvent[$eventId] = [
                'tickets_sold' => 0,
                'revenue' => 0.0,
                'refunded' => 0.0,
                'orders' => 0,
                'pending_orders' => 0,
                'paid_orders' => 0,
                'paid_amount' => 0.0,
                'capacity' => events_ticket_capacity($event, true),
                'net_revenue' => 0.0,
                'average_order' => 0.0,
                'sell_through_rate' => 0.0,
            ];
        }
        foreach ($orders as $order) {
            $eventId = (string) ($order['event_id'] ?? '');
            if ($eventId === '' || !isset($salesByEvent[$eventId])) {
                continue;
            }
            $quantity = 0;
            $amount = (float) ($order['amount'] ?? 0);
            foreach (($order['tickets'] ?? []) as $ticket) {
                $quantity += max(0, (int) ($ticket['quantity'] ?? 0));
            }
            $status = strtolower((string) ($order['status'] ?? 'paid'));
            if ($status === 'refunded') {
                $salesByEvent[$eventId]['refunded'] += $amount;
            } else {
                $salesByEvent[$eventId]['tickets_sold'] += $quantity;
                $salesByEvent[$eventId]['revenue'] += $amount;
                $salesByEvent[$eventId]['orders'] += 1;
                if ($status === 'pending') {
                    $salesByEvent[$eventId]['pending_orders'] += 1;
                }
                if ($status === 'paid') {
                    $salesByEvent[$eventId]['paid_orders'] += 1;
                    $salesByEvent[$eventId]['paid_amount'] += $amount;
                }
            }
        }

        foreach ($salesByEvent as &$metrics) {
            $revenue = (float) ($metrics['revenue'] ?? 0.0);
            $refunded = (float) ($metrics['refunded'] ?? 0.0);
            $metrics['net_revenue'] = max(0.0, $revenue - $refunded);
            $paidOrders = (int) ($metrics['paid_orders'] ?? 0);
            $paidAmount = (float) ($metrics['paid_amount'] ?? 0.0);
            $metrics['average_order'] = $paidOrders > 0 ? $paidAmount / $paidOrders : 0.0;
            $capacity = max(0, (int) ($metrics['capacity'] ?? 0));
            $ticketsSold = max(0, (int) ($metrics['tickets_sold'] ?? 0));
            $metrics['sell_through_rate'] = $capacity > 0 ? min(1.0, $ticketsSold / $capacity) : 0.0;
            unset($metrics['paid_amount']);
        }
        unset($metrics);

        return $salesByEvent;
    }
}

if (!function_exists('events_filter_upcoming')) {
    function events_filter_upcoming(array $events): array
    {
        $now = time();
        $upcoming = array_filter($events, static function ($event) use ($now) {
            $start = isset($event['start']) ? strtotime((string) $event['start']) : false;
            return $start !== false && $start >= $now;
        });
        usort($upcoming, static function ($a, $b) {
            $aTime = isset($a['start']) ? strtotime((string) $a['start']) : 0;
            $bTime = isset($b['start']) ? strtotime((string) $b['start']) : 0;
            if ($aTime === $bTime) {
                return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
            }
            return $aTime <=> $bTime;
        });
        return array_values($upcoming);
    }
}

if (!function_exists('events_format_currency')) {
    function events_format_currency(float $value): string
    {
        return '$' . number_format($value, 2);
    }
}

if (!function_exists('events_default_roles')) {
    function events_default_roles(): array
    {
        return [
            [
                'role' => 'Admin',
                'description' => 'Full access to events, tickets, orders, and settings.',
            ],
            [
                'role' => 'Event Manager',
                'description' => 'Create and manage events, update tickets, and view sales.',
            ],
            [
                'role' => 'Viewer',
                'description' => 'Read-only access to dashboards and reports.',
            ],
        ];
    }
}
