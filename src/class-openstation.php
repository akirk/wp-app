<?php

namespace WpApp;

if ( class_exists( 'WpApp\Openstation' ) ) {
    return;
}

/**
 * OpenStation (formerly Desktop Mode) integration.
 *
 * Registers every wp-app as a desktop icon in the OpenStation shell and
 * renders app pages without the masterbar when they are loaded inside an
 * OpenStation window.
 *
 * @see https://wordpress.org/plugins/desktop-mode/
 */
class Openstation {
    private static $hooks_initialized = false;

    /**
     * Hook the integration once for all apps.
     */
    public static function init() {
        if ( self::$hooks_initialized ) {
            return;
        }
        self::$hooks_initialized = true;

        // Apps register on plugins_loaded / init; the shell's own registries
        // are ready by init 10, so register right after them.
        if ( did_action( 'init' ) || doing_action( 'init' ) ) {
            self::register_icons();
        } else {
            add_action( 'init', [ __CLASS__, 'register_icons' ], 20 );
        }

        add_filter( 'body_class', [ __CLASS__, 'add_body_class' ] );
        add_action( 'wp_app_before_render', [ __CLASS__, 'enqueue_iframe_bridge' ] );
    }

    /**
     * Reset hook state (used by tests).
     */
    public static function reset() {
        self::$hooks_initialized = false;
    }

    /**
     * Function prefix of the active shell plugin.
     *
     * @return string 'openstation', 'desktop_mode', or '' when neither is active.
     */
    public static function get_prefix() {
        if ( function_exists( 'openstation_register_icon' ) ) {
            return 'openstation';
        }
        if ( function_exists( 'desktop_mode_register_icon' ) ) {
            return 'desktop_mode';
        }
        return '';
    }

    /**
     * Whether the shell plugin is active.
     */
    public static function is_available() {
        return '' !== self::get_prefix();
    }

    /**
     * Query flag the shell uses to request a page without admin chrome.
     */
    public static function get_chromeless_flag() {
        return 'desktop_mode' === self::get_prefix() ? 'desktop_mode_chromeless' : 'openstation_chromeless';
    }

    /**
     * Whether the current request is rendered inside an OpenStation window.
     *
     * Delegates to the shell so its safeguards apply: the flag only counts
     * for users who enabled OpenStation, and same-origin iframe loads that
     * lost the flag are still detected via Sec-Fetch headers.
     */
    public static function is_chromeless_request() {
        if ( function_exists( 'openstation_is_chromeless_request' ) ) {
            return (bool) openstation_is_chromeless_request();
        }
        if ( function_exists( 'desktop_mode_is_chromeless_request' ) ) {
            return (bool) desktop_mode_is_chromeless_request();
        }
        return false;
    }

    /**
     * Register one desktop icon per app the current user can access.
     */
    public static function register_icons() {
        $prefix = self::get_prefix();
        if ( '' === $prefix ) {
            return;
        }

        $register_icon = $prefix . '_register_icon';
        $capabilities  = Registry::get_app_capabilities();
        $position      = 10;

        foreach ( Registry::get_app_metadata() as $app_path => $metadata ) {
            if ( isset( $metadata['openstation'] ) && false === $metadata['openstation'] ) {
                continue;
            }
            if ( ! Registry::can_user_access_app( $app_path ) ) {
                continue;
            }

            $args             = self::get_icon_args( $app_path, $metadata );
            $args['position'] = $position;
            $position        += 10;

            if ( ! empty( $capabilities[ $app_path ] ) && is_string( $capabilities[ $app_path ] ) ) {
                $args['capabilities'] = [ $capabilities[ $app_path ] ];
            }

            call_user_func( $register_icon, self::get_icon_id( $app_path ), $args );
        }
    }

    /**
     * Icon id for an app path; nested paths keep their segments distinct.
     *
     * @param string $app_path App URL path.
     * @return string
     */
    public static function get_icon_id( $app_path ) {
        return sanitize_key( str_replace( '/', '-', $app_path ) );
    }

    /**
     * Build the icon registration arguments for an app.
     *
     * @param string $app_path App URL path.
     * @param array  $metadata App metadata from the Registry.
     * @return array Arguments for openstation_register_icon().
     */
    public static function get_icon_args( $app_path, $metadata ) {
        $name = isset( $metadata['openstation'] ) && is_string( $metadata['openstation'] ) && '' !== $metadata['openstation']
            ? $metadata['openstation']
            : ( isset( $metadata['name'] ) ? (string) $metadata['name'] : $app_path );

        $url = isset( $metadata['url'] ) ? (string) $metadata['url'] : home_url( '/' . $app_path . '/' );

        $args = [
            'title' => $name,
            'url'   => add_query_arg( self::get_chromeless_flag(), '1', $url ),
        ];

        if ( ! empty( $metadata['dashicon'] ) ) {
            $args['icon'] = (string) $metadata['dashicon'];
        } elseif ( ! empty( $metadata['icon_url'] ) ) {
            $args['icon'] = (string) $metadata['icon_url'];
        } else {
            $args['icon_svg'] = self::build_letter_svg( $name );
        }

        return $args;
    }

    /**
     * Build a letter-badge SVG for apps without an icon.
     *
     * @param string $name App display name.
     * @return string SVG markup.
     */
    public static function build_letter_svg( $name ) {
        $name  = trim( wp_strip_all_tags( (string) $name ) );
        $words = preg_split( '/[\s_\-]+/u', $name, -1, PREG_SPLIT_NO_EMPTY );

        if ( empty( $words ) ) {
            $letters = '?';
        } elseif ( count( $words ) >= 2 ) {
            $letters = mb_strtoupper( mb_substr( $words[0], 0, 1 ) . mb_substr( $words[1], 0, 1 ) );
        } else {
            $letters = mb_strtoupper( mb_substr( $words[0], 0, 1 ) );
        }

        // djb2 hash folded to 32 bits, for a stable hue per app name.
        $hash = 5381;
        $key  = strtolower( $name );
        $len  = strlen( $key );
        for ( $i = 0; $i < $len; $i++ ) {
            $hash = ( ( $hash * 33 ) + ord( $key[ $i ] ) ) & 0xFFFFFFFF;
        }
        $hue = $hash % 360;

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">' .
            '<rect width="100" height="100" rx="22" ry="22" fill="hsl(%d, 55%%, 45%%)"/>' .
            '<text x="50" y="50" fill="#fff" text-anchor="middle" dominant-baseline="central" ' .
            'font-family="-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif" ' .
            'font-weight="600" font-size="%d">%s</text>' .
            '</svg>',
            $hue,
            mb_strlen( $letters ) > 1 ? 36 : 46,
            esc_html( $letters )
        );
    }

    /**
     * Mark chromeless app pages so styles can adapt.
     *
     * @param array $classes Body classes.
     * @return array
     */
    public static function add_body_class( $classes ) {
        if ( self::is_chromeless_request() ) {
            $classes[] = 'wp-app-chromeless';
        }
        return $classes;
    }

    /**
     * Load the shell's iframe bridge on chromeless app pages so the window
     * picks up title changes, theme colors, and link handling.
     */
    public static function enqueue_iframe_bridge() {
        if ( ! self::is_chromeless_request() || ! function_exists( 'wp_enqueue_script' ) ) {
            return;
        }
        if ( function_exists( 'wp_script_is' ) && wp_script_is( 'os-iframe-bridge', 'registered' ) ) {
            wp_enqueue_script( 'os-iframe-bridge' );
        }
    }
}
