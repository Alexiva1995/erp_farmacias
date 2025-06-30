<script setup lang="js">
const props= defineProps({
  status: {type: Object, default: () => {}},
  titulo: {type: String, required: true},
  // tabla
  items: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  total: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  // search: { type: String, required: true },
})

const headers = [
  { title: 'Nombre', key: 'name', value: item => `${item.name} ${item.last_name}`, sortable: true, },
  { title: 'Identidad', key: 'identification', value: item => `${item.identification_type}${item.identification}`, sortable: true, },
];

const emit= defineEmits(["modalClose", 'mostrarFormulario',"update:options"])

function close(){
  props.status.statu=false
  props.status.titulo=""
  props.status.company={}
  props.status.clients=[]
  emit("modalClose")
}

function mostrarFormulario(){
  props.status.statu=false
  emit("mostrarFormulario")
}
</script>
<template>
  <VDialog :model-value="props.status.statu" max-width="800px" persistent>
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline">{{ props.titulo }}</span>
        <VSpacer />
        <VBtn icon variant="text" @click="close">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <div class="mb-3"></div>
      <VDataTableServer
        :headers="headers"
        :items-per-page="props.itemsPerPage"
        :items="props.items"
        :items-length="props.total"
        :loading="loading"
        :page="props.page"
        @update:options="(options) => emit('update:options', options)"
      />
      <div class="mb-3"></div>
      <VDivider />
      <VContainer> </VContainer>
      <VCardActions class="pa-4">
        <VBtn color="primary" variant="flat" @click="mostrarFormulario"
          >Agergar Cliente</VBtn
        >
      </VCardActions>
    </VCard>
  </VDialog>
</template>
