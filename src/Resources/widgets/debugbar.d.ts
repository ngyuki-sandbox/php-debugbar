
interface PhpDebugBar {
    Widgets: any
    Widget: Widget
    $: typeof jQuery
}

interface Widget {
    extend(options: WidgetOptions): Widget
}

interface WidgetThis {
    $el: JQuery<HTMLElement>
    bindAttr(type: 'data', func: (this: WidgetThis, data: any) => void): void
}

interface WidgetOptions {
    className: string,
    render: (this: WidgetThis) => void
}

declare var PhpDebugBar: PhpDebugBar;
