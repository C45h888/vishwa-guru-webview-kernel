import Root from './button.svelte';

export type ButtonVariant =
    | 'default'
    | 'ghost'
    | 'outline'
    | 'secondary'
    | 'destructive'
    | 'link';

export type ButtonSize = 'default' | 'sm' | 'lg' | 'icon';

export { Root, Root as Button };
export default Root;
