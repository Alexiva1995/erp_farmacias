<script setup>
import { toast } from "@/plugins/sweetalert";
import axios from "@/plugins/axios";
import { useAuthStore } from "@/stores/auth";

const props = defineProps({
  credits: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalCredits: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "open-payment-modal", "reload", "view-order-modal","print-order"]);

const headers = [
  { title: "Nombre", key: "client_full_name", sortable: true },
  { title: "Monto", key: "total_pending_amount", sortable: true },
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
      <template v-slot:item.client_full_name="{ item }">
        {{ item.client.name }} {{ item.client.last_name }}
      </template>

      <template v-slot:item.action="{ item }">
        <div class="d-flex align-center gap-2">
          
          <VCheckbox
            label="Pagar"
            :model-value="item.is_paid" 
            :disabled="item.is_paid"
            @update:model-value="emit('open-payment-modal', item)"
          />
          <IconBtn @click="emit('view-order-modal', item)">
            <VIcon icon="tabler-eye"
          /></IconBtn>
            <IconBtn
            @click="emit('print-order', item)">
            <VIcon icon="tabler-printer" />
          </IconBtn>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
