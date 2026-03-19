<script setup lang="js">
const props= defineProps({
  modalFormulario: {type: Boolean, required: true},
  titulo: {type: String, required: true},
  data: {type: Object, required: true},
})


const emit= defineEmits(["modalClose", 'save', 'clearErrorForm'])
function close(){
  emit("modalClose",false)
}
</script>
<template>
  <VDialog :model-value="props.modalFormulario" max-width="800px" persistent :fullscreen="$vuetify.display.xs">
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline">{{ props.titulo }}</span>
        <VSpacer />
        <VBtn icon variant="text" @click="close">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider class="mb-5" />
      <VImg
        class="mx-auto mb-5"
        :width="400"
        cover
        :src="props.data.order.url_recipe"
      />

      <VDivider class="" />
      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="close"
          class="w-50 mr-4 pa"
        >
          Cancelar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
