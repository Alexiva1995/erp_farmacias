<script setup>
import { computed } from "vue";

const props = defineProps({
  processedInvoiceDetails: {
    type: Array,
    required: true,
  },
  loadingDetails: {
    type: Boolean,
    default: false,
  },
  detailsHeaders: {
    type: Array,
    required: true,
  },
  isEditableMode: {
    type: Boolean,
    default: false,
  },
  isEditMode: {
    type: Boolean,
    default: false,
  },
  isLocationMode: {
    type: Boolean,
    default: false,
  },
  editingDetailId: {
    type: [Number, String, null],
    default: null,
  },
  editedDetailData: {
    type: Object,
    default: () => ({}),
  },
  invoice: {
    type: Object,
    required: true,
  },
  locations: {
    type: Array,
    default: () => [],
  },
  invoiceHasIva: {
    type: Boolean,
    default: false,
  },
  draggedOverItemId: {
    type: [Number, String, null],
    default: null,
  },
  getRowProps: {
    type: Function,
    required: true,
  },
  isNearExpiration: {
    type: Function,
    required: true,
  },
  isItemReturned: {
    type: Function,
    required: true,
  },
  getCostComparisonClass: {
    type: Function,
    required: true,
  },
  getPriceVsAutoOrderIndicator: {
    type: Function,
    required: true,
  },
  getPriceVsSystemCostIndicator: {
    type: Function,
    required: true,
  },
  formatCurrency: {
    type: Function,
    required: true,
  },
  getCurrencySymbol: {
    type: Function,
    required: true,
  },
});

const emit = defineEmits([
  "drag-start",
  "drag-over",
  "drop",
  "drag-end",
  "update-location",
  "recalculate-total-from-unit",
  "recalculate-unit-from-total",
  "save-editing-detail",
  "cancel-editing-detail",
  "toggle-return-item",
  "toggle-tax",
  "start-editing-detail",
  "remove-product-from-invoice",
]);
</script>

<template>
  <VDataTable
    :headers="detailsHeaders"
    :items="processedInvoiceDetails"
    :loading="loadingDetails"
    :hide-default-footer="true"
    :items-per-page="-1"
    class="invoice-products-table"
    :row-props="getRowProps"
  >
    <template #item.product_name_with_tax="{ item }">
      <div
        class="d-flex align-center py-1"
        :class="{
          'near-expiration-row': isNearExpiration(item),
          'draggable-row': isEditableMode && isEditMode,
          'drag-over': draggedOverItemId === item.id,
        }"
        @dragover="emit('drag-over', $event, item)"
        @drop="emit('drop', item)"
      >
        <!-- Icono de arrastrar a la izquierda del nombre -->
        <VTooltip v-if="isEditableMode && isEditMode" text="Arrastrar para reordenar" location="top">
          <template #activator="{ props: tipProps }">
            <IconBtn
              v-bind="tipProps"
              class="drag-handle me-1.5 flex-shrink-0"
              :class="{
                'drag-over': draggedOverItemId === item.id,
              }"
              draggable="true"
              size="small"
              @dragstart="emit('drag-start', item)"
              @dragover.prevent="emit('drag-over', $event, item)"
              @drop="emit('drop', item)"
              @dragend="emit('drag-end')"
            >
              <VIcon icon="tabler-grip-vertical" size="18" class="text-medium-emphasis" />
            </IconBtn>
          </template>
        </VTooltip>

        <div class="d-flex flex-column align-start">
          <div class="d-flex align-center">
            <span :class="{ 'returned-item': isItemReturned(item) }" class="font-weight-medium text-high-emphasis">
              {{ item.product_name_with_tax }}
            </span>
            <VTooltip v-if="isNearExpiration(item)" location="top">
              <template #activator="{ props: tipProps }">
                <VIcon
                  v-bind="tipProps"
                  icon="tabler-alert-triangle"
                  color="warning"
                  size="16"
                  class="ms-2"
                />
              </template>
              <span>Producto próximo a vencer (menos de 6 meses). Considere marcarlo como devolución.</span>
            </VTooltip>
          </div>
          <span class="text-caption text-medium-emphasis">
            {{ item.product?.laboratory?.name || 'Sin Laboratorio' }}
          </span>
        </div>
      </div>
    </template>

    <template #item.lot_and_expiration="{ item }">
      <div class="d-flex flex-column align-center" :class="{ 'near-expiration-row': isNearExpiration(item) }">
        <VTextField
          v-if="isEditableMode && item.id === editingDetailId"
          v-model="editedDetailData.lot_number"
          density="compact"
          hide-details
          variant="outlined"
          class="editable-cell mb-1"
          :placeholder="item.is_return ? 'Lote (Dev)' : 'Ingrese Lote'"
        />
        <span v-else :class="{ 'returned-item': isItemReturned(item) }" class="font-weight-medium">
          {{ item.lot_number || "Sin Lote" }}
        </span>

        <VTextField
          v-if="isEditableMode && item.id === editingDetailId"
          v-model="editedDetailData.expiration_date"
          type="date"
          density="compact"
          hide-details
          variant="outlined"
          class="editable-cell mt-1"
          :placeholder="item.is_return ? 'Venc. (Dev)' : 'F. Venc'"
        />
        <span
          v-else
          class="text-caption"
          :class="{
            'returned-item': isItemReturned(item),
            'text-warning font-weight-bold': isNearExpiration(item) && !isItemReturned(item),
            'text-disabled': !isNearExpiration(item)
          }"
        >
          <VIcon v-if="isNearExpiration(item) && !isItemReturned(item)" icon="tabler-alert-triangle-filled" size="14" class="me-1" />
          {{ item.expiration_date || "Sin Vencimiento" }}
        </span>
      </div>
    </template>

    <template #item.location="{ item }">
      <VAutocomplete
        v-if="isLocationMode && !isItemReturned(item)"
        :model-value="item.location"
        :items="locations"
        item-title="name"
        item-value="name"
        density="compact"
        hide-details
        variant="outlined"
        class="editable-cell"
        placeholder="Ej: A-01-B"
        :return-object="false"
        auto-select-first
        @update:model-value="emit('update-location', item.id, $event)"
      />
      <VChip
        v-else-if="isItemReturned(item)"
        size="x-small"
        color="warning"
        variant="tonal"
        class="font-weight-bold"
      >
        N/A (Devolución)
      </VChip>
      <span
        v-else
        :class="{ 'returned-item': isItemReturned(item) }"
      >{{ item.location || "-" }}</span>
    </template>

    <template #item.quantity="{ item }">
      <VTextField
        v-if="isEditableMode && item.id === editingDetailId"
        v-model.number="editedDetailData.quantity"
        type="number"
        step="1"
        density="compact"
        hide-details
        variant="outlined"
        class="editable-cell"
        min="0"
      />
      <span
        v-else
        :class="{ 'returned-item': isItemReturned(item) }"
        class="font-weight-medium"
      >
        {{ Math.round(Number(item.quantity) || 0) }}
      </span>
    </template>

    <template #item.unit_cost="{ item }">
      <div v-if="isEditableMode && item.id === editingDetailId" class="d-flex flex-column gap-1 my-1" style="min-width: 140px;">
        <VTextField
          v-model.number="editedDetailData.unit_cost"
          @input="emit('recalculate-total-from-unit')"
          type="number"
          step="0.01"
          density="compact"
          hide-details
          variant="outlined"
          placeholder="Unitario"
          label="Unitario"
          :prefix="getCurrencySymbol()"
        />
        <VTextField
          v-model.number="editedDetailData.total_cost_input"
          @input="emit('recalculate-unit-from-total')"
          type="number"
          step="0.01"
          density="compact"
          hide-details
          variant="outlined"
          placeholder="Total"
          label="Total"
          :prefix="getCurrencySymbol()"
        />
      </div>
      <div
        v-else
        class="cost-cell d-flex flex-column align-end"
        :class="[
          getCostComparisonClass(item),
          { 'returned-item': isItemReturned(item) },
        ]"
      >
        <!-- Línea 1: Costo en moneda de factura (ej. Bs) con indicador vs Auto-Orden -->
        <div class="d-flex align-center justify-end gap-1">
          <span class="font-weight-bold text-high-emphasis">{{
            formatCurrency(item.unit_cost, invoice.currency)
          }}</span>
          <VTooltip
            v-if="getPriceVsAutoOrderIndicator(item)"
            :text="getPriceVsAutoOrderIndicator(item).tooltip"
            location="top"
          >
            <template #activator="{ props: tipProps }">
              <VChip
                v-bind="tipProps"
                size="x-small"
                :color="getPriceVsAutoOrderIndicator(item).color"
                variant="tonal"
                class="px-1 font-weight-bold"
                style="height: 18px; font-size: 10px;"
              >
                <VIcon :icon="getPriceVsAutoOrderIndicator(item).icon" size="12" class="me-0.5" />
                {{ getPriceVsAutoOrderIndicator(item).badgeText }}
              </VChip>
            </template>
          </VTooltip>
        </div>

        <!-- Línea 2: Costo en USD en texto negro con indicador vs Costo Actual en Sistema -->
        <div
          v-if="invoice.currency !== 'USD' && item.unit_cost_usd != null"
          class="d-flex align-center justify-end gap-1 mt-0.5"
        >
          <span
            class="font-weight-bold text-high-emphasis"
            style="font-size: 11px; color: inherit;"
          >
            ${{ Number(item.unit_cost_usd).toFixed(2) }}
          </span>
          <VTooltip
            v-if="getPriceVsSystemCostIndicator(item)"
            :text="getPriceVsSystemCostIndicator(item).tooltip"
            location="top"
          >
            <template #activator="{ props: tipProps }">
              <VChip
                v-bind="tipProps"
                size="x-small"
                :color="getPriceVsSystemCostIndicator(item).color"
                variant="tonal"
                class="px-1 font-weight-bold"
                style="height: 18px; font-size: 10px;"
              >
                <VIcon :icon="getPriceVsSystemCostIndicator(item).icon" size="12" class="me-0.5" />
                {{ getPriceVsSystemCostIndicator(item).badgeText }}
              </VChip>
            </template>
          </VTooltip>
        </div>
      </div>
    </template>

    <template #item.tax_amount="{ item }">
      <div
        class="d-flex flex-column align-end"
        :class="{ 'returned-item': isItemReturned(item) }"
      >
        <span :class="{ 'font-weight-bold text-high-emphasis': item.tax_amount > 0 }">
          {{ formatCurrency(item.tax_amount, invoice.currency) }}
        </span>
      </div>
    </template>

    <template #item.total_cost="{ item }">
      <div
        class="d-flex flex-column align-end"
        :class="{ 'returned-item': isItemReturned(item) }"
      >
        <span class="font-weight-bold text-high-emphasis">
          {{ formatCurrency(item.total_cost, invoice.currency) }}
        </span>
      </div>
    </template>

    <template #item.actions="{ item }">
      <div v-if="isEditableMode && isEditMode">
        <div v-if="item.id === editingDetailId" class="d-flex align-center ga-1 justify-center">
          <VBtn
            color="success"
            size="small"
            variant="flat"
            icon="tabler-check"
            title="Guardar renglón"
            @click="emit('save-editing-detail')"
          />
          <VBtn
            color="error"
            size="small"
            variant="tonal"
            icon="tabler-x"
            title="Cancelar edición"
            @click="emit('cancel-editing-detail')"
          />
        </div>
        <div v-else class="d-flex align-center ga-1 justify-center">
          <VTooltip text="Marcar para Devolución">
            <template #activator="{ props: tipProps }">
              <IconBtn v-bind="tipProps" size="small" @click="emit('toggle-return-item', item)">
                <VIcon
                  :color="isItemReturned(item) ? 'warning' : 'default'"
                  icon="tabler-arrow-back-up"
                  size="18"
                />
              </IconBtn>
            </template>
          </VTooltip>
          <VTooltip
            :text="
              !invoiceHasIva
                ? 'Factura sin IVA'
                : item.tax_enabled
                  ? 'Quitar IVA'
                  : 'Agregar IVA'
            "
          >
            <template #activator="{ props: tipProps }">
              <IconBtn
                v-bind="tipProps"
                :disabled="!invoiceHasIva"
                :class="{ 'disabled-button': !invoiceHasIva }"
                size="small"
                @click="emit('toggle-tax', item)"
              >
                <VIcon
                  :color="
                    !invoiceHasIva
                      ? 'disabled'
                      : item.tax_enabled
                        ? 'success'
                        : 'default'
                  "
                  icon="tabler-receipt-tax"
                  size="18"
                />
              </IconBtn>
            </template>
          </VTooltip>
          <IconBtn size="small" title="Editar renglón" @click="emit('start-editing-detail', item)">
            <VIcon icon="tabler-edit" size="18" />
          </IconBtn>
          <IconBtn size="small" color="error" title="Eliminar renglón" @click="emit('remove-product-from-invoice', item.id)">
            <VIcon icon="tabler-trash" size="18" />
          </IconBtn>
        </div>
      </div>
    </template>
    <template #bottom />
  </VDataTable>
</template>
