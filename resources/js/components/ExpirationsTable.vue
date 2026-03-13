<script setup>
import { formatDate } from "@/utils/formatters";
import { computed } from "vue";

const props = defineProps({
  modelValue: {
    type: Array,
    required: true,
  },
  lots: {
    type: Array,
    required: true,
  },
  loading: {
    type: Boolean,
    required: true,
  },
  totalLots: {
    type: Number,
    required: true,
  },
  itemsPerPage: {
    type: Number,
    required: true,
  },
  page: {
    type: Number,
    required: true,
  },
});

const emit = defineEmits([
  "update:modelValue",
  "update:options",
  "expire-lot",
]);

const headers = [
  { title: "ID", key: "product.id", sortable: true },
  { title: "Producto", key: "product.name", sortable: true },
  { 
    title: "Laboratorio", 
    key: "laboratory_name", 
    sortable: true,
    value: (item) => item.product?.laboratory?.name || "—"
  },
  { title: "Nº Lote", key: "lot_number", sortable: false },
  { title: "Exp.", key: "expiration_date", sortable: true },
  { title: "Stock", key: "quantity", sortable: true },
  { title: "Acciones", key: "actions", sortable: false },
];

const selected = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

const getExpirationColor = (dateString) => {
  if (!dateString) return "text-disabled";
  const expDate = new Date(dateString);
  const today = new Date();
  const diffTime = expDate - today;
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

  if (diffDays <= 0) return "text-error font-weight-bold";
  if (diffDays <= 30) return "text-error";
  if (diffDays <= 90) return "text-warning";
  return "text-medium-emphasis";
};

const handleMobilePageChange = (newPage) => {
  emit('update:options', {
    page: newPage,
    itemsPerPage: props.itemsPerPage,
    sortBy: [],
  });
};
</script>

<template>
  <VCard variant="flat">
    <!-- Vista de Escritorio (Tabla) -->
    <div class="d-none d-md-block">
      <VDataTableServer
        v-model="selected"
        :show-select="true"
        item-value="id"
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.lots"
        :items-length="props.totalLots"
        :loading="props.loading"
        class="text-no-wrap"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #item.product.name="{ item }">
          <div class="d-flex align-center gap-x-4">
            <VAvatar
              v-if="item.product?.photo_url"
              size="38"
              variant="tonal"
              rounded
              :image="item.product.photo_url"
            />
            <div class="d-flex flex-column">
              <span class="text-body-1 font-weight-medium text-high-emphasis">{{
                item.product?.name?.toUpperCase() || ""
              }}</span>
              <span class="text-sm text-disabled">{{
                item.product?.active_ingredient || ""
              }}</span>
            </div>
          </div>
        </template>

        <template #item.laboratory_name="{ item }">
          <span class="text-uppercase">{{ item.product?.laboratory?.name || "—" }}</span>
        </template>

        <template #item.lot_number="{ item }">
          <span class="font-weight-medium">{{ item.lot_number }}</span>
        </template>

        <template #item.expiration_date="{ item }">
          <span :class="getExpirationColor(item.expiration_date)">
            {{ formatDate(item.expiration_date) }}
          </span>
        </template>

        <template #item.quantity="{ item }">
          <VChip
            :color="item.quantity > 0 ? 'success' : 'error'"
            label
            size="small"
            variant="tonal"
            class="font-weight-bold"
          >
            {{ item.quantity }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <VTooltip location="top" text="Marcar como Caducado">
            <template #activator="{ props: tooltipProps }">
              <IconBtn
                v-bind="tooltipProps"
                color="error"
                @click="emit('expire-lot', item)"
              >
                <VIcon icon="tabler-calendar-off" />
              </IconBtn>
            </template>
          </VTooltip>
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista de Móvil (Tarjetas) -->
    <div class="d-block d-md-none pa-2">
      <VLinearProgress v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.lots.length === 0 && !props.loading" class="text-center py-8 text-disabled">
        No se encontraron productos por vencer.
      </div>

      <div class="d-flex flex-column gap-2">
        <VCard
          v-for="item in props.lots"
          :key="item.id"
          variant="flat"
          class="border mb-1 overflow-hidden"
          style="border-radius: 8px !important;"
        >
          <div class="pa-3">
            <div class="d-flex gap-3 align-start">
              <VAvatar
                v-if="item.product?.photo_url"
                size="44"
                variant="tonal"
                rounded
                :image="item.product.photo_url"
                class="flex-shrink-0 mt-1"
              />
              <div class="flex-grow-1 min-width-0">
                <div class="d-flex align-center gap-1 mb-1">
                  <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight">
                    <span class="text-primary">{{ item.product?.id }}</span>
                    <span class="mx-1 text-disabled">|</span>
                    {{ item.product?.name }}
                  </h3>
                </div>
                
                <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs">
                  <span class="text-medium-emphasis font-weight-medium">{{ item.product?.active_ingredient }}</span>
                  <span class="text-disabled">|</span>
                  <span class="text-primary font-weight-bold text-uppercase">{{ item.product?.laboratory?.name || 'S/L' }}</span>
                </div>
              </div>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <div class="d-flex align-center justify-space-between bg-var-theme-background px-3 py-2 rounded border-dashed-thin">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Expira</span>
                <span :class="getExpirationColor(item.expiration_date)" class="text-base font-weight-black">
                  {{ formatDate(item.expiration_date) }}
                </span>
              </div>
              <div class="d-flex flex-column text-right">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Stock Lote</span>
                <span :class="item.quantity > 0 ? 'text-success' : 'text-error'" class="text-base font-weight-black">
                  {{ item.quantity ?? 0 }} <small class="text-super-xs">UNDS</small>
                </span>
              </div>
            </div>
            
            <div class="mt-2 px-1">
              <span class="text-super-xs text-disabled text-uppercase font-weight-bold">Lote: </span>
              <span class="text-super-xs font-weight-black">{{ item.lot_number }}</span>
            </div>
          </div>

          <VBtn 
            block 
            color="error" 
            variant="flat" 
            class="rounded-0"
            height="44"
            prepend-icon="tabler-calendar-off" 
            @click="emit('expire-lot', item)"
          >
            MARCAR COMO CADUCADO
          </VBtn>
        </VCard>
      </div>

      <!-- Paginación Móvil -->
      <div class="d-flex justify-center mt-4">
        <VPagination
          :model-value="props.page"
          :length="Math.ceil(props.totalLots / props.itemsPerPage)"
          :total-visible="3"
          density="compact"
          size="small"
          @update:model-value="handleMobilePageChange"
        />
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}

.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }
</style>
