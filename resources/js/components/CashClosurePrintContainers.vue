<script setup>
import CashClosureTicke from "@/components/CashClosureTicke.vue";
import HistoryCashClosureTicke from "@/components/HistoryCashClosureTicke.vue";

defineProps({
  isPrinting: {
    type: Boolean,
    default: false,
  },
  isDownload: {
    type: Boolean,
    default: false,
  },
  cashData: {
    type: Object,
    default: null,
  },
  isDownloadingPdf: {
    type: Boolean,
    default: false,
  },
  orderDataHistory: {
    type: Object,
    default: null,
  },
});
</script>

<template>
  <div
    id="CashClosurePrint"
    :class="{ 'd-none': !isPrinting, 'print-container': true }"
  >
    <CashClosureTicke
      v-if="isPrinting && cashData"
      :cash-data="cashData"
      :isPdf="isDownloadingPdf"
    />
  </div>

  <div
    id="HistoryDownload"
    :class="{ 'd-none': !isDownload, 'print-container': true }"
  >
    <HistoryCashClosureTicke
      v-if="isDownload && orderDataHistory"
      :order-data="orderDataHistory"
      :cash-data="cashData"
    />
  </div>
</template>
