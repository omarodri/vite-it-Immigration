import { useHead } from '@unhead/vue';
import { unref, computed } from 'vue';

let siteTitle = '';
let separator = '|';

export const usePageTitle = (pageTitle: any) =>
    useHead(
        computed(() => ({
            title: `${unref(pageTitle)} ${separator} ${siteTitle}`,
        }))
    );

export const useMeta = (data: any) => {
    return useHead({ ...data, title: `${data.title} | VITE-IT Immigration` });
};
