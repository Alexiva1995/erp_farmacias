import { computed, ref } from 'vue';

const props = defineProps({
  selectConDescuento: Boolean,
  tipo_de_vista: Boolean,
  tipo_de_filtracion: String,
  lapso_de_tiempo: String,
  stock: String,
  selectedLaboratory: { type: Array, default: () => [] },
  selectedGroup: { type: Array, default: () => [] },
  laboratories: { type: Array, default: () => [] },
  groups: { type: Array, default: () => [] },
  isColombian: Boolean,
});

const emit = defineEmits([
  "update:selectConDescuento",
  "update:tipo_de_vista",
  "update:tipo_de_filtracion",
  "update:lapso_de_tiempo",
  "update:stock",
  "update:selectedLaboratory",
  "update:selectedGroup",
  "update:isColombian",
  "clear",
  "generarPedido",
]);

const isCollapsed = ref(true);

const activeFiltersCount = computed(() => {
  let count = 0;
  if (props.selectedLaboratory?.length > 0) count++;
  if (props.selectedGroup?.length > 0) count++;
  if (props.isColombian) count++;
  if (props.stock !== 'all') count++;
  return count;
});

const precio = [
  { title: "Full", value: true },
  { title: "Descuento", value: false },
];

const tipoDeVistaOpcion = [
  { title: "Grupal", value: true },
  { title: "Individual", value: false },
];

const tipoFiltracionOpcion = [
  { title: "Promedio", value: "average" },
  { title: "Ventas", value: "sales" },
  { title: "Combinado", value: "combinado" },
];

const lapsoDeTiempoOpciones = [
  { title: "7 Dias", value: "7 days" },
  { title: "15 Dias", value: "15 days" },
  { title: "1 Mes", value: "1 month" },
  { title: "3 Meses", value: "3 month" },
  { title: "6 Meses", value: "6 month" },
  { title: "1 Año", value: "1 year" },
];

const stockOpciones = [
  { title: "Exceso", value: "exceso" },
  { title: "Fallas", value: "fallas" },
  { title: "Todos", value: "all" },
];
</script>

<template>
  <VCard class="mb-6 elevation-0 border rounded-lg overflow-hidden">
    <!-- Header / Toggle -->
    <VCardText class="pa-4 d-flex align-center cursor-pointer hover-bg" @click="isCollapsed = !isCollapsed">
      <VIcon icon="tabler-filter" class="mr-2" color="primary" />
      <span class="text-subtitle-1 font-weight-bold">Filtros de Análisis</span>
      
      <VBadge
        v-if="activeFiltersCount > 0"
        :content="activeFiltersCount"
        color="primary"
        class="ml-3"
        inline
      />

      <VSpacer />
      <VBtn
        variant="text"
        size="small"
        color="secondary"
        :icon="isCollapsed ? 'tabler-chevron-down' : 'tabler-chevron-up'"
      />
    </VCardText>

    <VDivider />

    <VExpandTransition>
      <div v-show="!isCollapsed">
        <VCardText class="pa-5 bg-var-theme-background">
          <VRow>
            <!-- FILA 1 -->
            <VCol cols="12" sm="6" md="4">
              <VAutocomplete
                :model-value="props.selectedLaboratory"
                :items="props.laboratories"
                label="Laboratorio"
                placeholder="Buscar..."
                item-title="name"
                item-value="id"
                clearable
                chips
                multiple
                closable-chips
                density="compact"
                variant="outlined"
                @update:model-value="emit('update:selectedLaboratory', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="4">
              <VAutocomplete
                :model-value="props.selectedGroup"
                :items="props.groups"
                label="Grupos"
                placeholder="Buscar..."
                item-title="name"
                item-value="id"
                clearable
                chips
                multiple
                closable-chips
                density="compact"
                variant="outlined"
                @update:model-value="emit('update:selectedGroup', $event)"
              />
            </VCol>

            <VCol cols="12" sm="12" md="4">
              <VSelect
                :model-value="props.lapso_de_tiempo"
                label="Lapso de tiempo"
                :items="lapsoDeTiempoOpciones"
                clearable
                density="compact"
                variant="outlined"
                @update:model-value="emit('update:lapso_de_tiempo', $event)"
              />
            </VCol>

            <!-- FILA 2 -->
            <VCol cols="12" sm="6" md="3">
              <VSelect
                :model-value="props.tipo_de_filtracion"
                label="Calcular Por"
                :items="tipoFiltracionOpcion"
                clearable
                density="compact"
                variant="outlined"
                @update:model-value="emit('update:tipo_de_filtracion', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="2">
              <VSelect
                :model-value="props.tipo_de_vista"
                label="Vista"
                :items="tipoDeVistaOpcion"
                clearable
                density="compact"
                variant="outlined"
                @update:model-value="emit('update:tipo_de_vista', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="2">
              <VSelect
                :model-value="props.selectConDescuento"
                label="Precio"
                :items="precio"
                clearable
                density="compact"
                variant="outlined"
                @update:model-value="emit('update:selectConDescuento', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="2">
              <VSelect
                :model-value="props.stock"
                label="Stock"
                :items="stockOpciones"
                clearable
                density="compact"
                variant="outlined"
                @update:model-value="emit('update:stock', $event)"
              />
            </VCol>
            
            <VCol cols="12" sm="6" md="3" class="d-flex align-center">
              <VSwitch
                :model-value="props.isColombian"
                label="Solo Origen Colombia"
                color="primary"
                hide-details
                @update:model-value="emit('update:isColombian', $event)"
              />
            </VCol>
          </VRow>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4 bg-var-theme-background">
          <VBtn color="secondary" variant="tonal" prepend-icon="tabler-eraser" @click="emit('clear')">
            Limpiar Filtros
          </VBtn>

          <VSpacer />

          <VBtn
            color="primary"
            variant="elevated"
            prepend-icon="tabler-shopping-cart-plus"
            @click="emit('generarPedido')"
          >
            Generar Pedido de Reposición
          </VBtn>
        </VCardActions>
      </div>
    </VExpandTransition>
  </VCard>
</template>
