<script setup lang="ts">
import { computed } from "vue";
import { SparklineItem } from "vuetify/lib/components/VSparkline/util/line.mjs";

const props = defineProps({
  color: {
    type: String,
    required: false,
    default: "primary",
  },
  stats: {
    type: Array<SparklineItem>,
    required: true,
  },
});

const sparkGradient = computed(() => [
  `rgb(var(--v-theme-${props.color}))`,
  "#ffffff",
]);
</script>

<template>
  <VSparkline
    padding="0"
    height="90px"
    line-width="2"
    :fill="true"
    :smooth="50"
    :gradient="sparkGradient"
    :model-value="props.stats"
  />
</template>
