<script setup>
import { ref } from "vue";

const props = defineProps({
  searchQuery: [String, null],
  startDate: [String, null],
  endDate: [String, null],
  showBaselineButton: { type: Boolean, default: false },
  baselineLoading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:startDate",
  "update:endDate",
  "clear",
  "export",
  "register-baseline",
]);

const isExpanded = ref(false);
</script>

<template>
  <VCard class="mb-6 overflow-hidden">
    <VCardText class="pa-4">
      <VRow align="center" no-gutters class="gap-4">
        <VCol cols="12" md="6" lg="7">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por ID de Producto, nombre o laboratorio..."
            clearable
            prepend-inner-icon="tabler-search"
            density="compact"
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>

        <VSpacer class="d-none d-md-block" />

        <VCol cols="12" md="auto" class="d-flex gap-2 justify-end flex-wrap">
          <VBtn
            :color="isExpanded ? 'primary' : 'secondary'"
            variant="tonal"
            size="small"
            :append-icon="isExpanded ? 'tabler-chevron-up' : 'tabler-chevron-down'"
            @click="isExpanded = !isExpanded"
          >
            {{ isExpanded ? 'MENOS FILTROS' : 'MÁS FILTROS' }}
          </VBtn>

          <VMenu>
            <template #activator="{ props: menuProps }">
              <VBtn
                color="success"
                variant="flat"
                size="small"
                prepend-icon="tabler-download"
                v-bind="menuProps"
              >
                EXPORTAR
              </VBtn>
            </template>
            <VList density="compact">
              <VListItem @click="emit('export', 'xlsx')">
                <template #prepend>
                  <VIcon icon="tabler-file-spreadsheet" color="success" />
                </template>
                <VListItemTitle>Excel</VListItemTitle>
              </VListItem>
              <VListItem @click="emit('export', 'pdf')">
                <template #prepend>
                  <VIcon icon="tabler-file-type-pdf" color="error" />
                </template>
                <VListItemTitle>PDF</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </VCol>
      </VRow>
    </VCardText>

    <VExpandTransition>
      <div v-show="isExpanded">
        <VDivider />
        <VCardText class="bg-var-theme-background">
          <VRow>
            <VCol cols="12" sm="6">
              <AppDateTimePicker
                :model-value="props.startDate"
                label="Fecha Desde"
                placeholder="Seleccionar fecha"
                clearable
                density="compact"
                :config="{
                  altInput: true,
                  altFormat: 'Y-m-d',
                  dateFormat: 'Y-m-d',
                }"
                @update:model-value="emit('update:startDate', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <AppDateTimePicker
                :model-value="props.endDate"
                label="Fecha Hasta"
                placeholder="Seleccionar fecha"
                clearable
                density="compact"
                :config="{
                  altInput: true,
                  altFormat: 'Y-m-d',
                  dateFormat: 'Y-m-d',
                }"
                @update:model-value="emit('update:endDate', $event)"
              />
            </VCol>
          </VRow>

          <div class="d-flex align-center gap-4 mt-4 flex-wrap">
            <VBtn
              v-if="showBaselineButton"
              color="warning"
              variant="tonal"
              size="small"
              prepend-icon="tabler-adjustments"
              :loading="baselineLoading"
              @click="emit('register-baseline')"
            >
              Registrar ajuste inicial
            </VBtn>

            <VBtn 
              color="error" 
              variant="text" 
              size="small" 
              prepend-icon="tabler-filter-off"
              @click="emit('clear')"
            >
              LIMPIAR FILTROS
            </VBtn>
          </div>
        </VCardText>
      </div>
    </VExpandTransition>
  </VCard>
</template>

<style scoped>
.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.03);
}
.gap-2 { gap: 8px !important; }
.gap-4 { gap: 16px !important; }
</style>
