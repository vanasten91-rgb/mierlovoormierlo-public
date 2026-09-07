<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Keeps PeepSo's JavaScript login flow deterministic on MvM content surfaces.
 * A normal site login always returns to the MvM homepage instead of the news
 * article or event where the login started. Dedicated organizer/entrepreneur
 * portals provide their own explicit redirect targets outside this content fix.
 */
final class MvM_Hub4_PeepSo_Login_Redirect {
    private const ALLOWED_TYPES = array( 'post', 'event_listing', 'event_organizer', 'event_venue' );
    private static string $canonical = '';
    private static bool $buffer_started = false;

    public static function init(): void {
        add_action( 'template_redirect', array( __CLASS__, 'start_buffer' ), 0 );
        add_action( 'wp_footer', array( __CLASS__, 'render_fix' ), PHP_INT_MAX - 5 );
    }

    public static function start_buffer(): void {
        if (
            self::$buffer_started ||
            is_admin() ||
            wp_doing_ajax() ||
            is_feed() ||
            is_trackback() ||
            ! is_singular( self::ALLOWED_TYPES )
        ) {
            return;
        }

        $canonical = home_url( '/' );
        if ( ! is_string( $canonical ) || '' === $canonical ) {
            return;
        }

        self::$canonical      = esc_url_raw( $canonical );
        self::$buffer_started = true;
        ob_start( array( __CLASS__, 'rewrite_markup' ) );
    }

    public static function rewrite_markup( string $html ): string {
        if ( '' === self::$canonical || false === stripos( $html, 'redirect_to' ) ) {
            return $html;
        }

        $canonical = esc_attr( self::$canonical );
        $rewritten = preg_replace_callback(
            '~<input\b(?=[^>]*\bname\s*=\s*(["\'])redirect_to\1)[^>]*>~i',
            static function ( array $match ) use ( $canonical ): string {
                $tag   = $match[0];
                $count = 0;
                $tag_with_value = preg_replace_callback(
                    '~(\bvalue\s*=\s*)(["\'])(.*?)\2~is',
                    static fn ( array $value_match ): string => $value_match[1] . $value_match[2] . $canonical . $value_match[2],
                    $tag,
                    1,
                    $count
                );

                if ( $count > 0 && is_string( $tag_with_value ) ) {
                    return $tag_with_value;
                }

                $trimmed = rtrim( $tag );
                $ending  = str_ends_with( $trimmed, '/>' ) ? '/>' : '>';
                $body    = substr( $trimmed, 0, -strlen( $ending ) );

                return rtrim( $body ) . ' value="' . $canonical . '"' . $ending;
            },
            $html
        );

        return is_string( $rewritten ) ? $rewritten : $html;
    }

    public static function render_fix(): void {
        if ( is_admin() || wp_doing_ajax() || ! is_singular( self::ALLOWED_TYPES ) ) {
            return;
        }

        $canonical = self::$canonical;
        if ( '' === $canonical ) {
            $canonical = esc_url_raw( home_url( '/' ) );
        }
        if ( '' === $canonical ) {
            return;
        }
        ?>
        <script id="mvm-peepso-login-redirect-v1">
        (function(){'use strict';
            var canonical=<?php echo wp_json_encode( esc_url_raw( $canonical ) ); ?>;
            if(!canonical)return;
            var selector='.ps-js-form-login input[name="redirect_to"],form.psf-login input[name="redirect_to"]';
            function apply(root){
                var scope=root&&root.querySelectorAll?root:document;
                var inputs=[];
                if(root&&root.matches&&root.matches(selector))inputs.push(root);
                scope.querySelectorAll(selector).forEach(function(input){inputs.push(input);});
                inputs.forEach(function(input){
                    if(input.value!==canonical)input.value=canonical;
                    if(input.getAttribute('value')!==canonical)input.setAttribute('value',canonical);
                });
            }
            function refresh(event){
                var target=event&&event.target;
                var form=target&&target.closest?target.closest('.ps-js-form-login,form.psf-login'):null;
                if(form)apply(form);
            }
            apply(document);
            if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',function(){apply(document);},{once:true});}
            window.addEventListener('load',function(){apply(document);},{once:true});
            window.addEventListener('pageshow',function(){apply(document);});
            ['pointerdown','mousedown','touchstart','focusin','click','submit'].forEach(function(eventName){
                document.addEventListener(eventName,refresh,true);
            });
            if('MutationObserver'in window&&document.documentElement){
                var scheduled=false;
                var observer=new MutationObserver(function(mutations){
                    var relevant=mutations.some(function(mutation){
                        if(mutation.type==='attributes')return mutation.target.matches&&mutation.target.matches(selector);
                        return Array.prototype.some.call(mutation.addedNodes,function(node){
                            return node.nodeType===1&&((node.matches&&node.matches(selector))||(node.querySelector&&node.querySelector(selector)));
                        });
                    });
                    if(relevant&&!scheduled){
                        scheduled=true;
                        window.setTimeout(function(){scheduled=false;apply(document);},0);
                    }
                });
                observer.observe(document.documentElement,{subtree:true,childList:true,attributes:true,attributeFilter:['value']});
            }
            [250,900,1250,2000,4000].forEach(function(delay){window.setTimeout(function(){apply(document);},delay);});
            var attempts=0;
            var watchdog=window.setInterval(function(){
                apply(document);
                attempts+=1;
                if(attempts>=20)window.clearInterval(watchdog);
            },500);
        }());
        </script>
        <?php
    }
}
