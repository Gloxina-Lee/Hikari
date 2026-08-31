let typedInstance: import('typed.js').default
export function disableTypedJsIfExist() {
    if (typedInstance) {
        typedInstance.destroy()
        typedInstance = null
    }
}
export default async function initTypedJs() {
    const json = document.getElementById('typed-js-initial')
    if (json) {
        disableTypedJsIfExist() // Fix mirai-mamori/Sakurairo #810
        try {
            const options = JSON.parse(json.innerHTML)
            const element = document.querySelector<HTMLElement>('.element')
            element.innerText = ''
            const { default: Typed } = await import('typed.js')
            typedInstance = new Typed(element, options)
        } catch (e) {
            console.error("请检查typed.js设置", e)
        }
    }
}
