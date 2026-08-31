export default async function math() {
    if (_iro.theme_mathjax &&
        (
            document.getElementsByTagName('math').length > 0 ||
            document.querySelector('article > div.entry-content')?.textContent.match(/(?:\$|\\\(|\\\[|\\begin\{.*?})/)
        )
    ) {
        if (!('MathJax' in window)) {
            const mathjaxConfig = {
                tex: {
                    inlineMath: [['$', '$'], ['\\(', '\\)']]
                },
                startup: {
                    typeset: false,           // Perform initial typeset?
                },
                options: {
                    // The bundled context menu can lazily load speech-engine code from a public CDN.
                    // Disable it so math rendering remains self-contained.
                    enableMenu: false
                },
                chtml: {
                    fontURL: new URL('vendor/mathjax/fonts/', _iro.theme_asset_base_url).toString(),
                    mathmlSpacing: true// true for MathML spacing rules, false for TeX rules
                }
            }
            //@ts-ignore
            window.MathJax = mathjaxConfig
        }
        //@ts-ignore
        await import('mathjax/es5/tex-mml-chtml')
        //@ts-ignore
        window.MathJax.typesetPromise()
    }
}
