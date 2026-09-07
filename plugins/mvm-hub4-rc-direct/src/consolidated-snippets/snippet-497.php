<?php
// Consolidated from production Code Snippet #497.
defined( 'ABSPATH' ) || exit;

add_action( 'wp_footer', static function () {
    if ( ! is_user_logged_in() || ( ! is_front_page() && ! is_home() ) ) { return; }
    $mine_url = esc_url( home_url( '/mijn-mierlo/' ) );
    $lokaal_url = esc_url( home_url( '/mierlokaal/' ) );
    ?>
    <style id="mvm-home-snel-naar-v5-css">
    [data-mvm-home-quick] > a,
    [data-mvm-home-quick] a,
    a[data-mvm-home-quick] {
        font-weight:700 !important;
    }
    </style>
    <script id="mvm-home-snel-naar-v5">
    (function(){
        var mineUrl = <?php echo wp_json_encode( $mine_url ); ?>;
        var lokaalUrl = <?php echo wp_json_encode( $lokaal_url ); ?>;
        var wanted = ['tijdlijn','berichten','groepen','forum','mijn profiel'];
        function norm(s){ return (s || '').replace(/\s+/g,' ').trim().toLocaleLowerCase('nl-NL'); }
        function clean(node){
            if (!node || node.nodeType !== 1) return;
            node.removeAttribute('id'); node.removeAttribute('aria-current');
            Array.prototype.forEach.call(node.querySelectorAll('[id]'), function(el){el.removeAttribute('id');});
            Array.prototype.forEach.call(node.querySelectorAll('[aria-current]'), function(el){el.removeAttribute('aria-current');});
        }
        function isWantedLink(a){ return wanted.indexOf(norm(a && a.textContent)) !== -1; }
        function findRoot(heading){
            var n = heading;
            for (var i=0; n && i<8; i++, n=n.parentElement){
                var links = n.querySelectorAll ? Array.prototype.slice.call(n.querySelectorAll('a')).filter(isWantedLink) : [];
                if (links.length >= 3) return n;
            }
            return null;
        }
        function findList(root){
            var first = Array.prototype.slice.call(root.querySelectorAll('a')).find(isWantedLink);
            if (!first) return null;
            var node = first;
            while (node && node !== root && node.parentElement){
                var list = node.parentElement;
                var count = Array.prototype.slice.call(list.children).filter(function(child){
                    if (!child.querySelectorAll) return false;
                    if (child.tagName === 'A' && isWantedLink(child)) return true;
                    return Array.prototype.slice.call(child.querySelectorAll('a')).some(isWantedLink);
                }).length;
                if (count >= 3) return {list:list, template:node};
                node = list;
            }
            return null;
        }
        function itemLink(item){
            if (!item) return null;
            return item.tagName === 'A' ? item : item.querySelector('a');
        }
        function directItemForLink(link,list){
            var n=link;
            while(n && n.parentElement !== list) n=n.parentElement;
            return n && n.parentElement === list ? n : null;
        }
        function makeItem(template,label,url,key){
            var item=template.cloneNode(true); clean(item);
            item.setAttribute('data-mvm-home-quick',key);
            var a=itemLink(item); if(!a) return null;
            a.href=url; a.textContent=label; clean(a);
            if (item.tagName === 'A') item.setAttribute('data-mvm-home-quick',key);
            Array.prototype.forEach.call(item.querySelectorAll('small,p,span'),function(el){
                if(!el.contains(a) && norm(el.textContent) && norm(el.textContent)!==norm(label)) el.remove();
            });
            return item;
        }
        function ensureItem(root,info,label,url,key,before){
            var existing = info.list.querySelector('[data-mvm-home-quick="'+key+'"]');
            if (!existing) {
                var link = Array.prototype.slice.call(root.querySelectorAll('a')).find(function(a){
                    return norm(a.textContent) === norm(label) || (a.getAttribute('href') || '').indexOf(url.replace(location.origin,'')) !== -1;
                });
                existing = directItemForLink(link,info.list);
            }
            if (!existing) existing=makeItem(info.template,label,url,key);
            if (!existing) return null;
            existing.setAttribute('data-mvm-home-quick',key);
            var a=itemLink(existing); if(a){a.href=url;a.textContent=label;clean(a);a.style.fontWeight='700';}
            if(before) info.list.insertBefore(existing,before); else info.list.insertBefore(existing,info.list.firstElementChild);
            return existing;
        }
        function run(){
            var headings=Array.prototype.slice.call(document.querySelectorAll('h1,h2,h3,h4,h5,h6')).filter(function(el){return norm(el.textContent)==='snel naar';});
            for(var i=0;i<headings.length;i++){
                var root=findRoot(headings[i]); if(!root) continue;
                var info=findList(root); if(!info) continue;
                var mine=ensureItem(root,info,'Mijn Mierlo',mineUrl,'mijn-mierlo',info.list.firstElementChild);
                if(!mine) continue;
                var afterMine=mine.nextSibling;
                ensureItem(root,info,'MierLokaal',lokaalUrl,'mierlokaal',afterMine);
                return true;
            }
            return false;
        }
        function start(){
            if(run()) return;
            var obs=new MutationObserver(function(){if(run()) obs.disconnect();});
            obs.observe(document.body,{childList:true,subtree:true});
            window.setTimeout(function(){obs.disconnect();run();},10000);
        }
        if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',start); else start();
    })();
    </script>
    <?php
}, 99 );