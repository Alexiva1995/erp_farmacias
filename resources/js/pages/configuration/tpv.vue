<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert";

const tpvMode = ref('complete')

const fetchSettings = async () => {
  try {
    const response = await axios.get('/general-settings')
    const settings = response.data.data
    tpvMode.value = settings.tpv_mode || 'complete'
  } catch (error) {
    console.error("Error cargando configuración del TPV:", error)
    toast.error("Error al cargar la configuración")
  }
}

const updateSettings = async () => {
  try {
    // Primero traer configuraciones actuales para no sobreescribir otros datos
    const response = await axios.get('/general-settings')
    const current = response.data.data

    await axios.post('/general-settings', {
      fiscal_mode: current.fiscal_mode,
      special_taxpayer_status: current.special_taxpayer_status,
      all_foreign_sales_spe: !!current.all_foreign_sales_spe,
      blind_cash_closure: !!current.blind_cash_closure,
      business_type: current.business_type,
      tpv_mode: tpvMode.value
    })
    toast.success("Configuración del TPV actualizada exitosamente")
  } catch (error) {
    console.error("Error al guardar:", error)
    toast.error("Error al actualizar la configuración")
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <VCard class="mb-6">
    <VCardItem class="py-4">
      <VCardTitle class="text-h6 mb-2"> Modalidad de Punto de Venta (TPV) </VCardTitle>
      <div class="text-body-2 text-medium-emphasis mb-4">
        Define cómo opera el Punto de Venta al momento de agregar productos a la orden y gestionar la información del cliente.
      </div>

      <VRadioGroup
        v-model="tpvMode"
        inline
        @update:model-value="updateSettings"
      >
        <VRadio 
          label="Completo (Estándar, con búsqueda de cliente y lotes)" 
          value="complete" 
          class="mr-6" 
        />
        <VRadio 
          label="Simple (Carga directa al añadir, sin requerir cédula o cliente)" 
          value="simple" 
        />
      </VRadioGroup>
    </VCardItem>
  </VCard>
</template>
