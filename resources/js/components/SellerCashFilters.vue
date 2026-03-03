<script setup>
import axios from '@axios';
import { onMounted, ref } from "vue";

const props = defineProps({
  searchQuery: [String, Number],
  startDate: {
    type: String,
    default: null,
  },
  endDate: {
    type: String,
    default: null,
  },
  showDateFilters: {
    type: Boolean,
    default: false,
  },
    showStateFilters: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  "update:searchQuery",
  "clear",
  "update:startDate",
  "update:endDate",
]);

const sellers = ref([]);
const loadingSellers = ref(false);

const fetchSellers = async () => {
    loadingSellers.value = true;
    try {
        const response = await axios.get('/api/finances/cash-closure/sellers');
        sellers.value = response.data;
    } catch (error) {
        console.error("Error cargando vendedores", error);
    } finally {
        loadingSellers.value = false;
    }
};

onMounted(() => {
    fetchSellers();
});

</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="3">
          <VAutocomplete
            :model-value="props.searchQuery"
            :items="sellers"
            item-title="username"
            item-value="id"
            placeholder="Seleccionar Vendedor"
            clearable
            :loading="loadingSellers"
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>

        <template v-if="props.showDateFilters">
          <VCol cols="12" sm="6" md="4">
            <AppDateTimePicker
              :model-value="props.startDate"
              placeholder="Fecha Desde"
              clearable
              :config="{
                altInput: true,
                altFormat: 'Y-m-d',
                dateFormat: 'Y-m-d',
              }"
              @update:model-value="emit('update:startDate', $event)"
            />
          </VCol>

          <VCol cols="12" sm="6" md="4">
            <AppDateTimePicker
              :model-value="props.endDate"
              placeholder="Fecha Hasta"
              clearable
              :config="{
                altInput: true,
                altFormat: 'Y-m-d',
                dateFormat: 'Y-m-d',
              }"
              @update:model-value="emit('update:endDate', $event)"
            />
          </VCol>
        </template>
      </VRow>
    </VCardText>
    <VDivider />
    <VCardActions class="pa-4 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtros
      </VBtn>
    </VCardActions>
  </VCard>
</template>
