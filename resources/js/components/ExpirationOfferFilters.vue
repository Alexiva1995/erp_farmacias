<script setup>
const props = defineProps({
  searchQuery: { type: String, default: '' },
  status: { type: String, default: '' },
  months: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  addOfferLoading: { type: Boolean, default: false },
});

const emit = defineEmits([
  'update:searchQuery',
  'update:status',
  'update:months',
  'clear',
  'search',
  'add-expiration-offer'
]);

const statusOptions = [
  { title: 'Todos', value: '' },
  { title: 'Activo', value: '1' },
  { title: 'Inactivo', value: '0' },
];

const monthsOptions = [
  { title: 'Todos', value: '' },
  { title: '1 mes', value: '1' },
  { title: '2 meses', value: '2' },
  { title: '3 meses', value: '3' },
  { title: '6 meses', value: '6' },
  { title: '12 meses', value: '12' },
];
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="3">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por meses, descuento, producto..."
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
            @keyup.enter="emit('search')"
          />
        </VCol>
        
        <VCol cols="12" sm="6" md="2">
          <VSelect
            :model-value="props.status"
            :items="statusOptions"
            label="Estado"
            clearable
            @update:model-value="emit('update:status', $event)"
          />
        </VCol>

        <VCol cols="12" sm="6" md="2">
          <VSelect
            :model-value="props.months"
            :items="monthsOptions"
            label="Meses"
            clearable
            @update:model-value="emit('update:months', $event)"
          />
        </VCol>
        
        <VCol cols="12" sm="6" md="3" class="d-flex align-center">
          <VBtn
            color="primary"
            variant="outlined"
            :loading="props.loading"
            @click="emit('search')"
          >
            <VIcon icon="tabler-search" class="me-2" />
            Buscar
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>
    
    <VDivider />
    
    <VCardActions class="pa-4 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtros
      </VBtn>
      
      <VSpacer />
      
      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        :loading="props.addOfferLoading"
        :disabled="props.addOfferLoading"
        @click="emit('add-expiration-offer')"
      >
        Añadir Oferta
      </VBtn>
    </VCardActions>
  </VCard>
</template>
