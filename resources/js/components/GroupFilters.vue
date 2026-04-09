<script setup>
// Filtros para Grupos (Products/Attributes)
import AppFilterBase from "@/components/AppFilterBase.vue";

const props = defineProps({
  searchQuery:    { type: String, default: "" },
  isStrictSearch: Boolean,
  loading:        { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:isStrictSearch",
  "clear",
  "add-group",
]);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :show-add="false"
    :show-advanced="true"
    search-placeholder="Buscar grupos o productos vinculados..."
    class="py-1 premium-filter"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
  >
    <!-- Barra Principal -->
    <template #search-extra>
      <div class="d-none d-sm-flex align-center h-100 ms-2">
        <VDivider vertical class="border-opacity-10 me-3" length="24" />
        <VSwitch
          :model-value="props.isStrictSearch"
          label="Búsqueda Inteligente"
          color="primary"
          density="compact"
          hide-details
          class="font-weight-bold text-xs"
          @update:model-value="emit('update:isStrictSearch', $event)"
        />
        <VTooltip activator="parent" location="top">Alternar entre búsqueda estricta y flexible</VTooltip>
      </div>
    </template>

    <template #actions-extra>
      <VBtn
        icon
        color="primary"
        variant="flat"
        size="38"
        class="ml-1 shadow-primary rounded-circle action-btn-hover"
        @click="emit('add-group')"
      >
        <VIcon icon="tabler-plus" size="22" />
        <VTooltip activator="parent" location="top">Crear Nuevo Grupo</VTooltip>
      </VBtn>
    </template>

    <template #advanced-filters>
      <VCol cols="12" sm="6" md="4" lg="3">
        <VCard variant="flat" class="pa-4 bg-var-theme-background rounded-lg border">
          <div class="d-flex align-center gap-3">
             <VIcon icon="tabler-info-circle" color="primary" />
             <div class="d-flex flex-column">
                <span class="text-xs font-weight-black text-uppercase">Tip de Búsqueda</span>
                <span class="text-super-xs text-disabled">Puedes buscar por ID del grupo, nombre o productos que contenga.</span>
             </div>
          </div>
        </VCard>
      </VCol>
    </template>
  </AppFilterBase>
</template>

<style scoped>
.premium-filter :deep(.v-field__input) {
  font-weight: 600 !important;
}

.shadow-primary {
  box-shadow: 0 4px 12px 0 rgba(var(--v-theme-primary), 0.3) !important;
}

.action-btn-hover {
  transition: transform 0.2s ease;
}

.action-btn-hover:hover {
  transform: scale(1.1);
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.03);
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}
</style>
