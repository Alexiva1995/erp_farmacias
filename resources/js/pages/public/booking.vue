<script setup>
definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

import { ref, onMounted, computed, watch } from 'vue'
import axios from '@axios'
import { toast } from '@/plugins/sweetalert'

// Estado del componente
const getLocalDateString = () => {
  const d = new Date()
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}
const selectedDate = ref(getLocalDateString())
const courtsData = ref([])
const loading = ref(false)
const error = ref(null)

const showEarlierHours = ref(false)

// Diálogo de reserva
const isDialogOpen = ref(false)
const formRef = ref(null)
const reservationForm = ref({
  court_id: null,
  court_name: '',
  date: '',
  start_time: '',
  end_time: '',
  duration: 1,
  client_id: null,
  identification: '',
  client_name: '',
  client_whatsapp: '',
})

// Normalizar teléfono venezolano sobre la marcha al escribir
const normalizePhoneInput = (val) => {
  if (!val) return ''
  let clean = val.replace(/[^0-9]/g, '')
  
  if (clean.startsWith('0') && clean.length === 11) {
    clean = '58' + clean.substring(1)
  } else if (clean.length === 10 && clean.startsWith('4')) {
    clean = '58' + clean
  }
  return clean
}

// Watcher para normalizar el telefono en tiempo real
watch(() => reservationForm.value.client_whatsapp, (newVal) => {
  if (newVal) {
    const normalized = normalizePhoneInput(newVal)
    if (normalized !== newVal && normalized.length >= 10) {
      reservationForm.value.client_whatsapp = normalized
    }
  }
})

// Watcher para autocompletar cliente por Cédula/RIF
watch(() => reservationForm.value.identification, async (newVal) => {
  if (!newVal) return
  
  const cleanCedula = newVal.replace(/[^0-9]/g, '')
  if (cleanCedula.length >= 6) {
    try {
      const response = await axios.get(`/public/clients/identification/${cleanCedula}`)
      if (response.data && response.data.data) {
        const client = response.data.data
        // Autocompletar nombre completo del cliente
        const fullName = `${client.name || ''} ${client.last_name || ''}`.trim()
        reservationForm.value.client_name = fullName
        
        // Autocompletar teléfono/whatsapp
        if (client.phone) {
          reservationForm.value.client_whatsapp = client.phone
        }
        
        // Asociar el ID del cliente
        reservationForm.value.client_id = client.id
      }
    } catch (e) {
      // Si no se encuentra, resetear los campos del cliente para que escriba de nuevo o los ingrese por primera vez
      reservationForm.value.client_name = ''
      reservationForm.value.client_whatsapp = ''
      reservationForm.value.client_id = null
    }
  }
})

// Reglas de validación
const rules = {
  required: value => !!value || 'Este campo es obligatorio.',
  whatsapp: value => {
    return value.length >= 10 || 'Ingresa un número de teléfono válido.'
  }
}

// Opciones de duración (medias horas hasta las 2 horas, luego horas enteras hasta las 10)
const durationOptions = computed(() => {
  const options = [
    { title: '1 Hora', value: 1 },
    { title: '1.5 Horas', value: 1.5 },
    { title: '2 Horas', value: 2 }
  ]
  for (let d = 3; d <= 10; d++) {
    options.push({
      title: `${d} Horas`,
      value: d
    })
  }
  return options
})

// Cargar disponibilidad desde la API pública
const fetchAvailability = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await axios.get('/public/reservations', {
      params: { date: selectedDate.value }
    })
    courtsData.value = response.data.data
  } catch (err) {
    error.value = err.response?.data?.message || 'Error al cargar la disponibilidad'
  } finally {
    loading.value = false
  }
}

// Recargar cuando cambia la fecha
watch(selectedDate, () => {
  fetchAvailability()
})



// Auxiliares de tiempo
const timeToMin = (t) => {
  const [h, m] = t.split(':').map(Number)
  return h * 60 + m
}

const minToTime = (m) => {
  if (m >= 1440) return '24:00'
  const h = Math.floor(m / 60)
  const min = m % 60
  return `${String(h).padStart(2, '0')}:${String(min).padStart(2, '0')}`
}

const formatAmPm = (timeStr) => {
  if (timeStr === '24:00') return '12:00 AM'
  const [hStr, mStr] = timeStr.split(':')
  let h = parseInt(hStr)
  const ampm = h >= 12 ? 'PM' : 'AM'
  h = h % 12
  h = h ? h : 12
  return `${String(h).padStart(2, '0')}:${mStr} ${ampm}`
}

const calculateEndTime = (startTimeStr, durationHours) => {
  if (!startTimeStr) return ''
  const [h, m] = startTimeStr.split(':').map(Number)
  const totalMinutes = h * 60 + m + durationHours * 60
  return minToTime(totalMinutes)
}

watch(() => reservationForm.value.duration, (newVal) => {
  if (reservationForm.value.start_time) {
    reservationForm.value.end_time = calculateEndTime(reservationForm.value.start_time, parseFloat(newVal))
  }
})

// Generar slots de 1 hora libre
const getCourtSlots = (courtData) => {
  const baseStarts = showEarlierHours.value 
    ? ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00']
    : ['17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00']

  const occupied = []
  
  courtData.fixed_schedules.forEach(fs => {
    occupied.push({
      start: fs.start_time.substring(0, 5),
      end: fs.end_time.substring(0, 5) === '00:00' ? '24:00' : fs.end_time.substring(0, 5)
    })
  })

  courtData.reservations.forEach(res => {
    occupied.push({
      start: res.start_time.substring(0, 5),
      end: res.end_time.substring(0, 5) === '00:00' ? '24:00' : res.end_time.substring(0, 5)
    })
  })

  occupied.sort((a, b) => a.start.localeCompare(b.start))

  const slots = []
  let currentTime = baseStarts[0]
  const maxLimitMin = timeToMin(baseStarts[baseStarts.length - 1]) + 60
  let safetyCounter = 0

  while (timeToMin(currentTime) < maxLimitMin && safetyCounter < 100) {
    safetyCounter++
    const currentMin = timeToMin(currentTime)

    // Buscar si hay algún bloque ocupado que comience exactamente en currentTime o se solape
    const activeOcc = occupied.find(occ => {
      const occStart = timeToMin(occ.start)
      const occEnd = occ.end === '24:00' ? 1440 : timeToMin(occ.end)
      return occStart <= currentMin && occEnd > currentMin
    })

    if (activeOcc) {
      // Avanzar currentTime al final del bloque ocupado (no mostramos las horas ocupadas al público)
      const occEndMin = activeOcc.end === '24:00' ? 1440 : timeToMin(activeOcc.end)
      if (occEndMin > currentMin) {
        currentTime = activeOcc.end === '24:00' ? '24:00' : activeOcc.end
      } else {
        currentTime = minToTime(currentMin + 60)
      }
    } else {
      // Bloque libre de mínimo 1 hora
      const endMin = currentMin + 60
      const endTime = minToTime(endMin)
      
      const nextOcc = occupied.find(occ => {
        const occStart = timeToMin(occ.start)
        return occStart > currentMin && occStart < endMin
      })

      if (nextOcc) {
        const nextStartMin = timeToMin(nextOcc.start)
        if (nextStartMin > currentMin) {
          currentTime = nextOcc.start
        } else {
          currentTime = minToTime(currentMin + 60)
        }
    } else {
        const labelAmPm = `${formatAmPm(currentTime)} - ${formatAmPm(endTime)}`
        slots.push({
          label: labelAmPm,
          start: currentTime,
          end: endTime,
          type: 'free',
          statusLabel: 'Disponible'
        })
        currentTime = endTime
      }
    }
  }

  // Filtrar slots dinámicamente si la fecha seleccionada es HOY (según zona horaria local)
  const now = new Date()
  const todayStr = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0')
  
  if (selectedDate.value === todayStr) {
    const currentHour = now.getHours()
    const currentMin = now.getMinutes()
    const nowTotalMin = currentHour * 60 + currentMin

    return slots.filter(slot => {
      const slotStartMin = timeToMin(slot.start)
      // Ocultar si ya pasaron más de 15 minutos desde el inicio del bloque
      return (slotStartMin + 15) >= nowTotalMin
    })
  }

  return slots
}

// Abrir modal de reserva
const openBookingDialog = (court, slot) => {
  reservationForm.value = {
    court_id: court.id,
    court_name: court.name,
    date: selectedDate.value,
    start_time: slot.start,
    duration: 1,
    end_time: calculateEndTime(slot.start, 1),
    client_id: null,
    identification: '',
    client_name: '',
    client_whatsapp: '',
    request_weekly_fixed: false,
  }
  isDialogOpen.value = true
}

// Guardar reserva desde el link público
const isSuccessDialogOpen = ref(false)
const confirmedReservationData = ref(null)

// Registrar visita del usuario en la sección pública
const recordVisit = async () => {
  try {
    await axios.post('/public/visits')
  } catch (e) {
    console.error('Error al registrar métricas de visita:', e)
  }
}

const whatsappLink = computed(() => {
  if (!confirmedReservationData.value) return ''
  const court = confirmedReservationData.value.court_name
  const date = confirmedReservationData.value.date
  const time = `${formatAmPm(confirmedReservationData.value.start_time.substring(0, 5))} - ${formatAmPm(confirmedReservationData.value.end_time.substring(0, 5))}`
  const name = confirmedReservationData.value.client_name
  const whatsapp = confirmedReservationData.value.client_whatsapp
  const fixedText = confirmedReservationData.value.request_weekly_fixed ? "\n🔄 Solicitud adicional: Hora Fija Semanal" : ""
  
  const text = `Hola, quiero confirmar mi reserva:\n🏟️ Cancha: ${court}\n📅 Fecha: ${date}\n⏰ Horario: ${time}\n👤 Nombre: ${name}\n📱 Teléfono: ${whatsapp}${fixedText}`
  return `https://wa.me/584247423672?text=${encodeURIComponent(text)}`
})

const submitReservation = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  let finalEndTime = reservationForm.value.end_time
  if (finalEndTime === '24:00') {
    finalEndTime = '00:00'
  }

  loading.value = true
  try {
    const response = await axios.post('/public/reservations', {
      court_id: reservationForm.value.court_id,
      date: reservationForm.value.date,
      start_time: reservationForm.value.start_time,
      end_time: finalEndTime,
      client_name: reservationForm.value.client_name,
      client_whatsapp: reservationForm.value.client_whatsapp,
      client_id: reservationForm.value.client_id,
      identification: reservationForm.value.identification,
      request_weekly_fixed: reservationForm.value.request_weekly_fixed,
    })

    confirmedReservationData.value = { 
      ...reservationForm.value,
      id: response.data.data.id
    }
    toast.success(response.data.message || 'Reserva creada exitosamente.')
    isDialogOpen.value = false
    isSuccessDialogOpen.value = true
    await fetchAvailability()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Error al procesar la reserva.')
  } finally {
    loading.value = false
  }
}

// Confirmar la reserva en base de datos públicamente al ir a WhatsApp
const confirmAndGoToWhatsApp = async () => {
  if (!confirmedReservationData.value || !confirmedReservationData.value.id) return
  
  try {
    // Confirmar en el backend para disparar notificaciones de Telegram en tiempo real
    await axios.patch(`/public/reservations/${confirmedReservationData.value.id}/confirm`)
  } catch (e) {
    console.error('Error al pre-confirmar reserva:', e)
  }
  
  // Abrir enlace de WhatsApp en una nueva pestaña
  window.open(whatsappLink.value, '_blank', 'noopener,noreferrer')
  isSuccessDialogOpen.value = false
}

// Registrar visita del usuario cuando carga el sitio
onMounted(() => {
  fetchAvailability()
  recordVisit()
})

const formatPrice = (value) => {
  if (!value) return '0'
  return new Intl.NumberFormat('de-DE', { maximumFractionDigits: 0 }).format(parseFloat(value))
}
</script>

<template>
  <div class="public-booking-container py-6 px-4">
    <!-- Header público -->
    <div class="text-center mb-6">
      <img src="/logo.png" alt="Gol Club" class="gol-club-logo mb-2" />
      <h1 class="text-h5 font-weight-bold text-primary">⚽ Reservar Cancha - Gol Club</h1>
      
      <div class="d-flex justify-center gap-4 mt-2">
        <VBtn
          href="https://maps.app.goo.gl/WPtfBAvfDuZqhuDf9"
          target="_blank"
          rel="noopener noreferrer"
          color="primary"
          variant="tonal"
          size="small"
          prepend-icon="tabler-map-pin"
          class="rounded-pill mx-1"
        >
          Ubicación
        </VBtn>
        <VBtn
          href="https://www.instagram.com/golclub.sc/"
          target="_blank"
          rel="noopener noreferrer"
          color="error"
          variant="tonal"
          size="small"
          prepend-icon="tabler-brand-instagram"
          class="rounded-pill mx-1"
        >
          Instagram
        </VBtn>
      </div>
    </div>

    <!-- Filtro de Fecha -->
    <VRow justify="center" class="mb-4">
      <VCol cols="12" sm="6" md="4">
        <VTextField
          v-model="selectedDate"
          type="date"
          label="Selecciona una Fecha"
          prepend-inner-icon="tabler-calendar"
          variant="outlined"
          color="primary"
          density="comfortable"
        />
      </VCol>
    </VRow>

    <div class="text-center mb-4">
      <VBtn
        :color="showEarlierHours ? 'secondary' : 'primary'"
        variant="tonal"
        size="small"
        prepend-icon="tabler-clock"
        @click="showEarlierHours = !showEarlierHours"
      >
        {{ showEarlierHours ? 'Ocultar horas de la mañana' : 'Ver horas más tempranas' }}
      </VBtn>
    </div>

    <!-- Cargando -->
    <div v-if="loading && courtsData.length === 0" class="text-center my-8">
      <VProgressCircular indeterminate size="40" color="primary" />
      <p class="mt-2 text-caption text-grey">Buscando disponibilidad...</p>
    </div>

    <!-- Error -->
    <VAlert v-else-if="error" type="error" variant="tonal" class="mb-4">
      {{ error }}
    </VAlert>

    <!-- Grilla de Canchas -->
    <VRow v-else justify="center">
      <VCol
        v-for="item in courtsData"
        :key="item.court.id"
        cols="12"
        sm="6"
        md="5"
      >
        <VCard border class="mb-4 elevation-1">
          <VCardItem class="bg-grey-lighten-4 py-2">
            <div class="d-flex justify-space-between align-center">
              <VCardTitle class="text-subtitle-1 font-weight-bold text-grey-darken-3">
                ⚽ {{ item.court.name }}
              </VCardTitle>
              <VChip color="primary" size="x-small" variant="flat">
                {{ formatPrice(item.court.price) }} COP / hr
              </VChip>
            </div>
          </VCardItem>

          <VDivider />

          <!-- Lista de horas libres únicamente -->
          <VList class="px-2 py-1">
            <template v-if="getCourtSlots(item).length === 0">
              <VListItem class="text-center py-4 text-grey-darken-1 text-caption">
                No hay horas libres para esta fecha.
              </VListItem>
            </template>
            <template v-else>
              <VListItem
                v-for="slot in getCourtSlots(item)"
                :key="slot.start"
                class="border-b py-2 px-1"
              >
                <template #prepend>
                  <span class="text-caption font-weight-medium text-grey-darken-2">
                    {{ slot.label }}
                  </span>
                </template>
                <template #append>
                  <VBtn
                    color="primary"
                    variant="elevated"
                    size="x-small"
                    class="font-weight-bold rounded-pill"
                    prepend-icon="tabler-calendar-event"
                    @click="openBookingDialog(item.court, slot)"
                  >
                    Reservar
                  </VBtn>
                </template>
              </VListItem>
            </template>
          </VList>
        </VCard>
      </VCol>
    </VRow>

    <!-- Diálogo de reserva -->
    <VDialog v-model="isDialogOpen" max-width="450px">
      <VCard>
        <VCardTitle class="bg-primary text-white py-3">
          <span class="text-subtitle-1">🏟️ Confirmar Reserva</span>
        </VCardTitle>

        <VCardText class="py-4">
          <VForm ref="formRef">
            <VRow>
              <VCol cols="12" sm="6">
                <VTextField
                  :model-value="reservationForm.court_name"
                  label="Cancha"
                  readonly
                  disabled
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>
              <VCol cols="12" sm="6">
                <VTextField
                  :model-value="reservationForm.date"
                  label="Fecha"
                  readonly
                  disabled
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>
              <VCol cols="12" sm="6">
                <VSelect
                  v-model="reservationForm.duration"
                  :items="durationOptions"
                  item-title="title"
                  item-value="value"
                  label="Duración"
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>
              <VCol cols="12" sm="6">
                <VTextField
                  :model-value="`${reservationForm.start_time} - ${reservationForm.end_time}`"
                  label="Horario"
                  readonly
                  disabled
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>
              <VCol cols="12">
                <VTextField
                  v-model="reservationForm.identification"
                  label="Cédula de Identidad / RIF"
                  placeholder="Ej. 12345678"
                  required
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                  hint="Ingresa tu cédula para completar tus datos automáticamente"
                  persistent-hint
                />
              </VCol>
              <VCol cols="12">
                <VTextField
                  v-model="reservationForm.client_name"
                  label="Tu Nombre"
                  required
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>
              <VCol cols="12">
                <VTextField
                  v-model="reservationForm.client_whatsapp"
                  label="Tu WhatsApp"
                  placeholder="584121234567"
                  required
                  :rules="[rules.required, rules.whatsapp]"
                  variant="outlined"
                  density="comfortable"
                  hint="Código de país sin el símbolo (+)"
                  persistent-hint
                />
              </VCol>
              
              <!-- Checkbox para solicitar Hora Fija Semanal -->
              <VCol cols="12" class="pt-0">
                <VCheckbox
                  v-model="reservationForm.request_weekly_fixed"
                  color="primary"
                  hide-details
                  density="compact"
                >
                  <template #label>
                    <div class="text-caption text-grey-darken-3 font-weight-medium">
                      🔄 ¿Deseas solicitar este horario de forma fija todas las semanas?
                    </div>
                  </template>
                </VCheckbox>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VDivider />

        <VCardActions class="px-4 py-3 d-flex gap-4">
          <VBtn
            color="grey-darken-1"
            variant="outlined"
            class="flex-grow-1"
            @click="isDialogOpen = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            variant="flat"
            class="flex-grow-1"
            :loading="loading"
            @click="submitReservation"
          >
            Reservar Ahora
          </VBtn>
         </VCardActions>
      </VCard>
    </VDialog>

    <!-- Diálogo de Confirmación WhatsApp -->
    <VDialog v-model="isSuccessDialogOpen" max-width="450px" persistent>
      <VCard class="text-center py-4">
        <VCardText>
          <div class="mb-4 text-success">
            <VIcon size="64" icon="tabler-circle-check-filled" />
          </div>
          
          <h3 class="text-h6 font-weight-bold mb-2">🎉 ¡Reserva Registrada!</h3>
          <p class="text-body-2 text-grey-darken-1 mb-4">
            Para asegurar tu reserva, por favor presiona el siguiente botón para confirmarla a través de WhatsApp.
          </p>

          <VCard v-if="confirmedReservationData" variant="outlined" class="pa-3 mb-4 text-left bg-light">
            <div class="text-caption"><span class="font-weight-bold">Cancha:</span> {{ confirmedReservationData.court_name }}</div>
            <div class="text-caption"><span class="font-weight-bold">Fecha:</span> {{ confirmedReservationData.date }}</div>
            <div class="text-caption"><span class="font-weight-bold">Horario:</span> {{ confirmedReservationData.start_time }} - {{ confirmedReservationData.end_time }}</div>
            <div class="text-caption"><span class="font-weight-bold">Cliente:</span> {{ confirmedReservationData.client_name }}</div>
            <div class="text-caption"><span class="font-weight-bold">Teléfono:</span> {{ confirmedReservationData.client_whatsapp }}</div>
            <div v-if="confirmedReservationData.request_weekly_fixed" class="text-caption text-primary font-weight-bold mt-1">
              🔄 Solicitando Horario Fijo Semanal
            </div>
          </VCard>

          <div class="d-flex mt-4">
            <VBtn
              color="success"
              prepend-icon="tabler-brand-whatsapp"
              class="font-weight-bold flex-grow-1"
              @click="confirmAndGoToWhatsApp"
            >
              Confirmar
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.public-booking-container {
  max-width: 900px;
  margin: 0 auto;
}
.gol-club-logo {
  max-width: 100px;
  height: auto;
}
.border-b {
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}
</style>
