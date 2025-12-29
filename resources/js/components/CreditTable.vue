<script setup>
import { useAuthStore } from "@/stores/auth";

const props = defineProps({
  credits: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalCredits: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits([
  "update:options",
  "open-payment-modal",
  "reload",
  "view-order-modal",
  "print-order",
]);

const headers = [
  { title: "Fecha", key: "credit_date", sortable: true },
  { title: "Documento", key: "client_identification", sortable: false },
  { title: "Nombre", key: "client_full_name", sortable: true },
  { title: "Monto", key: "total_pending_amount", sortable: true },
  { title: "Estado", key: "status", sortable: true },
  { title: "Acciones", key: "action", sortable: false },
];

const authStore = useAuthStore();
</script>
<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.credits"
      :items-length="props.totalCredits"
      :loading="props.loading"
      class="text-no-wrap"
      fixed-header
      height="auto"
      @update:options="(options) => emit('update:options', options)"
    >
      <template v-slot:item.credit_date="{ item }">
        <span>{{
          item.credit_date ? item.credit_date.split(" ")[0] : "N/A"
        }}</span>
      </template>

      <template v-slot:item.client_identification="{ item }">
        {{ item.client.identification_type }}{{ item.client.identification }}
      </template>

      <template v-slot:item.client_full_name="{ item }">
        {{ item.client.name }} {{ item.client.last_name }}
      </template>

      <template v-slot:item.status="{ item }">
        <span
          :class="
            item.status === 0
              ? 'text-error'
              : item.status === 1
              ? 'text-info'
              : 'text-success'
          "
          class="font-weight-medium text-uppercase"
        >
          {{
            item.status === 0
              ? "DEBE"
              : item.status === 1
              ? "PARCIALMENTE PAGADO"
              : "PAGADO"
          }}
        </span>
      </template>

      <template v-slot:item.action="{ item }">
        <div class="d-flex align-center gap-2">
          <IconBtn
            @click="emit('open-payment-modal', item)"
            :disabled="item.status === 2"
          >
            <VIcon icon="tabler-wallet"
          /></IconBtn>
          <IconBtn @click="emit('view-order-modal', item)" color="info">
            <VIcon icon="tabler-eye"
          /></IconBtn>
          <IconBtn @click="emit('print-order', item)">
            <VIcon icon="tabler-printer" />
          </IconBtn>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
