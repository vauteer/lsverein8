<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: trans('Light') },
    { value: 'dark', Icon: Moon, label: trans('Dark') },
    { value: 'system', Icon: Monitor, label: trans('System') },
] as const;
</script>

<template>
    <!-- Theme tokens rather than fixed neutrals: this control was the last
    place in the app hardcoding colours, which is why it kept the starter
    kit's grey after the palette changed. bg-muted track, bg-background pill —
    the shadcn Tabs convention, so it follows any future palette on its own.
    In dark that makes the active pill darker than the track rather than
    lighter, because --muted is the lightest surface token there; the shadow
    and the foreground text carry it. -->
    <div class="inline-flex gap-1 rounded-lg bg-muted p-1">
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            @click="updateAppearance(value)"
            :class="[
                'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                appearance === value
                    ? 'bg-background text-foreground shadow-xs'
                    : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
            ]"
        >
            <component :is="Icon" class="-ml-1 h-4 w-4" />
            <span class="ml-1.5 text-sm">{{ label }}</span>
        </button>
    </div>
</template>
