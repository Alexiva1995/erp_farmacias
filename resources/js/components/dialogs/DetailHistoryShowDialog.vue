<script setup>
import OrderViewModal from "@/components/dialogs/OrderViewModal.vue";
import { computed } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  historyName: { type: String, default: "" },
  details: { type: Array, default: () => [] },
  historyId: { type: Number },
  histories: { type: Object, default: () => ({}) },
  user: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue"]);

const isDialogVisible = computed({
  get() {
    return props.modelValue;
  },
  set(value) {
    emit("update:modelValue", value);
  },
});

const orderData = computed(() => ({
  id: props.histories?.fiscal_id || props.histories?.id || "N/A",
  created_at: props.histories?.invoice_date || props.histories?.created_at,
  seller: {
    username: props.user?.username || "N/A",
  },
  client: {
    name: props.histories?.business_name || "N/A",
    last_name: "", // History often stores simple name
    identification_type: "",
    identification: props.histories?.identification || "N/A",
  },
}));

const orderProducts = computed(() => {
  return props.details.map((detail) => ({
    id: detail.id,
    selectedQuantity: detail.quantity,
    title: detail.product_name,
    laboratory: "", // details commonly lack this in history view unless joined
    // Calculate unit price from total / quantity for display
    fixed_price:
      parseFloat(detail.total_amount || 0) / (parseFloat(detail.quantity) || 1),
    taxRate: 0, // already included in total usually
  }));
});

const totalAmount = computed(() => {
  // Sum up total_amounts
  return props.details.reduce(
    (sum, d) => sum + parseFloat(d.total_amount || 0),
    0
  );
});

// Assume currency. If histories has currency, use it. Default BS if not present?
// History table usually handles BS.
const selectedCurrency = computed(() => {
  return props.histories?.currency || "BS";
});
</script>

<template>
  <OrderViewModal
    v-model:isDialogVisible="isDialogVisible"
    :order-data="orderData"
    :order-products="orderProducts"
    :total-amount="totalAmount"
    :selected-currency="selectedCurrency"
    :payments="[]"
    :change-amount="0"
    :credit-amount="0"
    :credit="false"
  />
</template>
