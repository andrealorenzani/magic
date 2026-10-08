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
        ];
        return $icons[$name] ?? '';
    }
}
