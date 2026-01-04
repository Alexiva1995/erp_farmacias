<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { toast } from "@/plugins/sweetalert";

const fiscalMode = ref('')
const specialStatus = ref('')

const configFiscal = [
  { label: "Demo", value: "demo" },
  { label: "Activa", value: "activa" },
]

const specialTaxpayerOptions = [
  { label: "Desactivada", value: "desactivada" },
  { label: "Activa", value: "activa" },
]

const fetchSettings = async () => {
  try {
    const response = await axios.get('/api/general-settings')
    fiscalMode.value = response.data.fiscal_mode
    specialStatus.value = response.data.special_taxpayer_status
  } catch (error) {
    console.error("Error cargando configuración:", error)
    toast.success("Error al cargar la configuración")
  }
}

const updateSettings = async () => {
  try {
    await axios.post('/api/general-settings', {
      fiscal_mode: fiscalMode.value,
      special_taxpayer_status: specialStatus.value
    })
    toast.success("Configuración actualizada existosamente")
  } catch (error) {
    console.error("Error al guardar:", error)
    toast.success("Error al actualizada la configuración")
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <VCard class="mb-6">
    <VCardItem class="py-2">
      <VCardTitle class="text-h6"> Configuración Fiscal </VCardTitle>
      <VRadioGroup
        v-model="fiscalMode"
        inline
        @update:model-value="updateSettings"
      >
        <VRadio   
          v-for="item in configFiscal"
          :key="item.value"
          :label="item.label"
          :value="item.value"
        />
      </VRadioGroup>
    </VCardItem>

    <VCardItem class="py-2">
      <VCardTitle class="text-h6">Sujeto pasivo especial </VCardTitle>
      <VRadioGroup
        v-model="specialStatus"
        inline
        @update:model-value="updateSettings"
      >
        <VRadio   
          v-for="item in specialTaxpayerOptions"
          :key="item.value"
          :label="item.label"
          :value="item.value"
        />
      </VRadioGroup>
    </VCardItem>
  </VCard>
</template>
