import { isMobile } from "./mobile"
/**
 * 根据滚动位置调整UI显示
 */
export default function scrollHandler() {
    const skinMenu = document.querySelector(".skin-menu")
    const changskin = document.querySelector<HTMLElement>("#changskin")
    const mb_to_top = document.querySelector<HTMLElement>("#moblieGoTop")
    const progressBar = document.getElementById('bar')
    const controls = [mb_to_top, changskin].filter(
        (element): element is HTMLElement => element !== null
    )
    let ticking = false
    let controlsVisible: boolean | null = null
    let lastProgress = -1

    const updateScrollUi = () => {
        const scrollTop = window.scrollY || document.documentElement.scrollTop || document.body.scrollTop
        const shouldShowControls = scrollTop > 20

        if (shouldShowControls !== controlsVisible) {
            const transform = shouldShowControls ? "scale(1)" : "scale(0)"
            controls.forEach(element => element.style.transform = transform)
            controlsVisible = shouldShowControls
        }

        if (skinMenu && skinMenu.classList.contains("show")) {
            skinMenu.classList.remove("show")
        }

        if (!isMobile() && progressBar) {
            const scrollableHeight = Math.max(document.documentElement.scrollHeight - window.innerHeight, 0)
            const progress = scrollableHeight > 0
                ? Math.min(100, Math.max(0, Math.round(scrollTop / scrollableHeight * 100)))
                : 0

            if (progress !== lastProgress) {
                progressBar.style.width = progress + '%'
                lastProgress = progress
            }
        }

        ticking = false
    }

    const scheduleScrollUiUpdate = () => {
        if (ticking) return
        ticking = true
        window.requestAnimationFrame(updateScrollUi)
    }

    window.addEventListener("scroll", scheduleScrollUiUpdate, { passive: true })
    window.addEventListener("resize", scheduleScrollUiUpdate, { passive: true })
    updateScrollUi()
}
//pjax.complete ready
/* function NH() {
    const header_thresold = 0,
        siteHeader = document.querySelector(".site-header")
    window.addEventListener("scroll", () => {
        const scrollTop = document.documentElement.scrollTop || window.pageYOffset;
        if (scrollTop > header_thresold) {
            siteHeader.classList.add("yya");
        } else {
            siteHeader.classList.remove("yya");
        }
    })
    //     $(window).scroll(function () {
    //         var s = $(document).scrollTop(),
    //             cached = $('.site-header');
    //         if (s == h1) {
    //             cached.removeClass('yya');
    //         }
    //         if (s > h1) {
    //             cached.addClass('yya');
    //         }
    // });
} */
//ready
/* function GT() {
    const mb_to_top = document.querySelector("#moblieGoTop"),
        changskin = document.querySelector("#changskin");
    window.addEventListener("scroll", debounce(() => {
        const scroll = document.documentElement.scrollTop || document.body.scrollTop;
        const cssText = scroll > 20 ? "scale(1)" : "scale(0)"
        mb_to_top.style.transform = cssText;
        changskin.style.transform = cssText;
    }))
    mb_to_top.onclick = topFunction
}

function topFunction() {
    window.scrollTo({
        top: 0,
        behavior: "smooth"
    });
} */
