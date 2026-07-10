<script setup lang="ts">
import type { Color } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import {
  ColorAreaArea,
  ColorAreaRoot,
  ColorAreaThumb,
  ColorFieldInput,
  ColorFieldRoot,
  ColorSliderRoot,
  ColorSliderThumb,
  ColorSliderTrack,
  ColorSwatch,
  colorToString,
  normalizeColor,
  PopoverContent,
  PopoverPortal,
  PopoverRoot,
  PopoverTrigger,
} from 'reka-ui'
import { computed, ref, watch } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  defaultValue?: string
  modelValue?: string
  class?: HTMLAttributes['class']
}>()

const emits = defineEmits<{
  (e: 'update:modelValue', payload: string): void
}>()

const DEFAULT_COLOR = '#3b82f6'

function toColorObj(value: string | undefined | null): Color {
  if (!value || value.trim() === '') {
    return normalizeColor(DEFAULT_COLOR)
  }

  try {
    return normalizeColor(value)
  } catch {
    return normalizeColor(DEFAULT_COLOR)
  }
}

const colorObj = ref<Color>(toColorObj(props.modelValue))
const hexColor = computed(() => colorToString(colorObj.value, 'hex'))

watch(
  () => props.modelValue,
  (newValue) => {
    const newColorObj = toColorObj(newValue)
    if (colorToString(newColorObj, 'hex') !== hexColor.value) {
      colorObj.value = newColorObj
    }
  },
)

function handleColorUpdate(newColor: Color) {
  colorObj.value = newColor
  emits('update:modelValue', colorToString(newColor, 'hex'))
}

function handleHexUpdate(hex: string) {
  try {
    colorObj.value = normalizeColor(hex)
    emits('update:modelValue', hex)
  } catch {
    // Invalid hex input — ignore
  }
}
</script>

<template>
  <PopoverRoot>
    <PopoverTrigger
      as-child
      :class="cn(
        'border-input ring-offset-background focus-visible:ring-ring focus-visible:ring-2 focus-visible:ring-offset-2 inline-flex h-9 w-9 items-center justify-center rounded-md border transition-colors hover:opacity-80 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50',
        props.class,
      )"
    >
      <button type="button">
        <ColorSwatch
          :color="hexColor"
          class="size-6 rounded-sm"
          :style="{ backgroundColor: 'var(--reka-color-swatch-color)' }"
        />
      </button>
    </PopoverTrigger>

    <PopoverPortal>
      <PopoverContent
        side="bottom"
        :side-offset="8"
        class="rounded-lg p-4 w-[280px] bg-popover border border-border shadow-md z-50"
      >
        <div class="flex flex-col gap-4">
          <div class="flex items-center gap-3">
            <ColorSwatch
              :color="hexColor"
              class="w-8 h-8 rounded-md border border-white/10"
              :style="{ backgroundColor: 'var(--reka-color-swatch-color)' }"
            />
            <div class="flex flex-col">
              <span class="text-sm font-medium">Color</span>
              <code class="text-xs text-muted-foreground">{{ hexColor }}</code>
            </div>
          </div>

          <ColorAreaRoot
            v-slot="{ style }"
            :model-value="colorObj"
            color-space="hsl"
            x-channel="saturation"
            y-channel="lightness"
            class="relative"
            @update:color="handleColorUpdate"
          >
            <ColorAreaArea
              class="relative w-full h-[140px] rounded-md overflow-hidden focus:outline-none focus:ring-2 focus:ring-ring"
              :style="style"
            >
              <ColorAreaThumb class="block w-4 h-4 rounded-full bg-white border-2 border-white shadow-md cursor-pointer hover:scale-110 transition-transform" />
            </ColorAreaArea>
          </ColorAreaRoot>

          <div class="flex flex-col gap-2">
            <label class="text-xs font-medium text-muted-foreground">Hue</label>
            <ColorSliderRoot
              :model-value="colorObj"
              channel="hue"
              color-space="hsl"
              class="relative flex items-center w-full h-4"
              @update:color="handleColorUpdate"
            >
              <ColorSliderTrack class="relative flex-1 rounded-full h-2">
                <div class="absolute inset-0 rounded-full hue-gradient" />
              </ColorSliderTrack>
              <ColorSliderThumb class="block w-4 h-4 rounded-full bg-white border-2 border-white shadow-md cursor-pointer hover:scale-110 transition-transform focus:outline-none focus:ring-2 focus:ring-ring" />
            </ColorSliderRoot>
          </div>

          <div class="flex flex-col gap-2">
            <label class="text-xs font-medium text-muted-foreground">Hex</label>
            <ColorFieldRoot
              :model-value="hexColor"
              @update:model-value="handleHexUpdate"
            >
              <ColorFieldInput
                class="w-full px-2 py-1.5 text-sm border border-border bg-background rounded-md focus:outline-none focus:ring-2 focus:ring-ring focus:border-ring font-mono"
                placeholder="#000000"
              />
            </ColorFieldRoot>
          </div>
        </div>
      </PopoverContent>
    </PopoverPortal>
  </PopoverRoot>
</template>

<style scoped>
.hue-gradient {
  background: linear-gradient(to right, #ff0000 0%, #ffff00 17%, #00ff00 33%, #00ffff 50%, #0000ff 67%, #ff00ff 83%, #ff0000 100%);
}
</style>
