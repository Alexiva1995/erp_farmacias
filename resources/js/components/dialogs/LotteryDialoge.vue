<script setup lang="js">
const props= defineProps({
  modalFormulario: {type: Boolean, required: true},
  lista: {type: Array, default: () => []},
})

const emit= defineEmits(["modalClose"])

function close(){
  emit("modalClose",false)
}
</script>
<template>
  <VDialog :model-value="props.modalFormulario" max-width="800px" persistent>
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline">Loteria</span>
        <VSpacer />
        <VBtn icon variant="text" @click="close">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VList :items="props.lista" item-title="name" />
      <VDivider />
      <VCardActions>
        <VSpacer />
        <VContainer>
          <VRow justify="end">
            <VCol cols="12" sm="6" md="6" lg="6">
              <VBtn
                color="secondary"
                variant="outlined"
                @click="close"
                width="100%"
                >Cancelar</VBtn
              >
            </VCol>
          </VRow>
        </VContainer>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
