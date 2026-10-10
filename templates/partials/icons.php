<?php
/** Static inline SVG icons: trusted constants only, never built from user input. */

if (!function_exists('icon')) {
    function icon(string $name): string
    {
        static $icons = [
            'printer' => '<svg class="icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M7 3h10v5H7zM5 9h14a2 2 0 0 1 2 2v6h-4v4H7v-4H3v-6a2 2 0 0 1 2-2zm4 6v4h6v-4z" fill="currentColor"/></svg>',
            'heart' => '<svg class="icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M12 21s-8-5.3-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 5.7-8 11-8 11z" fill="currentColor"/></svg>',
            'bolt' => '<svg class="icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M13 2 4 14h6l-1 8 9-12h-6z" fill="currentColor"/></svg>',
            'bulb' => '<svg class="icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M12 2a7 7 0 0 0-4 12.7V18h8v-3.3A7 7 0 0 0 12 2zM9 20h6v2H9z" fill="currentColor"/></svg>',
            'check-all' => '<svg class="icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M2 12.5 3.4 11l3.1 3.1L13 7.6l1.4 1.4-8 8zm9 2.5 1.4-1.4 1.1 1.1 6.1-6.1 1.4 1.4-7.5 7.5z" fill="currentColor"/></svg>',
            'trash' => '<svg class="icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M9 3h6l1 2h4v2H4V5h4zM6 9h12l-1 12H7z" fill="currentColor"/></svg>',
            'compare' => '<svg class="icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M8 3 3 8l5 5V9h8V7H8zm8 8v4H8v2h8v4l5-5z" fill="currentColor"/></svg>',
            'x' => '<svg class="icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="m6.4 5 5.6 5.6L17.6 5 19 6.4 13.4 12l5.6 5.6-1.4 1.4-5.6-5.6L6.4 19 5 17.6l5.6-5.6L5 6.4z" fill="currentColor"/></svg>',
        ];
        return $icons[$name] ?? '';
    }
}
