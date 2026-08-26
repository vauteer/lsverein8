<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { buttonVariants } from '@/components/ui/button';
import type { PaginationLink } from '@/types';

const props = defineProps<{
    links: PaginationLink[];
}>();

type PageLink = PaginationLink & { page: number };

const prev = computed(() => props.links[0]);
const next = computed(() => props.links[props.links.length - 1]);

const numericLinks = computed<PageLink[]>(() =>
    props.links
        .slice(1, -1)
        .filter((link) => /^\d+$/.test(link.label))
        .map((link) => ({ ...link, page: Number(link.label) })),
);

/**
 * At most five numbers: the first page, the current one with a neighbour on
 * either side, and the last. Laravel hands over every page, which laid a row
 * of buttons across the whole width once a list ran long.
 *
 * A null in the result is a gap, rendered as an ellipsis. Ported from
 * lscraft5, along with the icon-only previous/next.
 */
const windowedPages = computed<(PageLink | null)[]>(() => {
    const links = numericLinks.value;

    if (links.length === 0) {
        return [];
    }

    const currentPage = links.find((link) => link.active)?.page ?? 1;
    const lastPage = links[links.length - 1].page;

    const wantedPages = [
        ...new Set([
            1,
            currentPage - 1,
            currentPage,
            currentPage + 1,
            lastPage,
        ]),
    ]
        .filter((page) => page >= 1 && page <= lastPage)
        .sort((a, b) => a - b);

    const result: (PageLink | null)[] = [];

    wantedPages.forEach((page, index) => {
        if (index > 0 && page - wantedPages[index - 1] > 1) {
            result.push(null);
        }

        const link = links.find((candidate) => candidate.page === page);

        if (link) {
            result.push(link);
        }
    });

    return result;
});
</script>

<template>
    <nav class="flex items-center gap-1" :aria-label="$t('Pagination')">
        <component
            :is="prev?.url ? Link : 'span'"
            :href="prev?.url ?? undefined"
            :class="[
                buttonVariants({ variant: 'outline', size: 'icon-sm' }),
                !prev?.url && 'pointer-events-none opacity-50',
            ]"
            :aria-disabled="!prev?.url ? true : undefined"
        >
            <span class="sr-only">{{ $t('Previous') }}</span>
            <ChevronLeft class="size-4" />
        </component>

        <template v-for="(link, index) in windowedPages" :key="index">
            <span
                v-if="!link"
                class="flex size-8 items-center justify-center text-sm text-muted-foreground"
            >
                &hellip;
            </span>
            <component
                :is="link.url ? Link : 'span'"
                v-else
                :href="link.url ?? undefined"
                :class="[
                    link.active
                        ? buttonVariants({
                              variant: 'secondary',
                              size: 'icon-sm',
                          })
                        : buttonVariants({
                              variant: 'outline',
                              size: 'icon-sm',
                          }),
                ]"
                :aria-current="link.active ? 'page' : undefined"
            >
                {{ link.label }}
            </component>
        </template>

        <component
            :is="next?.url ? Link : 'span'"
            :href="next?.url ?? undefined"
            :class="[
                buttonVariants({ variant: 'outline', size: 'icon-sm' }),
                !next?.url && 'pointer-events-none opacity-50',
            ]"
            :aria-disabled="!next?.url ? true : undefined"
        >
            <span class="sr-only">{{ $t('Next') }}</span>
            <ChevronRight class="size-4" />
        </component>
    </nav>
</template>
