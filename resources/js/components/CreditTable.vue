<script setup>
import { toast } from "@/plugins/sweetalert";
import axios from "@/plugins/axios";

const props = defineProps({
  credits: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalCredits: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(['update:options']);

const headers = [
  { title: 'Nombre', key: 'client_full_name', sortable: true },
  { title: 'Monto', key: 'pending_amount',  sortable: true},
  { title: 'Añadir', key: 'action', sortable: false},
];



const toggleCreditStatus = async (item, newValue) => {
  try {
    const newStatus = newValue ? 'Paid' : 'Active';
    item.status = newStatus;
    await axios.put(`/tpv/credits/${item.id}`, { status: newStatus });
    console.log(`Estado del crédito ${item.id} actualizado a '${newStatus}' con éxito.`);
    toast.success(`Estado del crédito ${item.id} actualizado a '${newStatus}' con éxito.`);
  } catch (error) {
    console.error("Error al actualizar el estado del crédito:", error);
    item.status = newValue ? 'Active' : 'Paid'; 
    toast.error("Error al actualizar el estado del crédito.");
  }
};

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
          <VBtn color="primary" variant="outlined" @click="emit('paid')">
            Pagar
          </VBtn>
         <VSwitch
          :model-value="item.status === 'Paid'"
          @update:model-value="newValue => toggleCreditStatus(item, newValue)"
        />

        </div>
  </template>

</VDataTableServer>
</VCard>
</template>
