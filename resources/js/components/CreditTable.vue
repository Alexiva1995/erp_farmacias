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

const emit = defineEmits(['update:options', 'open-payment-modal','reload']);

const headers = [
  { title: 'Nombre', key: 'client_full_name', sortable: true },
  { title: 'Monto', key: 'total_pending_amount',  sortable: true},
  { title: 'Añadir', key: 'action', sortable: false},
];

const authStore = useAuthStore();
//const isAdmin = computed(() => authStore.user?.id === 3);
//const isAdmin = computed(() => 3 === 3);

/*const toggleCreditStatus = async (item, newValue) => {
  try {
    const newStatus = newValue ? 'Paid' : 'Active';
     const requestData = {
            ids: item.credit_ids,
            status: newStatus
        };
    await axios.put(`/tpv/credits/status`, requestData);
    console.log(`Estado de los créditos ${item.credit_ids} actualizado a '${newStatus}' con éxito.`);
    toast.success(`Estado del crédito ${item.credit_ids} actualizado a '${newStatus}' con éxito.`);
    emit('reload');
  } catch (error) {
    console.error("Error al actualizar el estado del crédito:", error);
    item.status = newValue ? 'Active' : 'Paid'; 
    toast.error("Error al actualizar el estado del crédito.");
  }
};*/

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
      fixed-header height="auto"
      @update:options="options => emit('update:options', options)"
    >
      <template v-slot:item.client_full_name="{ item }">
      {{ item.client.name }} {{ item.client.last_name }}
    </template>

    <template v-slot:item.action="{ item }">
    <div class="d-flex align-center gap-2">
          <VBtn color="primary" variant="outlined" @click="emit('open-payment-modal', item)" >
            Pagar
          </VBtn>
         <VSwitch
          v-if="isAdmin"
          :model-value="!!item.is_paid" 
          @update:model-value="newValue => toggleCreditStatus(item, newValue)"
        />
        </div>
  </template>

</VDataTableServer>
</VCard>
</template>
