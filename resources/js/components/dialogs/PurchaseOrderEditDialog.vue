<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  purchaseOrder: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modalValue", "delete-detail", "update-details", "save", "clearErrors"]);
const details = ref([]);
const affectedRows = ref(new Map());
const dirty = ref(false);
const formErrors = ref({});
const idToIndex = ref(new Map());

const page = ref(1);
const itemsPerPage = ref(10);
const totalDetails = ref(0);

const closeDialog = () => {
  emit("update:modelValue", false);
  formErrors.value = {};
  idToIndex.value = new Map();
  emit("clearErrors");
};

watch(
  () => props.purchaseOrder,
  (purchaseOrder) => purchaseOrder?.id && fetchPurchaseOrder(purchaseOrder.id),
  { deep: true, immediate: true },
);

const reset = () => {
  affectedRows.value = new Map();
  dirty.value = false;
  formErrors.value = {};
  idToIndex.value = new Map();
};

const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  try {
    const date = new Date(dateString);
    const year = date.getUTCFullYear();
    const month = (date.getUTCMonth() + 1).toString().padStart(2, "0");
    const day = date.getUTCDate().toString().padStart(2, "0");
    return `${year}-${month}-${day}`;
  } catch (error) {
    return "Fecha inválida";
  }
};

const groupProductsHeaders = [
  { title: "Nombre", key: "product_name", sortable: false },
  { title: "Cantidad", key: "quantity", sortable: false },
  { title: "Coste", key: "unit_cost", sortable: false },
  { title: "Subtotal", key: "subtotal", sortable: false },
  { title: "Acciones", key: "actions", sortable: false },
];

const fetchPurchaseOrder = async (id) => {
  try {
    reset();
    const { data } = await axios.get(`/suppliers/purchase-orders/${id}`);
    details.value = data.data;
    affectedRows.value = new Map(data.data.map((d) => [d.id, { quantity: d.quantity, unit_cost: d.unit_cost }]));
    totalDetails.value = data.total;
  } catch (error) {
    console.error(error);
    toast.error("Error al obtener los detalles de la orden de compra.");
  }
};

const submitForm = async () => {
  const affected = details.value.filter(
    (r) =>
      r.quantity !== affectedRows.value.get(r.id)?.quantity || r.unit_cost !== affectedRows.value.get(r.id)?.unit_cost,
  );

  if (!affected.length) {
    toast.info("Sin cambios que guardar");
    closeDialog();
    return;
  }

  idToIndex.value = new Map(affected.map((d, idx) => [d.id, { index: idx }]));

  emit("save", { details: affected });
};

watch(
  () => props.errors,
  (newErrors) => {
    formErrors.value = newErrors || {};
  },
  { deep: true },
);

watch(
  details,
  (rows) => {
    const changed = rows.some(
      (r) =>
        r.quantity !== affectedRows.value.get(r.id)?.quantity ||
        r.unit_cost !== affectedRows.value.get(r.id)?.unit_cost,
    );
    dirty.value = changed;
  },
  { deep: true },
);

const getError = (row, attr) => {
  const idx = idToIndex.value.get(row.id)?.index;
  if (idx === -1) return "";
  const key = `details.${idx}.${attr}`;
  return props.errors?.[key]?.[0] || "";
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="800px"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard class="d-flex flex-column">
      <VCardTitle class="d-flex align-center">
        <span class="text-h5 font-weight-bold">Editar Orden de Compra</span>

        <VSpacer />

        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VSheet color="#f5f5f5" variant="tonal" rounded="lg" class="pa-4">
        <p class="text-h6 font-weight-medium mb-4">Detalles de Orden</p>

        <VRow>
          <VCol cols="6">
            <div class="d-flex align-center gap-4 mb-4">
              <span class="font-weight-medium">Proveedor</span>
              <VChip color="primary" label>{{ purchaseOrder.supplier_name }}</VChip>
              <VSpacer />
            </div>
          </VCol>
          <VCol cols="6">
            <div class="d-flex align-center gap-4 mb-4">
              <span class="font-weight-medium">Fecha de solicitud</span>
              <VChip color="primary" label>{{ formatDate(purchaseOrder.order_date) }}</VChip>
              <VSpacer />
            </div>
          </VCol>
        </VRow>

        <VDataTable
          v-if="details.length > 0"
          :headers="groupProductsHeaders"
          :items="details"
          density="compact"
          class="mt-4 rounded-lg"
          no-data-text="Esta orden no tiene detalles."
          :itemsPerPage="itemsPerPage"
          :page="page"
          :total="totalDetails"
        >
          <template #item.quantity="{ item }">
            <VTextField
              v-model="item.quantity"
              label=""
              min="1"
              type="number"
              variant="outlined"
              :error="!!getError(item, 'quantity')"
              :error-messages="getError(item, 'quantity')"
            />
          </template>
          <template #item.subtotal="{ item }">
            <span>{{ (item.unit_cost * item.quantity).toFixed(2) }}</span>
          </template>
          <template #item.actions="{ item }">
            <VRow>
              <VTooltip text="Eliminar detalle de Orden de Compra" location="top">
                <template #activator="{ props }">
                  <IconBtn v-bind="props" @click="emit('delete-detail', item.id)">
                    <VIcon icon="tabler-trash" />
                  </IconBtn>
                </template>
              </VTooltip>
            </VRow>
          </template>
          <template #body.append>
            <tr class="font-weight-bold">
              <td :colspan="groupProductsHeaders.length - 4" class="text-right">Total</td>
              <td class="text-right">
                {{ details.reduce((sum, r) => Number(sum) + Number(r.quantity), 0) }}
              </td>
              <td :colspan="groupProductsHeaders.length - 4" class="text-right">Total</td>
              <td class="text-right">
                {{ details.reduce((sum, r) => sum + r.quantity * r.unit_cost, 0).toFixed(2) }}
              </td>
            </tr>
          </template>
        </VDataTable>
      </VSheet>

      <VDivider />

      <!-- El VCardActions se mantiene igual, será el pie de página fijo -->
      <VCardActions class="pa-4">
        <VBtn color="secondary" variant="outlined" @click="closeDialog" class="flex-grow-1 w-0 mr-4"> Cancelar </VBtn>
        <VBtn color="primary" variant="flat" @click="submitForm" class="flex-grow-1 w-0"> Guardar </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
