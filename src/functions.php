<?php

/**
 * Global functions for WpApp framework
 */

if ( ! function_exists( 'wp_app_head' ) ) {
    /**
     * Generate HTML head content for app templates
     * Similar to wp_head() but clean and without theme/plugin interference
     */
    function wp_app_head() {
        if ( function_exists( 'wp_head' ) ) {
            wp_head();
        } else {
            // Basic meta tags (fallback when WordPress is not present)
            echo '<meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '">' . "\n";
            echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";

            // CSRF token for AJAX requests
            echo '<meta name="csrf-token" content="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '">' . "\n";

            // Allow language attributes
            if ( function_exists( 'get_language_attributes' ) ) {
                echo '<meta name="language" content="' . esc_attr( get_language_attributes() ) . '">' . "\n";
            }
        }

        // Custom app head hook - allows components to add styles/scripts
        do_action( 'wp_app_head' );

        // Allow specific head content injection
        do_action( 'wp_app_head_meta' );
        do_action( 'wp_app_head_styles' );
        do_action( 'wp_app_head_scripts' );
    }
}

if ( ! function_exists( 'wp_app_body_open' ) ) {
    /**
     * Generate body open content for app templates
     * Similar to wp_body_open() but for apps
     */
    function wp_app_body_open() {
        // Include WordPress admin bar if showing
        if ( is_admin_bar_showing() ) {
            wp_admin_bar_render();
        }

        // Custom app body open hook
        do_action( 'wp_app_body_open' );
    }
}

if ( ! function_exists( 'wp_app_body_close' ) ) {
    /**
     * Generate body close content for app templates
     */
    function wp_app_body_close() {
        if ( function_exists( 'wp_footer' ) ) {
            wp_footer();
        }

        // Custom app body close hook
        do_action( 'wp_app_body_close' );
    }
}



if ( ! function_exists( 'wp_app_title' ) ) {
    /**
     * Generate page title for app pages
     */
    function wp_app_title( $title = '', $separator = '-' ) {
        $site_name = get_bloginfo( 'name' );

        if ( empty( $title ) ) {
            // Try to get title from current route
            global $wp_app_route;
            if ( isset( $wp_app_route['pattern'] ) ) {
                $title = ucwords( str_replace( [ '-', '_', '/' ], ' ', $wp_app_route['pattern'] ) );
            } else {
                $title = 'App';
            }
        }

        if ( $site_name ) {
            return esc_html( $title . ' ' . $separator . ' ' . $site_name );
        }

        return esc_html( $title );
    }
}

if ( ! function_exists( 'wp_app_language_attributes' ) ) {
    /**
     * Get language attributes for HTML tag
     */
    function wp_app_language_attributes() {
        $attributes = [];

        if ( function_exists( 'is_rtl' ) && is_rtl() ) {
            $attributes[] = 'dir="rtl"';
        }

        if ( function_exists( 'get_bloginfo' ) ) {
            $lang = get_bloginfo( 'language' );
            if ( $lang ) {
                $attributes[] = 'lang="' . esc_attr( $lang ) . '"';
            }
        }

        return implode( ' ', $attributes );
    }
}

if ( ! function_exists( 'wp_app_enqueue_style' ) ) {
    /**
     * Enqueue a style for app pages
     */
    function wp_app_enqueue_style( $handle, $src = '', $deps = [], $ver = false ) {
        add_action( 'wp_app_head_styles', function() use ( $handle, $src, $deps, $ver ) {
            if ( $src ) {
                $url = $src;
                if ( $ver ) {
                    $url .= '?ver=' . esc_attr( $ver );
                }
                echo '<link rel="stylesheet" id="' . esc_attr( $handle ) . '-css" href="' . esc_url( $url ) . '" type="text/css" media="all">' . "\n";
            }
        } );
    }
}

if ( ! function_exists( 'wp_app_enqueue_script' ) ) {
    /**
     * Enqueue a script for app pages
     */
    function wp_app_enqueue_script( $handle, $src = '', $deps = [], $ver = false, $in_footer = true ) {
        $hook = $in_footer ? 'wp_app_body_close' : 'wp_app_head_scripts';

        add_action( $hook, function() use ( $handle, $src, $deps, $ver ) {
            if ( $src ) {
                $url = $src;
                if ( $ver ) {
                    $url .= '?ver=' . esc_attr( $ver );
                }
                echo '<script id="' . esc_attr( $handle ) . '-js" src="' . esc_url( $url ) . '"></script>' . "\n";
            }
        } );
    }
}

if ( ! function_exists( 'wp_app_add_inline_style' ) ) {
    /**
     * Add inline CSS for app pages
     */
    function wp_app_add_inline_style( $handle, $css ) {
        add_action( 'wp_app_head_styles', function() use ( $handle, $css ) {
            echo '<style id="' . esc_attr( $handle ) . '-inline-css">' . "\n";
            echo $css . "\n";
            echo '</style>' . "\n";
        } );
    }
}

if ( ! function_exists( 'wp_app_add_inline_script' ) ) {
    /**
     * Add inline JavaScript for app pages
     */
    function wp_app_add_inline_script( $handle, $js, $in_footer = true ) {
        $hook = $in_footer ? 'wp_app_body_close' : 'wp_app_head_scripts';

        add_action( $hook, function() use ( $handle, $js ) {
            echo '<script id="' . esc_attr( $handle ) . '-inline-js">' . "\n";
            echo $js . "\n";
            echo '</script>' . "\n";
        } );
    }
}

if ( ! function_exists( 'wp_app_dequeue_theme_assets' ) ) {
    /**
     * Remove theme styles and scripts from app pages
     */
    function wp_app_dequeue_theme_assets() {
        global $wp_styles, $wp_scripts;

        // Only run on app pages
        if ( ! get_query_var( 'wp_app_request' ) ) {
            return;
        }

        if ( ! $wp_styles ) {
            return;
        }

        // Get all enqueued styles
        $enqueued_styles = $wp_styles->queue;

        // Whitelist of styles to keep (WordPress core and essential plugins)
        $keep_styles = [
            'admin-bar',
            'dashicons',
            'debug-bar',
            'query-monitor',
            'qm-',
        ];

        foreach ( $enqueued_styles as $handle ) {
            $should_keep = false;

            // Check if this style should be kept
            foreach ( $keep_styles as $keep ) {
                if ( $handle === $keep || strpos( $handle, $keep ) === 0 ) {
                    $should_keep = true;
                    break;
                }
            }

            // Dequeue if not in whitelist
            if ( ! $should_keep ) {
                wp_dequeue_style( $handle );
            }
        }

        // Also dequeue scripts we don't need (but keep admin bar and debugging tools)
        if ( $wp_scripts ) {
            $enqueued_scripts = $wp_scripts->queue;

            $keep_scripts = [
                'admin-bar',
                'query-monitor',
                'qm-',
            ];

            foreach ( $enqueued_scripts as $handle ) {
                $should_keep = false;

                foreach ( $keep_scripts as $keep ) {
                    if ( $handle === $keep || strpos( $handle, $keep ) === 0 ) {
                        $should_keep = true;
                        break;
                    }
                }

                if ( ! $should_keep ) {
                    wp_dequeue_script( $handle );
                }
            }
        }
    }
}

// Hook into wp_enqueue_scripts to dequeue theme assets on app pages
if ( function_exists( 'add_action' ) ) {
    add_action( 'wp_enqueue_scripts', 'wp_app_dequeue_theme_assets', 999 );
}

if ( ! function_exists( 'wp_app_get_route_var' ) ) {
    /**
     * Get a route parameter value (WordPress-style global function)
     *
     * @param string $var Parameter name
     * @param mixed $default Default value if parameter doesn't exist
     * @return mixed Parameter value
     */
    function wp_app_get_route_var( $var, $default = '' ) {
        return get_query_var( $var, $default );
    }
}