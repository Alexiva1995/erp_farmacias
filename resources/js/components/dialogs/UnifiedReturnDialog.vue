<script setup>
import { computed, reactive, ref, watch } from "vue";
import { formatCurrency } from "@/utils/currencyFormatter";
import { toast } from "@/plugins/sweetalert";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  order: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  requireApproval: { type: Boolean, default: true },
  canDirectApprove: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "submit"]);

const formState = reactive({
  refund_method: "balance",
  restock_inventory: true,
  reason: "",
  approve_immediately: true,
});

const selectedIds = ref([]);
const quantities = ref({});

const refundMethodOptions = [
  { title: "Saldo a favor del cliente", value: "balance" },
  { title: "Efectivo (egreso de caja)", value: "cash" },
  { title: "Pago Móvil (egreso)", value: "mobile_payment" },
  { title: "Transferencia (egreso)", value: "transfer" },
];

watch(
  () => [props.modelValue, props.order],
  ([isOpen, order]) => {
    if (!isOpen || !order) return;
    formState.refund_method = "balance";
    formState.restock_inventory = true;
    formState.reason = "";
    formState.approve_immediately = !props.requireApproval || props.canDirectApprove;

    const nextQty = {};
    const availableDetails = (order.details || []).filter(
      (d) => (d.available_quantity ?? d.quantity ?? 0) > 0
    );
    availableDetails.forEach((d) => {
      nextQty[d.id] = d.available_quantity ?? d.quantity ?? 1;
    });
    quantities.value = nextQty;
    selectedIds.value = [];
  },
  { immediate: true }
);

const orderCurrency = computed(() =>
  String(props.order?.currency || "USD").toUpperCase()
);

const hasFiscalInvoice = computed(() =>
  Boolean(props.order?.fiscal_history?.invoice_number)
);

const detailsList = computed(() => props.order?.details || []);

const getUnitNetPrice = (detail) => {
  const basePrice = Number(detail.price || detail.unit_price_usd || 0);
  const discountPct = Number(detail.discount_percentage || 0);
  return (basePrice * (100 - discountPct)) / 100;
};

const getLineSubtotal = (detail) => {
  if (!selectedIds.value.includes(detail.id)) return 0;
  const qty = Number(quantities.value[detail.id] || 0);
  return qty * getUnitNetPrice(detail);
};

const totalRefundAmount = computed(() =>
  detailsList.value.reduce((sum, detail) => sum + getLineSubtotal(detail), 0)
);

const totalSelectedUnits = computed(() =>
  detailsList.value.reduce((sum, detail) => {
    if (!selectedIds.value.includes(detail.id)) return sum;
    return sum + Number(quantities.value[detail.id] || 0);
  }, 0)
);

const isSelected = (detailId) => selectedIds.value.includes(detailId);

const toggleSelection = (detail) => {
  const available = Number(detail.available_quantity ?? detail.quantity ?? 0);
  if (available <= 0) return;

  if (isSelected(detail.id)) {
    selectedIds.value = selectedIds.value.filter((id) => id !== detail.id);
  } else {
    selectedIds.value = [...selectedIds.value, detail.id];
  }
};

const closeDialog = () => {
  emit("update:modelValue", false);
};

const handleSubmit = () => {
  if (!props.order) return;
  if (selectedIds.value.length === 0) {
    toast.warning("Seleccione al menos un producto para devolver.");
    return;
  }

  const items = [];
  for (const detail of detailsList.value) {
    if (!selectedIds.value.includes(detail.id)) continue;

    const qty = Number(quantities.value[detail.id] || 0);
    const maxQty = Number(detail.available_quantity ?? detail.quantity ?? 0);

    if (!Number.isInteger(qty) || qty <= 0) {
      toast.warning(`Ingrese una cantidad válida para ${detail.product?.name || "el producto"}.`);
      return;
    }
    if (qty > maxQty) {
      toast.warning(`La cantidad de ${detail.product?.name || "producto"} supera el máximo disponible (${maxQty}).`);
      return;
    }

    items.push({
      product_id: detail.product_id,
      quantity: qty,
    });
  }

  emit("submit", {
    order_id: props.order.id,
    items,
    refund_method: formState.refund_method,
    restock_inventory: formState.restock_inventory,
    reason: formState.reason ? formState.reason.trim() : null,
    approve_immediately: formState.approve_immediately,
  });
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="920"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="rounded-lg">
      <VCardTitle class="d-flex align-center justify-space-between pa-4 bg-surface border-b">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="tonal" size="38">
            <VIcon icon="tabler-arrow-back-up" size="20" />
          </VAvatar>
          <div>
            <h4 class="text-subtitle-1 font-weight-bold mb-0">
              Procesar Devolución — Orden #{{ props.order?.id }}
            </h4>
            <span class="text-caption text-medium-emphasis">
              {{ props.order?.client?.name }} {{ props.order?.client?.last_name }}
              ({{ props.order?.client?.identification_type }}{{ props.order?.client?.identification }})
            </span>
          </div>
        </div>
        <VBtn icon="tabler-x" variant="text" size="small" :disabled="props.loading" @click="closeDialog" />
      </VCardTitle>

      <VCardText class="pa-4">
        <VAlert
          v-if="hasFiscalInvoice"
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          Orden con <strong>Factura Fiscal #{{ props.order.fiscal_history.invoice_number }}</strong>.
          Al aprobar la devolución se encolará automáticamente la <strong>Nota de Crédito</strong> fiscal.
        </VAlert>

        <VAlert
          v-else
          type="secondary"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          Esta orden no tiene factura fiscal emitida. Se actualizará únicamente inventario y reembolso.
        </VAlert>

        <VTable density="comfortable" class="border rounded-lg mb-4">
          <thead>
            <tr>
              <th style="inline-size: 44px;"></th>
              <th>Producto</th>
              <th class="text-center">Vendido</th>
              <th class="text-center">Disponible</th>
              <th class="text-center" style="inline-size: 130px;">Devolver</th>
              <th class="text-end">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="detail in detailsList"
              :key="detail.id"
              :class="{ 'bg-var-theme-background': isSelected(detail.id) }"
            >
              <td>
                <VCheckboxBtn
                  :model-value="isSelected(detail.id)"
                  :disabled="(detail.available_quantity ?? detail.quantity ?? 0) <= 0 || props.loading"
                  color="primary"
                  @update:model-value="toggleSelection(detail)"
                />
              </td>
              <td>
                <div class="font-weight-medium">
                  {{ detail.product?.name || `Producto #${detail.product_id}` }}
                </div>
                <div class="d-flex align-center gap-2 text-caption text-medium-emphasis">
                  <VChip
                    size="x-small"
                    :color="detail.product?.iva ? 'warning' : 'secondary'"
                    variant="tonal"
                  >
                    {{ detail.product?.iva ? 'IVA 16%' : 'Exento' }}
                  </VChip>
                  <span v-if="detail.already_returned > 0">
                    Ya devuelto: {{ detail.already_returned }}
                  </span>
                </div>
              </td>
              <td class="text-center font-weight-medium">
                {{ detail.quantity }}
              </td>
              <td class="text-center">
                <VChip
                  size="small"
                  :color="(detail.available_quantity ?? detail.quantity) > 0 ? 'success' : 'secondary'"
                  variant="tonal"
                >
                  {{ detail.available_quantity ?? detail.quantity }}
                </VChip>
              </td>
              <td>
                <VTextField
                  v-model.number="quantities[detail.id]"
                  type="number"
                  min="1"
                  :max="detail.available_quantity ?? detail.quantity"
                  density="compact"
                  variant="outlined"
                  hide-details
                  :disabled="!isSelected(detail.id) || props.loading"
                />
              </td>
              <td class="text-end font-weight-bold">
                {{ formatCurrency(getLineSubtotal(detail), orderCurrency) }}
              </td>
            </tr>
          </tbody>
        </VTable>

        <VRow>
          <VCol cols="12" md="6">
            <VSelect
              v-model="formState.refund_method"
              :items="refundMethodOptions"
              item-title="title"
              item-value="value"
              label="Método de Reembolso"
              variant="outlined"
              density="compact"
              :disabled="props.loading"
            />
          </VCol>
          <VCol cols="12" md="6">
            <VTextField
              v-model="formState.reason"
              label="Motivo de devolución (opcional)"
              placeholder="Ej: Producto equivocado, empaque dañado..."
              variant="outlined"
              density="compact"
              maxlength="255"
              :disabled="props.loading"
            />
          </VCol>
          <VCol cols="12" md="6">
            <VSwitch
              v-model="formState.restock_inventory"
              :label="formState.restock_inventory ? 'Reingresar al inventario vendible' : 'Descartar como dañado / merma'"
              :color="formState.restock_inventory ? 'success' : 'warning'"
              density="compact"
              hide-details
              :disabled="props.loading"
            />
          </VCol>
          <VCol v-if="props.requireApproval && props.canDirectApprove" cols="12" md="6">
            <VSwitch
              v-model="formState.approve_immediately"
              label="Aprobar y ejecutar inmediatamente"
              color="primary"
              density="compact"
              hide-details
              :disabled="props.loading"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 d-flex justify-space-between align-center flex-wrap gap-3">
        <div>
          <span class="text-caption text-medium-emphasis d-block">
            Total a reembolsar ({{ totalSelectedUnits }} unid.)
          </span>
          <span class="text-h6 font-weight-bold text-primary">
            {{ formatCurrency(totalRefundAmount, orderCurrency) }}
          </span>
        </div>

        <div class="d-flex gap-2">
          <VBtn variant="outlined" color="secondary" :disabled="props.loading" @click="closeDialog">
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            variant="flat"
            prepend-icon="tabler-check"
            :loading="props.loading"
            :disabled="props.loading || selectedIds.length === 0"
            @click="handleSubmit"
          >
            Confirmar Devolución
          </VBtn>
        </div>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
