<script setup>
import { defineProps, defineEmits, computed, ref, watch } from "vue";
import { formatCurrency } from "@/utils/currencyFormatter";
const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  creditsData: {
    type: Object,
    default: () => ({}),
  },
  selectedCurrency: {
    type: String,
    default: "USD",
  },
});

const emit = defineEmits(["update:isDialogVisible", "modal-closed"]);

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => emit("update:isDialogVisible", val),
});

const closeModal = () => {
  emit("update:isDialogVisible", false);
  emit("modal-closed");
};
</script>
<template>
  <VDialog v-model="dialogVisible" max-width="700px">
    <VCard>
      <VCardTitle class="d-flex align-center p-2">
        <span class="text-h5 font-weight-bold pr-1">Ordenes</span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VCardText class="px-2 py-2 pt-0 mt-0 bg-grey-lighten-2">
        <VList class="card-list no-space-list ma-0" density="compact" nav>
          <VListItem
            v-for="credit in props.creditsData"
            :key="credit.id"
            class="rounded-0"
          >
            <VListItemTitle class="p-2 d-flex align-center">Orden N° {{ credit.order.id }}</VListItemTitle>

            <template #append>
              <div class="d-flex align-center">
                <div class="d-flex flex-column align-end">
                  <span
                    v-if="index === 0"
                    class="text-caption text-medium-emphasis"
                    >Monto</span
                  >
                  <span class="text-body-1 font-weight-regular"
                    >{{ credit.order.total_amount }}
                    {{ credit.order.currency }}</span
                  >
                </div>
              </div>
            </template>
          </VListItem>
        </VList>
      </VCardText>
      <VDivider />

    </VCard>
  </VDialog>
</template>
<style scoped>
.card-list .v-list-item:not(:last-child) {
  padding-block: 4px !important;
  padding-block-end: 0 !important;
}

.v-list .v-list-item--nav:not(:only-child) {
  margin-block-end: 0 !important;
}
</style>
