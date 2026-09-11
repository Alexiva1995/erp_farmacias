<script setup>
import { computed, ref, watch } from "vue";
import AppTextField from "@core/components/app-form-elements/AppTextField.vue";
import AppAutocomplete from "@core/components/app-form-elements/AppAutocomplete.vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  group: { type: Object, default: () => ({ id: null, name: "", laboratory_ids: [] }) },
  laboratories: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "save"]);

const dialogVisible = computed({
  get: () => props.modelValue,
  set: (val) => emit("update:modelValue", val),
});

const localGroup = ref({ id: null, name: "", laboratory_ids: [] });

watch(
  () => props.group,
  (val) => {
    localGroup.value = {
      id: val?.id ?? null,
      name: val?.name ?? "",
      laboratory_ids: Array.isArray(val?.laboratory_ids) ? [...val.laboratory_ids] : [],
    };
  },
  { immediate: true, deep: true }
);

const handleSave = () => {
  emit("save", { ...localGroup.value });
};

const close = () => {
  dialogVisible.value = false;
};
</script>

<template>
  <VDialog v-model="dialogVisible" max-width="600">
    <VCard class="rounded-xl shadow-xl border-0 overflow-hidden">
      <!-- Cabecera Premium con gradiente -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" class="me-3 elevation-1" size="40">
            <VIcon icon="tabler-layers-intersect" size="24" color="primary" />
          </VAvatar>
          <div class="d-flex flex-column">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">Configurar Grupo</h2>
            <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold mt-1">Asignación de laboratorios</span>
          </div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="white" size="small" class="rounded-lg" @click="close" />
        </div>
      </VCardTitle>
      <VCardText class="pa-6">
        <AppTextField v-model="localGroup.name" label="Nombre del Grupo" class="mb-4" />
        <AppAutocomplete
          v-model="localGroup.laboratory_ids"
          :items="props.laboratories"
          item-title="name"
          item-value="id"
          label="Laboratorios"
          multiple chips closable-chips
        />
      </VCardText>
      <VDivider />
      <!-- Botones con distribución equitativa y diseño premium -->
      <VCardActions class="pa-4 bg-light border-t">
        <VRow no-gutters class="w-100">
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg text-button uppercase"
              @click="close"
            >
              Descartar
            </VBtn>
          </VCol>
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              size="large"
              block
              height="50"
              :loading="props.loading"
              :disabled="props.loading"
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              @click="handleSave"
            >
              Guardar
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.text-super-xs { font-size: 0.65rem !important; }
.header-gradient {
  background: var(--brand-gradient) !important;
}
.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}
.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
