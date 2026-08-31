import { loadCSS } from 'fg-loadcss';
import { slideToggle } from '../../common/util';
import math from './math';
declare namespace window {
    let jQuery: Function
    let $: Function
    let lightGallery: Function
}
function collapse() {
    //收缩、展开
    /* jQuery(document).ready(
    function(jQuery){
        jQuery('.collapseButton').click(function(){
        jQuery(this).parent().parent().find('.xContent').slideToggle('slow');
        });
        }) */
    const collapseButtons = document.getElementsByClassName('collapseButton') as HTMLCollectionOf<HTMLAnchorElement>
    if (collapseButtons.length > 0) {
        const collapseListener = (e: MouseEvent) => {
            slideToggle((e.currentTarget as HTMLAnchorElement).nextElementSibling);
        }
        for (const ele of collapseButtons) {
            ele.addEventListener("click", collapseListener)
        }
        // import('jquery').then(({ default: jQuery }) => {
        //     jQuery('.collapseButton').on("click", function () {
        //         jQuery(this).parent().parent().find('.xContent').slideToggle('slow');
        //     })
        // })
    }
}
let lightBoxCSS: HTMLLinkElement
const vendorBaseUrl = new URL('vendor/', _iro.theme_asset_base_url)
async function lightbox() {
    //init lightbox
    if (_iro.baguetteBox) {
        if (!lightBoxCSS) lightBoxCSS = loadCSS(new URL('baguettebox/baguetteBox.min.css', vendorBaseUrl).toString())
        //@ts-ignore
        const { default: baguetteBox } = await import('baguettebox.js')
        baguetteBox.run('.entry-content', {
            captions: function (element: HTMLElement) {
                return element.getElementsByTagName('img')[0].alt;
            },
            ignoreClass: 'fancybox',
        });
    } else if (_iro.fancybox) {
        if (!lightBoxCSS) lightBoxCSS = loadCSS(new URL('fancybox/jquery.fancybox.min.css', vendorBaseUrl).toString())
        if (!((window.jQuery instanceof Function) || (window.$ instanceof Function))) {
            //@ts-ignore
            const { default: jQuery } = await import('jquery')
            window.$ = jQuery
            window.jQuery = jQuery
        }
        //@ts-ignore
        await import('@fancyapps/fancybox')
    } else if (_iro.lightGallery) {
        const { default: initLightGallery } = await import('../lightGallery/import')
        initLightGallery()
    }
}

export default function article_attach() {
    collapse()
    lightbox()
    math()
}
