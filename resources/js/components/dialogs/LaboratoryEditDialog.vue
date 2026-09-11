<script setup>
import { computed, ref, watch } from "vue";
import AppTextField from "@core/components/app-form-elements/AppTextField.vue";
import AppAutocomplete from "@core/components/app-form-elements/AppAutocomplete.vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  lab: { type: Object, default: () => ({ id: null, name: "", group_id: null }) },
  groups: { type: Array, default: () => [] },
  enableBrandGroups: { type: Boolean, default: false },
  isRestaurant: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "save"]);

const dialogVisible = computed({
  get: () => props.modelValue,
  set: (val) => emit("update:modelValue", val),
});

const localLab = ref({ id: null, name: "", group_id: null });

watch(
  () => props.lab,
  (val) => {
    localLab.value = { id: val?.id ?? null, name: val?.name ?? "", group_id: val?.group_id ?? null };
  },
  { immediate: true, deep: true }
);

const handleSave = () => {
  emit("save", { ...localLab.value });
};

const close = () => {
  dialogVisible.value = false;
};
</script>

<template>
  <VDialog v-model="dialogVisible" max-width="500">
    <VCard class="rounded-xl shadow-xl border-0 overflow-hidden">
      <!-- Cabecera Premium con gradiente -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" class="me-3 elevation-1" size="40">
            <VIcon :icon="props.isRestaurant ? 'tabler-tags' : 'tabler-flask'" size="24" color="primary" />
          </VAvatar>
          <div class="d-flex flex-column">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ localLab.id ? 'Editar' : 'Nueva' }} {{ props.isRestaurant ? 'Marca' : 'Laboratorio' }}
            </h2>
            <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold mt-1">
              {{ props.isRestaurant ? 'Información de la marca' : 'Información del fabricante' }}
            </span>
          </div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="white" size="small" class="rounded-lg" @click="close" />
        </div>
      </VCardTitle>
      <VCardText class="pa-6 pt-6">
        <VRow>
          <VCol cols="12">
            <p class="text-xs font-weight-black text-primary text-uppercase mb-2 ls-1">Datos Generales</p>
            <AppTextField
              v-model="localLab.name"
              :label="props.isRestaurant ? 'Nombre de la Marca' : 'Nombre del Laboratorio'"
              :placeholder="props.isRestaurant ? 'Ej: Nestlé' : 'Ej: Bayer'"
              persistent-placeholder
              class="mb-4"
            />
            <template v-if="props.enableBrandGroups">
              <p class="text-xs font-weight-black text-primary text-uppercase mb-2 ls-1">{{ props.isRestaurant ? 'Grupo de Marca' : 'Asignación Corporativa' }}</p>
              <AppAutocomplete
                v-model="localLab.group_id"
                :items="props.groups"
                item-title="name"
                item-value="id"
                :label="props.isRestaurant ? 'Grupo de Marca' : 'Grupo de Laboratorio'"
                placeholder="Seleccionar grupo..."
                clearable
                persistent-placeholder
              />
            </template>
          </VCol>
        </VRow>
      </VCardText>
      <VDivider />
      <!-- Botones con distribución equitativa y diseño premium -->
      <VCardActions class="pa-4 bg-light border-t">
        <VRow no-gutters class="w-100">
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="outlined"
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg text-button uppercase"
              @click="close"
            >
              Cancelar
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
              Guardar {{ localLab.id ? 'Cambios' : (props.isRestaurant ? 'Marca' : 'Laboratorio') }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.text-super-xs { font-size: 0.65rem !important; }
.text-xs { font-size: 0.75rem !important; }
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
