<script setup>
import { ref } from 'vue';

const props = defineProps({
  buscador: String,
  tipo_identificacion_filtro: String,
  company_id_filtro: [String, null],
  client_type_filtro: [String, null],
  fechaHasta_filtro: [String, null],
  fechaDesde_filtro: [String, null],
  companies: { type: Array, default: () => [] },
});

const emit = defineEmits([
  "update:buscador",
  "update:tipo_identificacion_filtro",
  "update:company_id_filtro",
  "update:client_type_filtro",
  "update:fechaHasta_filtro",
  "update:fechaDesde_filtro",
  "clear",
  "add-client",
  "export-pdf",
  "export-excel",
]);

const isFiltersVisible = ref(false);

const clientTypeOptions = [
  { title: "VIP", value: "VIP" },
  { title: "Frecuente", value: "Frecuente" },
  { title: "Ocasional", value: "Ocasional" },
  { title: "En Riesgo", value: "En Riesgo" },
  { title: "Nuevo", value: "Nuevo" },
];
</script>

<template>
  <VCard class="mb-6">
    <VCardText class="pa-4">
      <VRow align="center">
        <VCol cols="12" md="4">
          <AppTextField
            :model-value="props.buscador"
            placeholder="Buscar por nombre, ID o teléfono..."
            prepend-inner-icon="tabler-search"
            clearable
            @update:model-value="emit('update:buscador', $event)"
          />
        </VCol>
        
        <VCol cols="12" md="8" class="d-flex justify-md-end gap-2">
          <VBtn
            :color="isFiltersVisible ? 'primary' : 'secondary'"
            variant="tonal"
            prepend-icon="tabler-filter"
            @click="isFiltersVisible = !isFiltersVisible"
          >
            Filtros {{ isFiltersVisible ? 'Ocultar' : 'Mostrar' }}
          </VBtn>
          <VBtn
            color="primary"
            prepend-icon="tabler-plus"
            @click="emit('add-client')"
          >
            Nuevo Cliente
          </VBtn>
        </VCol>
      </VRow>

      <VExpandTransition>
        <div v-show="isFiltersVisible">
          <VDivider class="my-4" />
          <VRow>
            <VCol cols="12" sm="6" md="3">
              <VSelect
                :model-value="props.tipo_identificacion_filtro"
                label="Tipo ID"
                :items="['V-', 'J-', 'G-', 'E-']"
                variant="outlined"
                density="compact"
                clearable
                @update:model-value="emit('update:tipo_identificacion_filtro', $event)"
              />
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <VSelect
                :model-value="props.company_id_filtro"
                label="Empresa"
                :items="props.companies"
                item-title="name"
                item-value="id"
                variant="outlined"
                density="compact"
                clearable
                @update:model-value="emit('update:company_id_filtro', $event)"
              />
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <VSelect
                :model-value="props.client_type_filtro"
                label="Tipo de Cliente"
                :items="clientTypeOptions"
                variant="outlined"
                density="compact"
                clearable
                @update:model-value="emit('update:client_type_filtro', $event)"
              />
            </VCol>
            <VCol cols="12" sm="6" md="3" class="d-flex align-center">
              <VBtn 
                color="secondary" 
                variant="text" 
                size="small" 
                prepend-icon="tabler-refresh"
                @click="emit('clear')"
              >
                Resetear Filtros
              </VBtn>
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <AppDateTimePicker
                :model-value="props.fechaDesde_filtro"
                label="Fecha Desde"
                placeholder="Seleccionar"
                clearable
                @update:model-value="emit('update:fechaDesde_filtro', $event)"
              />
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <AppDateTimePicker
                :model-value="props.fechaHasta_filtro"
                label="Fecha Hasta"
                placeholder="Seleccionar"
                clearable
                @update:model-value="emit('update:fechaHasta_filtro', $event)"
              />
            </VCol>
            
            <VCol cols="12" class="d-flex justify-end pt-2">
              <VMenu>
                <template #activator="{ props: menuProps }">
                  <VBtn
                    color="success"
                    variant="tonal"
                    prepend-icon="tabler-download"
                    v-bind="menuProps"
                  >
                    Exportar
                  </VBtn>
                </template>
                <VList>
                  <VListItem @click="emit('export-excel', 'xlsx')">
                    <template #prepend>
                      <VIcon icon="tabler-file-spreadsheet" color="success" />
                    </template>
                    <VListItemTitle>Excel</VListItemTitle>
                  </VListItem>
                  <VListItem @click="emit('export-pdf')">
                    <template #prepend>
                      <VIcon icon="tabler-file-type-pdf" color="error" />
                    </template>
                    <VListItemTitle>PDF</VListItemTitle>
                  </VListItem>
                </VList>
              </VMenu>
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>
