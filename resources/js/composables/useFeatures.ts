import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

export function useFeatures() {
    const page = usePage<PageProps>();
    const features = computed(() => page.props.tenantFeatures ?? []);
    const hasFeature = (slug: string) => features.value.includes(slug);
    return { hasFeature, features };
}
