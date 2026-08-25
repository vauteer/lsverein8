<script setup lang="ts">
import type { AvatarImageProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { AvatarImage } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<AvatarImageProps & { class?: HTMLAttributes["class"] }>()

// The image is forced into a square box, and object-fit defaults to "fill",
// which stretches anything that is not already square. object-cover crops
// instead, which is what a photo wants. Merged through cn() so a caller can
// pass object-contain (club logos, where cropping would cut off the wordmark)
// and have tailwind-merge drop this one - class attribute order alone would
// not decide it, both utilities have the same specificity.
const delegatedProps = reactiveOmit(props, "class")
</script>

<template>
  <AvatarImage
    data-slot="avatar-image"
    v-bind="delegatedProps"
    :class="cn('aspect-square size-full object-cover', props.class)"
  >
    <slot />
  </AvatarImage>
</template>
