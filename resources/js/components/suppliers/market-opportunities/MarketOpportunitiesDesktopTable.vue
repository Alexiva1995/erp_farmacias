<script setup>
import { formatCurrency } from "@/utils/currencyFormatter";

defineProps({
  itemsPerPage: { type: Number, default: 10 },
  page: { type: Number, default: 1 },
  sortBy: { type: Array, default: () => [{ key: "saving_percentage", order: "desc" }] },
  headers: { type: Array, required: true },
  items: { type: Array, required: true },
  totalItems: { type: Number, default: 0 },
  loading: { type: Boolean, default: false },
  submittingItems: { type: Object, default: () => ({}) },
});

const emit = defineEmits([
  "update:itemsPerPage",
  "update:page",
  "update:sortBy",
  "fetch",
  "add-units",
]);
</script>

<template>
  <VDataTableServer
    :items-per-page="itemsPerPage"
    :page="page"
    :sort-by="sortBy"
    :headers="headers"
    :items="items"
    :items-length="totalItems"
    :loading="loading"
    hover
    density="compact"
    class="text-no-wrap premium-table overflow-hidden"
    @update:items-per-page="emit('update:itemsPerPage', $event)"
    @update:page="emit('update:page', $event)"
    @update:sort-by="emit('update:sortBy', $event)"
    @update:options="emit('fetch')"
  >
    <template #item.product_id="{ item }">
      <a
        :href="'/inventory/traceability?q=' + item.product_id"
        target="_blank"
        class="text-decoration-none font-weight-black text-primary"
      >
        {{ item.product_id }}
      </a>
    </template>

    <!-- Producto -->
    <template #item.product_name_inventory="{ item }">
      <div class="d-flex align-center py-1" style="max-inline-size: 380px;">
        <div class="d-flex flex-column overflow-hidden">
          <span
            class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate"
            :title="item.product_name_inventory"
          >
            {{ item.product_name_inventory.toUpperCase() }}
          </span>
          <div class="d-flex align-center gap-1 text-super-xs">
            <span
              class="text-disabled truncate"
              style="max-inline-size: 200px"
              >{{
                item.active_ingredient_inventory || "SIN INGREDIENTE"
              }}</span
            >
            <span class="text-disabled mx-1">|</span>
            <span
              class="text-primary font-weight-black text-uppercase truncate"
              style="max-inline-size: 250px"
            >
              {{ item.laboratory_name || "S/L" }} - {{ item.supplier_name }}
            </span>
          </div>
        </div>
      </div>
    </template>

    <template #item.historic_costs="{ item }">
      <div class="d-flex flex-column align-end text-right">
        <span class="text-xs text-error font-weight-bold leading-none mb-1" title="Costo Máximo Histórico">
          {{ formatCurrency(item.effective_max_cost, "USD") }}
        </span>
        <span class="text-sm text-high-emphasis font-weight-black leading-none mb-1" title="Precio Actual">
          {{ formatCurrency(item.inventory_unit_cost, "USD") }}
        </span>
        <span class="text-xs text-success font-weight-bold leading-none" title="Costo Mínimo Histórico">
          {{ formatCurrency(item.effective_min_cost, "USD") }}
        </span>
      </div>
    </template>

    <template #item.unit_cost_usd="{ item }">
      <div class="d-flex flex-column align-end">
        <span class="text-sm font-weight-bold text-success">{{
          formatCurrency(item.unit_cost_usd, "USD")
        }}</span>
      </div>
    </template>

    <template #item.total_sold_completed="{ item }">
      <span class="text-sm font-weight-bold">{{ item.total_sold_completed || 0 }}</span>
    </template>

    <template #item.lote_quantity="{ item }">
      <VChip
        :color="item.lote_quantity > 0 ? 'secondary' : 'error'"
        variant="tonal"
        size="small"
        class="font-weight-bold"
      >
        {{ item.lote_quantity || 0 }}
      </VChip>
    </template>

    <template #item.totalQuantityInAutoOrder="{ item }">
      <VChip
        :color="item.totalQuantityInAutoOrder > 0 ? 'warning' : 'grey'"
        variant="tonal"
        size="small"
        class="font-weight-bold"
      >
        {{ item.totalQuantityInAutoOrder || 0 }}
      </VChip>
    </template>

    <template #item.promedio_calculado="{ item }">
      <span class="text-sm font-weight-bold">{{ item.promedio_calculado || 0 }}</span>
    </template>

    <template #item.saving_percentage="{ item }">
      <VChip
        color="success"
        size="small"
        label
        class="font-weight-bold"
      >
        {{ item.saving_percentage }}%
      </VChip>
    </template>

    <template #item.actions="{ item }">
      <div class="d-flex align-center ga-2 justify-center">
        <VTextField
          v-model="item.quantity_to_add"
          type="number"
          placeholder="Can."
          density="compact"
          hide-details
          variant="outlined"
          class="quantity-input"
          style="min-inline-size: 80px;"
          :disabled="!!submittingItems[item.id]"
          @keypress.enter="emit('add-units', item)"
        />
        <VBtn
          :icon="!submittingItems[item.id] ? 'tabler-plus' : undefined"
          color="primary"
          variant="tonal"
          size="small"
          class="rounded-circle shadow-sm"
          :loading="!!submittingItems[item.id]"
          :disabled="!!submittingItems[item.id]"
          @click="emit('add-units', item)"
        />
      </div>
    </template>
  </VDataTableServer>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.text-xs {
  font-size: 0.75rem !important;
}

.premium-table :deep(th) {
  background-color: rgb(var(--v-theme-surface)) !important;
  color: rgba(
    var(--v-theme-on-surface),
    var(--v-medium-emphasis-opacity)
  ) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.5px !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.premium-table :deep(tbody tr:hover) {
  background-color: rgba(var(--v-theme-primary), 0.02) !important;
}

.quantity-input {
  inline-size: 80px;
}
</style>
