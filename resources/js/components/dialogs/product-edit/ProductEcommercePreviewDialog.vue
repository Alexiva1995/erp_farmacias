<script setup>
const props = defineProps({
  modelValue: { type: Boolean, required: true },
  imagePreviewUrl: { type: String, default: null },
});

const emit = defineEmits(["update:modelValue"]);

const closeDialog = () => {
  emit("update:modelValue", false);
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="340"
    scrollable
    @update:model-value="closeDialog"
  >
    <VCard class="rounded-xl overflow-hidden pa-0">
      <div
        class="d-flex align-center justify-space-between px-4 py-3"
        style="background: linear-gradient(135deg, rgb(var(--v-theme-secondary)) 0%, rgb(var(--v-theme-primary)) 100%);"
      >
        <span class="text-subtitle-2 font-weight-black text-white" style="letter-spacing: 1px;">VISTA PREVIA TIENDA</span>
        <VBtn
          icon="tabler-x"
          variant="text"
          color="white"
          size="small"
          density="compact"
          @click="closeDialog"
        />
      </div>
      <div style="aspect-ratio: 1; background: #f5f5f5; overflow: hidden;">
        <img
          v-if="props.imagePreviewUrl"
          :src="props.imagePreviewUrl"
          style="width: 100%; height: 100%; object-fit: cover; display: block;"
          alt="Vista previa"
        />
      </div>
      <div class="px-4 py-2 text-center">
        <span class="text-caption text-disabled" style="letter-spacing: 1px; font-size: 10px;">ASÍ SE VERÁ EN EL ECOMMERCE</span>
      </div>
    </VCard>
  </VDialog>
</template>
