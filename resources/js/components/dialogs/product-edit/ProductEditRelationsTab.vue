<script setup>
import { computed } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { calculateStock } from "@/utils/formatters";

const props = defineProps({
  formData: { type: Object, required: true },
  groups: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
  allProducts: { type: Array, default: () => [] },
  loadingGroups: { type: Boolean, default: false },
  selectedGroup: { type: Object, default: null },
  xs: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:selectedGroup",
  "group-selected",
  "remove-group",
]);

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => brandingStore.settings.business_type === "restaurant");

const assignedGroupName = computed(() => {
  return props.formData.group ? props.formData.group.name : null;
});

const productsInGroup = computed(() => {
  if (!props.formData.group_id) return [];
  return props.allProducts.filter(
    (p) => p.group_id === props.formData.group_id && p.id !== props.formData.id,
  );
});

const groupProductsHeaders = [
  { title: "ID", key: "id", sortable: false },
  { title: "PRODUCTO", key: "name", sortable: false },
  { title: "LABORATORIO", key: "laboratory.name", sortable: false },
  { title: "STOCK", key: "lots", sortable: false },
];
</script>

<template>
  <div :class="[xs ? 'gap-4' : 'gap-6', 'd-flex flex-column']">
    <!-- Jerarquía y Agrupación -->
    <div v-if="brandingStore.settings.enable_groups" class="d-flex flex-column gap-3">
      <div class="d-flex align-center gap-2">
        <div class="header-indicator primary shadow-sm" />
        <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Jerarquía y Agrupación</span>
      </div>

      <VCard
        variant="flat"
        :class="[xs ? 'pa-3' : 'pa-5', 'bg-surface rounded-xl border shadow-sm']"
      >
        <div class="pa-4 bg-light rounded-xl border-dashed-2">
          <div class="d-flex align-center gap-2 mb-4 leading-none">
            <VIcon
              icon="tabler-link"
              size="18"
              color="primary"
            />
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Asignación de Grupo Maestro</span>
          </div>
          <div 
            v-if="!assignedGroupName"
            class="d-flex gap-2 align-center w-100"
          >
            <VAutocomplete
              :model-value="selectedGroup"
              @update:model-value="emit('group-selected', $event)"
              :items="groups"
              item-title="name"
              item-value="id"
              placeholder="BUSCAR GRUPO POR NOMBRE..."
              variant="outlined"
              density="comfortable"
              hide-details
              class="bg-surface rounded-lg font-weight-black flex-grow-1"
              :loading="loadingGroups"
              return-object
            >
              <template #item="{ props: itemProps, item }">
                <VListItem v-bind="itemProps">
                  <template #title>
                    <div class="font-weight-black text-uppercase text-xs d-flex align-center gap-1">
                      <span class="text-primary">#{{ item.raw.id }}</span>
                      <span class="text-disabled">|</span>
                      <span>{{ item.raw.name }}</span>
                    </div>
                  </template>
                </VListItem>
              </template>
            </VAutocomplete>
          </div>

          <div
            v-if="assignedGroupName"
            :class="!assignedGroupName ? 'mt-4' : ''"
          >
            <VChip
              color="primary"
              variant="flat"
              label
              closable
              class="font-weight-black px-4 rounded-lg shadow-sm"
              height="32"
              @click:close="emit('remove-group')"
            >
              <VIcon
                icon="tabler-hierarchy"
                size="16"
                class="me-2"
              />
              GRUPO ACTUAL: {{ assignedGroupName }}
            </VChip>
          </div>
        </div>
      </VCard>
    </div>

    <!-- Proveedores Asociados (Solo Restaurante) -->
    <div v-if="isRestaurant" class="d-flex flex-column gap-3">
      <div class="d-flex align-center gap-2">
        <div class="header-indicator primary shadow-sm" />
        <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Proveedores del Producto</span>
      </div>

      <VCard
        variant="flat"
        :class="[xs ? 'pa-3' : 'pa-5', 'bg-surface rounded-xl border shadow-sm']"
      >
        <div class="pa-4 bg-light rounded-xl border-dashed-2">
          <div class="d-flex align-center gap-2 mb-4 leading-none">
            <VIcon
              icon="tabler-truck"
              size="18"
              color="primary"
            />
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Vincular Proveedores (Seleccionar uno o más)</span>
          </div>
          <VAutocomplete
            v-model="formData.supplier_ids"
            :items="suppliers"
            item-title="name"
            item-value="id"
            placeholder="SELECCIONAR PROVEEDORES..."
            variant="outlined"
            density="comfortable"
            multiple
            chips
            closable-chips
            class="bg-surface rounded-lg font-weight-black"
            hide-details
          />
        </div>
      </VCard>
    </div>

    <!-- Productos del mismo grupo -->
    <div
      v-if="productsInGroup.length > 0"
      class="d-flex flex-column gap-3"
    >
      <div class="d-flex align-center justify-space-between mb-0">
        <div class="d-flex align-center gap-2">
          <div class="header-indicator secondary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Productos Relacionados</span>
        </div>
        <VChip
          size="x-small"
          color="secondary"
          variant="flat"
          class="font-weight-black rounded-lg px-3 shadow-sm"
        >
          {{ productsInGroup.length }} REGISTROS
        </VChip>
      </div>

      <VCard
        variant="flat"
        class="bg-surface rounded-xl border shadow-sm overflow-hidden"
      >
        <!-- Desktop Table -->
        <div class="d-none d-sm-block">
          <VDataTable
            :headers="groupProductsHeaders"
            :items="productsInGroup"
            density="comfortable"
            class="table-standard"
          >
            <template #item.lots="{ item }">
              <VChip
                size="x-small"
                color="primary"
                variant="tonal"
                class="font-weight-black rounded-lg"
              >
                STK: {{ calculateStock(item) }} UNID.
              </VChip>
            </template>
            <template #item.name="{ item }">
              <span class="text-caption font-weight-black text-medium-emphasis uppercase">{{ item.name }}</span>
            </template>
          </VDataTable>
        </div>

        <!-- Mobile Cards -->
        <div class="d-block d-sm-none pa-3">
          <div class="d-flex flex-column gap-2">
            <VCard
              v-for="item in productsInGroup"
              :key="item.id"
              variant="flat"
              class="pa-3 bg-light rounded-xl border"
            >
              <div class="d-flex align-center justify-space-between mb-2">
                <h4 class="text-xs font-weight-black truncate-2-lines flex-grow-1 mr-2 leading-tight uppercase">
                  <span class="text-primary mr-1">#{{ item.id }}</span>
                  {{ item.name }}
                </h4>
                <VChip
                  size="x-small"
                  color="primary"
                  variant="flat"
                  class="font-weight-black"
                >
                  {{ calculateStock(item) }}
                </VChip>
              </div>
              <div class="text-super-xs text-disabled font-weight-black uppercase mt-1 opacity-80 letter-spacing-1">
                {{ item.laboratory?.name || "SIN LABORATORIO" }}
              </div>
            </VCard>
          </div>
        </div>
      </VCard>
    </div>
  </div>
</template>

<style scoped>
.header-indicator {
  border-radius: 8px !important;
  block-size: 16px;
  inline-size: 4px;
}
.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}
.header-indicator.secondary {
  background-color: rgb(var(--v-theme-secondary));
}
.border-dashed-2 {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}
.letter-spacing-1 {
  letter-spacing: 1px !important;
}
.uppercase {
  text-transform: uppercase;
}
.truncate-2-lines {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
}
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}
</style>
