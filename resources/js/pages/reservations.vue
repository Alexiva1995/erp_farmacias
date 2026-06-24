<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue'
import { useReservationStore } from '@/stores/reservationStore'
import { toast, Swal } from '@/plugins/sweetalert'
import axios from '@/plugins/axios'

const store = useReservationStore()

const showEarlierHours = ref(false)

const formatAmPm = (timeStr) => {
  if (timeStr === '24:00') return '12:00 AM'
  const [hStr, mStr] = timeStr.split(':')
  let h = parseInt(hStr)
  const ampm = h >= 12 ? 'PM' : 'AM'
  h = h % 12
  h = h ? h : 12
  return `${String(h).padStart(2, '0')}:${mStr} ${ampm}`
}

const timeToMin = (t) => {
  if (!t) return 0
  const matches = t.match(/\d+/g)
  if (!matches) return 0
  let h = parseInt(matches[0])
  let m = matches[1] ? parseInt(matches[1]) : 0
  
  if (t.toLowerCase().includes('pm') && h < 12) {
    h += 12
  }
  if (t.toLowerCase().includes('am') && h === 12) {
    h = 0
  }
  
  return h * 60 + m
}

// Generar slots dinámicos de 1 hora por cancha basándose en reservas y fijos activos para evitar fraccionamiento y solapamientos
const getCourtSlots = (courtData) => {
  const baseStarts = showEarlierHours.value 
    ? ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00']
    : ['17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00']

  // Unificar reservas y fijos ordenados
  const occupied = []
  
  courtData.fixed_schedules.forEach(fs => {
    occupied.push({
      type: 'fixed',
      start: fs.start_time.substring(0, 5),
      end: fs.end_time.substring(0, 5) === '00:00' ? '24:00' : fs.end_time.substring(0, 5),
      label: `Fijo: ${fs.client_name}`,
      raw: fs
    })
  })

  courtData.reservations.forEach(res => {
    occupied.push({
      type: res.status,
      start: res.start_time.substring(0, 5),
      end: res.end_time.substring(0, 5) === '00:00' ? '24:00' : res.end_time.substring(0, 5),
      label: res.status === 'verified' ? `Reservado: ${res.client_name}` : `Pendiente: ${res.client_name}`,
      raw: res
    })
  })

  // Ordenar por hora de inicio
  occupied.sort((a, b) => a.start.localeCompare(b.start))

  const slots = []
  let currentTime = baseStarts[0]
  const lastLimit = '24:00'

  const minToTime = (m) => {
    if (m >= 1440) return '24:00'
    const h = Math.floor(m / 60)
    const min = m % 60
    return `${String(h).padStart(2, '0')}:${String(min).padStart(2, '0')}`
  }

  // Llevar registro de los IDs de ocupaciones ya renderizados para no duplicarlos
  const renderedIds = new Set()

  let safetyCounter = 0
  const maxLimitMin = timeToMin(baseStarts[baseStarts.length - 1]) + 60



  while (safetyCounter < 100) {
    safetyCounter++
    const currentMin = timeToMin(currentTime)
    if (currentMin >= maxLimitMin) {
      break
    }

    // Buscar si hay algún bloque ocupado que comience exactamente en currentTime o se solape
    const activeOcc = occupied.find(occ => {
      const occStart = timeToMin(occ.start)
      const occEnd = occ.end === '24:00' ? 1440 : timeToMin(occ.end)
      return occStart <= currentMin && occEnd > currentMin
    })

    if (activeOcc) {
      const occKey = `${activeOcc.type}-${activeOcc.raw.id}`
      const occEndMin = activeOcc.end === '24:00' ? 1440 : timeToMin(activeOcc.end)
      
      if (!renderedIds.has(occKey)) {
        // Primera vez que vemos este bloque ocupado: lo agregamos completo en una sola línea
        const labelAmPm = `${formatAmPm(activeOcc.start)} - ${formatAmPm(activeOcc.end)}`
        slots.push({
          label: labelAmPm,
          start: activeOcc.start,
          end: activeOcc.end,
          type: activeOcc.type,
          statusLabel: activeOcc.label,
          data: activeOcc.raw
        })
        renderedIds.add(occKey)
      }
      
      // Avanzar currentTime directamente al final del bloque ocupado.
      if (occEndMin > currentMin) {
        currentTime = activeOcc.end === '24:00' ? '24:00' : activeOcc.end
      } else {
        currentTime = minToTime(currentMin + 60)
      }
    } else {
      // Bloque libre de mínimo 1 hora (60 minutos)
      const endMin = currentMin + 60
      const endTime = minToTime(endMin)
      
      // Validar si choca con el inicio de alguna reserva futura dentro de la hora
      const nextOcc = occupied.find(occ => {
        const occStart = timeToMin(occ.start)
        return occStart > currentMin && occStart < endMin
      })

      if (nextOcc) {
        // Si hay una reserva futura que choca antes de completar la hora, avanzamos currentTime 
        // directamente al inicio de esa ocupación sin generar un botón de reserva inútil de menos de 1 hora
        const nextStartMin = timeToMin(nextOcc.start)
        if (nextStartMin > currentMin) {
          currentTime = nextOcc.start
        } else {
          currentTime = minToTime(currentMin + 60)
        }
      } else {
        // Bloque libre completo de 1 hora
        const labelAmPm = `${formatAmPm(currentTime)} - ${formatAmPm(endTime)}`
        slots.push({
          label: labelAmPm,
          start: currentTime,
          end: endTime,
          type: 'free',
          statusLabel: 'Disponible'
        })
        // Avanzar el reloj 1 hora completa (endTime) para mantener bloques limpios secuenciales
        currentTime = endTime
      }
    }
  }

  // 3. Filtrar slots dinámicamente si la fecha seleccionada es HOY
  const now = new Date()
  const todayStr = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0')
  
  if (store.selectedDate === todayStr) {
    const currentHour = now.getHours()
    const currentMin = now.getMinutes()
    const nowTotalMin = currentHour * 60 + currentMin

    return slots.filter(slot => {
      const slotStartMin = timeToMin(slot.start)
      
      // Si la hora de inicio del bloque ya pasó por más de 15 minutos con respecto a la hora real actual, se oculta.
      // Permitimos ver bloques que inician en el futuro o que iniciaron hace menos de 15 minutos.
      return (slotStartMin + 15) >= nowTotalMin
    })
  }

  return slots
}

// Comprobar ocupación en un slot específico (obsoleto con getCourtSlots pero mantenido por compatibilidad)
const getSlotStatus = (courtData, slot) => {
  return { type: slot.type, label: slot.statusLabel, color: slot.type === 'fixed' ? 'light-blue' : (slot.type === 'verified' ? 'success' : (slot.type === 'pending' ? 'info' : 'primary')), fixedSchedule: slot.data, reservation: slot.data }
}

// Modal y formulario de reserva
const isDialogOpen = ref(false)
const formRef = ref(null)
const reservationForm = ref({
  court_id: null,
  court_name: '',
  date: '',
  start_time: '',
  end_time: '',
  duration: 1,
  client_name: '',
  client_whatsapp: '',
})

// Calcular hora de fin basado en inicio y duración
const calculateEndTime = (startTimeStr, durationHours) => {
  if (!startTimeStr) return ''
  const [h, m] = startTimeStr.split(':').map(Number)
  const totalMinutes = h * 60 + m + durationHours * 60
  const endH = Math.floor(totalMinutes / 60) % 24
  const endM = totalMinutes % 60
  const pad = (n) => String(n).padStart(2, '0')
  return `${pad(endH)}:${pad(endM)}`
}

// Watcher para calcular hora de fin al cambiar duración
watch(() => reservationForm.value.duration, (newVal) => {
  if (reservationForm.value.start_time) {
    reservationForm.value.end_time = calculateEndTime(reservationForm.value.start_time, parseFloat(newVal))
  }
})

// Reglas de validación
const rules = {
  required: value => !!value || 'Este campo es obligatorio.',
  whatsapp: value => {
    const pattern = /^\+?[0-9]{8,15}$/
    return pattern.test(value) || 'WhatsApp no válido (debe tener entre 8 y 15 dígitos).'
  }
}

// Cargar disponibilidad al iniciar
onMounted(() => {
  store.fetchAvailability()
  store.setupEchoListener()
})

// Desconectar listener al desmontar
onUnmounted(() => {
  store.cleanupEchoListener()
})

// Recargar al cambiar de fecha
watch(() => store.selectedDate, () => {
  store.fetchAvailability()
})

// Abrir diálogo de reserva
const openBookingDialog = (court, slot) => {
  reservationForm.value = {
    court_id: court.id,
    court_name: court.name,
    date: store.selectedDate,
    start_time: slot.start,
    duration: 1, // Por defecto 1 hora
    end_time: calculateEndTime(slot.start, 1),
    client_name: '',
    client_whatsapp: '',
  }
  isDialogOpen.value = true
}

// Guardar reserva
const submitReservation = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  let finalEndTime = reservationForm.value.end_time
  if (finalEndTime === '24:00') {
    finalEndTime = '00:00'
  }

  try {
    const response = await store.createReservation({
      court_id: reservationForm.value.court_id,
      date: reservationForm.value.date,
      start_time: reservationForm.value.start_time,
      end_time: finalEndTime,
      client_name: reservationForm.value.client_name,
      client_whatsapp: reservationForm.value.client_whatsapp,
    })

    toast.success(response.message || 'Pre-reserva creada con éxito. Confirma desde WhatsApp.')
    isDialogOpen.value = false
  } catch (error) {
    toast.error(error.message || 'Error al intentar crear la reserva.')
  }
}



const formatPrice = (value) => {
  if (!value) return '0'
  return new Intl.NumberFormat('de-DE', { maximumFractionDigits: 0 }).format(parseFloat(value))
}

const getWhatsAppLink = (reservation) => {
  if (!reservation) return '#'
  const courtName = reservation.court_name || 'Cancha'
  const dateStr = reservation.date || ''
  const timeStr = `${reservation.start_time} - ${reservation.end_time}`
  const baseConfirmUrl = `${window.location.origin}/api/public/reservations/confirm-direct/${reservation.id}`
  const text = `Hola ${reservation.client_name}, confirma tu reserva para la cancha '${courtName}' el día ${dateStr} de ${timeStr} ingresando aquí: ${baseConfirmUrl}`
  return `https://wa.me/${reservation.client_whatsapp}?text=${encodeURIComponent(text)}`
}

const isFixedScheduleDialogOpen = ref(false)
const fixedScheduleFormRef = ref(null)
const editingFixedScheduleId = ref(null)
const fixedScheduleForm = ref({
  court_id: null,
  day_of_week: null,
  start_time: '',
  end_time: '',
  duration: 1,
  client_name: '',
  client_whatsapp: '',
})

// Recalcular hora de fin para el horario fijo dinámicamente
watch(
  () => [fixedScheduleForm.value.start_time, fixedScheduleForm.value.duration],
  ([start, duration]) => {
    if (start) {
      fixedScheduleForm.value.end_time = calculateEndTime(start, parseFloat(duration))
    }
  }
)

const daysOptions = [
  { label: 'Lunes', value: 1 },
  { label: 'Martes', value: 2 },
  { label: 'Miércoles', value: 3 },
  { label: 'Jueves', value: 4 },
  { label: 'Viernes', value: 5 },
  { label: 'Sábado', value: 6 },
  { label: 'Domingo', value: 7 },
]

const openFixedScheduleDialog = () => {
  editingFixedScheduleId.value = null
  fixedScheduleForm.value = {
    court_id: store.courtsData[0]?.court.id || null,
    day_of_week: 1,
    start_time: '16:00',
    end_time: '17:00',
    duration: 1,
    client_name: '',
    client_whatsapp: '',
  }
  isFixedScheduleDialogOpen.value = true
}

const openEditFixedScheduleDialog = (fixedSchedule) => {
  editingFixedScheduleId.value = fixedSchedule.id
  const start = fixedSchedule.start_time.substring(0, 5)
  const end = fixedSchedule.end_time.substring(0, 5)
  
  // Determinar la duración en horas
  let duration = 1
  if (start && end) {
    const [sH, sM] = start.split(':').map(Number)
    let [eH, eM] = end.split(':').map(Number)
    if (eH === 0 && eM === 0) eH = 24
    const diffMin = (eH * 60 + eM) - (sH * 60 + sM)
    duration = diffMin / 60
  }

  fixedScheduleForm.value = {
    court_id: fixedSchedule.court_id,
    day_of_week: fixedSchedule.day_of_week,
    start_time: start,
    end_time: end,
    duration: duration,
    client_name: fixedSchedule.client_name,
    client_whatsapp: fixedSchedule.client_whatsapp || '',
  }
  isFixedScheduleDialogOpen.value = true
}

const submitFixedSchedule = async () => {
  const { valid } = await fixedScheduleFormRef.value.validate()
  if (!valid) return

  let finalEndTime = fixedScheduleForm.value.end_time
  if (finalEndTime === '24:00') {
    finalEndTime = '00:00'
  }

  try {
    const payload = {
      court_id: fixedScheduleForm.value.court_id,
      day_of_week: fixedScheduleForm.value.day_of_week,
      start_time: fixedScheduleForm.value.start_time,
      end_time: finalEndTime,
      client_name: fixedScheduleForm.value.client_name,
      client_whatsapp: fixedScheduleForm.value.client_whatsapp,
    }

    let response
    if (editingFixedScheduleId.value) {
      response = await store.updateFixedSchedule(editingFixedScheduleId.value, payload)
      toast.success(response.message || 'Horario fijo actualizado correctamente.')
    } else {
      response = await store.createFixedSchedule(payload)
      toast.success(response.message || 'Horario fijo configurado correctamente.')
    }
    
    isFixedScheduleDialogOpen.value = false
  } catch (error) {
    toast.error(error.message || 'Error al guardar el horario fijo.')
  }
}

const confirmDeleteFixedSchedule = (id) => {
  Swal.fire({
    title: '¿Cancelar Horario Fijo?',
    text: 'Se cancelará el horario fijo únicamente para la fecha seleccionada. Si se cancela 4 veces, se eliminará permanentemente.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#e00073',
    cancelButtonColor: '#7a7a7a',
    confirmButtonText: 'Sí, cancelar',
    cancelButtonText: 'No, mantener'
  }).then(async (result) => {
    if (result.isConfirmed) {
      try {
        await store.deleteFixedSchedule(id, store.selectedDate);
        toast.success('Horario fijo cancelado para esta semana.');
      } catch (error) {
        toast.error(error.message || 'Error al cancelar el horario fijo.');
      }
    }
  })
}

const confirmDeleteReservation = (id) => {
  Swal.fire({
    title: '¿Cancelar Reserva?',
    text: '¿Estás seguro de que deseas cancelar esta reserva?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#e00073',
    cancelButtonColor: '#7a7a7a',
    confirmButtonText: 'Sí, cancelar',
    cancelButtonText: 'No, mantener'
  }).then(async (result) => {
    if (result.isConfirmed) {
      try {
        await store.deleteReservation(id);
        toast.success('Reserva cancelada correctamente.');
      } catch (error) {
        toast.error(error.message || 'Error al cancelar la reserva.');
      }
    }
  })
}

const copyWeeklyReservations = async () => {
  try {
    const today = new Date()
    const currentDayOfWeek = today.getDay()
    
    // Días que faltan hasta el domingo (0)
    const daysToSunday = currentDayOfWeek === 0 ? 0 : 7 - currentDayOfWeek
    
    const dates = []
    for (let i = 0; i <= daysToSunday; i++) {
      const d = new Date(today)
      d.setDate(today.getDate() + i)
      dates.push(d.toISOString().split('T')[0])
    }

    toast.info('Consultando y recopilando reservas...')

    // Obtener reservas de todos los días en paralelo
    const requests = dates.map(dateStr => 
      axios.get('/reservations', { params: { date: dateStr } })
        .then(res => ({ date: dateStr, data: res.data.data }))
    )

    const results = await Promise.all(requests)
    
    let message = `📋 *RESERVAS GOL CLUB*\n`
    message += `Desde hoy hasta el domingo ${dates[dates.length - 1].split('-').reverse().join('/')}\n\n`

    let totalReservations = 0

    results.forEach(day => {
      const [y, m, d] = day.date.split('-')
      const formattedDate = `${d}/${m}/${y}`
      
      let dayText = `📅 *${formattedDate}*\n`
      let hasReservationsForDay = false

      day.data.forEach(courtItem => {
        const courtName = courtItem.court.name
        const dayResList = courtItem.reservations || []
        const dayFixedList = courtItem.fixed_schedules || []

        if (dayResList.length > 0 || dayFixedList.length > 0) {
          dayText += `  🏟️ *${courtName}*:\n`
        }

        const combinedList = []
        
        dayFixedList.forEach(fixed => {
          combinedList.push({
            start: fixed.start_time.substring(0, 5),
            end: fixed.end_time.substring(0, 5),
            label: `Fijo: ${fixed.client_name}`
          })
        })

        dayResList.forEach(res => {
          const statusIcon = res.status === 'verified' ? '✅' : '⏳'
          combinedList.push({
            start: res.start_time.substring(0, 5),
            end: res.end_time.substring(0, 5),
            label: `${res.client_name} (${statusIcon})`
          })
        })

        // Ordenar cronológicamente (más temprano primero) usando minutos enteros
        combinedList.sort((a, b) => timeToMin(a.start) - timeToMin(b.start))

        combinedList.forEach(item => {
          const start = formatAmPm(item.start)
          const end = formatAmPm(item.end)
          dayText += `    • ${start} a ${end} - ${item.label}\n`
          hasReservationsForDay = true
          totalReservations++
        })
      })

      if (hasReservationsForDay) {
        message += dayText + `\n`
      }
    })

    if (totalReservations === 0) {
      message += `No hay reservas registradas en este período.\n`
    }

    await navigator.clipboard.writeText(message)
    toast.success('¡Reservas semanales copiadas al portapapeles!')
  } catch (err) {
    console.error(err)
    toast.error('Error al generar o copiar las reservas semanales.')
  }
}
</script>

<template>
  <VContainer class="py-6">
    <!-- Navbar de Filtros y Cabecera Unificada -->
    <VCard class="mb-6 px-4 py-3" elevation="1" border>
      <div class="d-flex flex-wrap align-center justify-space-between gap-4">
        <!-- Título y Subtítulo -->
        <div class="d-flex align-center gap-3">
          <h1 class="text-h5 font-weight-bold text-primary mb-0 d-flex align-center">
            ⚽ <span class="ml-2">Reservas - Gol Club</span>
          </h1>
        </div>

        <!-- Filtros y Acciones -->
        <div class="d-flex flex-wrap align-center gap-3 ml-auto" style="flex: 1; justify-content: flex-end; min-width: 300px;">
          <!-- Selector de Fecha -->
          <div style="width: 200px;">
            <VTextField
              v-model="store.selectedDate"
              type="date"
              label="Seleccionar Fecha"
              prepend-inner-icon="tabler-calendar"
              variant="outlined"
              color="primary"
              density="compact"
              hide-details
            />
          </div>

          <!-- Botones de Acción -->
          <VBtn
            :color="showEarlierHours ? 'secondary' : 'primary'"
            variant="tonal"
            density="comfortable"
            prepend-icon="tabler-clock"
            @click="showEarlierHours = !showEarlierHours"
          >
            {{ showEarlierHours ? 'Ocultar Mañana' : 'Ver Mañana' }}
          </VBtn>

          <VBtn
            color="secondary"
            variant="elevated"
            density="comfortable"
            prepend-icon="tabler-calendar-plus"
            @click="openFixedScheduleDialog"
          >
            Horario Fijo
          </VBtn>

          <VBtn
            color="success"
            variant="elevated"
            density="comfortable"
            prepend-icon="tabler-copy"
            @click="copyWeeklyReservations"
          >
            Copiar Semana
          </VBtn>
        </div>
      </div>
    </VCard>

    <!-- Estado de carga -->
    <div v-if="store.loading && store.courtsData.length === 0" class="text-center my-12">
      <VProgressCircular indeterminate size="64" color="primary" />
      <p class="mt-4 text-grey">Consultando canchas y disponibilidad...</p>
    </div>

    <!-- Mensaje de error -->
    <VAlert v-else-if="store.error" type="error" variant="tonal" class="mb-6">
      {{ store.error }}
    </VAlert>

    <!-- Grilla de Canchas y Horarios -->
    <VRow v-else>
      <VCol
        v-for="item in store.courtsData"
        :key="item.court.id"
        cols="12"
        md="6"
      >
        <VCard border class="mb-6 elevation-1">
          <VCardItem class="bg-grey-lighten-4 py-3">
            <div class="d-flex justify-space-between align-center">
              <VCardTitle class="text-h6 font-weight-bold text-grey-darken-3">
                ⚽ {{ item.court.name }}
              </VCardTitle>
              <VChip color="primary" size="small" variant="flat" class="ml-4">
                {{ formatPrice(item.court.price) }} COP / hora
              </VChip>
            </div>
          </VCardItem>

          <VDivider />

          <!-- Listado de horarios -->
          <VList class="px-3 py-2">
            <VListItem
              v-for="slot in getCourtSlots(item)"
              :key="slot.start"
              class="border-b py-3 px-2"
            >
              <template #prepend>
                <div class="d-flex flex-column" style="width: 150px">
                  <span class="font-weight-medium text-body-2 text-grey-darken-2">
                    {{ slot.label }}
                  </span>
                </div>
              </template>

              <!-- Botón o estado de reserva -->
              <template #append>
                <!-- Disponible -->
                <VBtn
                  v-if="getSlotStatus(item, slot).type === 'free'"
                  color="primary"
                  variant="elevated"
                  size="small"
                  elevation="3"
                  class="font-weight-bold px-4 rounded-pill text-uppercase letter-spacing-1"
                  prepend-icon="tabler-calendar-event"
                  @click="openBookingDialog(item.court, slot)"
                >
                  Reservar
                </VBtn>

                <!-- Fijo u Ocupado -->
                <div v-else class="d-flex align-center">
                  <!-- Si es fijo -->
                  <template v-if="getSlotStatus(item, slot).type === 'fixed'">
                    <VChip
                      color="light-blue"
                      variant="flat"
                      size="small"
                      class="font-weight-medium mr-2 text-white"
                    >
                      {{ getSlotStatus(item, slot).label }}
                    </VChip>
                    <VBtn
                      color="primary"
                      size="x-small"
                      icon="tabler-edit"
                      variant="elevated"
                      elevation="1"
                      class="mr-2"
                      title="Editar Horario Fijo"
                      @click="openEditFixedScheduleDialog(getSlotStatus(item, slot).fixedSchedule)"
                    />
                    <VBtn
                      color="error"
                      size="x-small"
                      icon="tabler-trash"
                      variant="elevated"
                      elevation="1"
                      title="Eliminar Horario Fijo"
                      @click="confirmDeleteFixedSchedule(getSlotStatus(item, slot).fixedSchedule.id)"
                    />
                  </template>

                  <!-- Si es reservado verificado -->
                  <template v-if="getSlotStatus(item, slot).type === 'verified'">
                    <VChip
                      color="success"
                      variant="flat"
                      size="small"
                      class="font-weight-medium mr-2"
                    >
                      {{ getSlotStatus(item, slot).label }}
                    </VChip>
                    <VBtn
                      color="error"
                      size="x-small"
                      icon="tabler-trash"
                      variant="elevated"
                      elevation="1"
                      title="Cancelar Reserva"
                      @click="confirmDeleteReservation(getSlotStatus(item, slot).reservation.id)"
                    />
                  </template>

                  <!-- Si es pendiente -->
                  <template v-if="getSlotStatus(item, slot).type === 'pending'">
                    <VChip
                      color="warning"
                      variant="flat"
                      size="small"
                      class="font-weight-medium mr-2"
                    >
                      {{ getSlotStatus(item, slot).label }}
                    </VChip>
                    <VBtn
                      color="success"
                      size="x-small"
                      icon="tabler-brand-whatsapp"
                      variant="elevated"
                      elevation="1"
                      title="Enviar link de confirmación por WhatsApp"
                      class="mr-2"
                      :href="getWhatsAppLink(getSlotStatus(item, slot).reservation)"
                      target="_blank"
                    />
                    <VBtn
                      color="error"
                      size="x-small"
                      icon="tabler-trash"
                      variant="elevated"
                      elevation="1"
                      title="Cancelar Reserva"
                      @click="confirmDeleteReservation(getSlotStatus(item, slot).reservation.id)"
                    />
                  </template>
                </div>
              </template>
            </VListItem>
          </VList>
        </VCard>
      </VCol>
    </VRow>

    <!-- Diálogo de reserva -->
    <VDialog v-model="isDialogOpen" max-width="500px">
      <VCard>
        <VCardTitle class="bg-primary text-white py-4">
          <span class="text-h6">🏟️ Nueva Reserva</span>
        </VCardTitle>

        <VCardText class="py-6">
          <VForm ref="formRef">
            <VRow>
              <VCol cols="12">
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
                  :items="[
                    { title: '1 Hora', value: 1 },
                    { title: '1.5 Horas', value: 1.5 },
                    { title: '2 Horas', value: 2 }
                  ]"
                  label="Duración"
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>
              <VCol cols="12" sm="6">
                <VTextField
                  :model-value="`${reservationForm.start_time} - ${reservationForm.end_time}`"
                  label="Horario Reservado"
                  readonly
                  disabled
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>
              <VCol cols="12">
                <VTextField
                  v-model="reservationForm.client_name"
                  label="Nombre del Cliente"
                  required
                  :rules="[rules.required]"
                  variant="outlined"
                  color="primary"
                  density="comfortable"
                />
              </VCol>
              <VCol cols="12">
                <VTextField
                  v-model="reservationForm.client_whatsapp"
                  label="WhatsApp (ej. 584121234567)"
                  required
                  placeholder="584121234567"
                  :rules="[rules.required, rules.whatsapp]"
                  variant="outlined"
                  color="primary"
                  density="comfortable"
                  hint="Ingresa el número con el código de país, sin espacios ni símbolos (+)"
                  persistent-hint
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VDivider />

        <VCardActions class="px-6 py-4">
          <VSpacer />
          <VBtn
            color="grey-darken-1"
            variant="text"
            @click="isDialogOpen = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            variant="flat"
            :loading="store.loading"
            @click="submitReservation"
          >
            Confirmar Reserva
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Diálogo de horario fijo -->
    <VDialog v-model="isFixedScheduleDialogOpen" max-width="500px">
      <VCard>
        <VCardTitle class="bg-secondary text-white py-4">
          <span class="text-h6">🗓️ {{ editingFixedScheduleId ? 'Editar Horario Fijo' : 'Configurar Horario Fijo' }}</span>
        </VCardTitle>

        <VCardText class="py-6">
          <VForm ref="fixedScheduleFormRef">
            <VRow>
              <VCol cols="12">
                <VSelect
                  v-model="fixedScheduleForm.court_id"
                  :items="store.courtsData.map(c => ({ title: c.court.name, value: c.court.id }))"
                  label="Cancha"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required]"
                />
              </VCol>
              <VCol cols="12">
                <VSelect
                  v-model="fixedScheduleForm.day_of_week"
                  :items="daysOptions"
                  item-title="label"
                  item-value="value"
                  label="Día de la Semana"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required]"
                />
              </VCol>
              <VCol cols="12" sm="6">
                 <VSelect
                   v-model="fixedScheduleForm.start_time"
                   :items="[
                     '08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
                     '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30',
                     '16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00', '19:30',
                     '20:00', '20:30', '21:00', '21:30', '22:00', '22:30', '23:00'
                   ]"
                   label="Hora Inicio"
                   variant="outlined"
                   density="comfortable"
                   :rules="[rules.required]"
                 />
               </VCol>
               <VCol cols="12" sm="6">
                 <VSelect
                   v-model="fixedScheduleForm.duration"
                   :items="[
                     { title: '1 Hora', value: 1 },
                     { title: '1.5 Horas', value: 1.5 },
                     { title: '2 Horas', value: 2 }
                   ]"
                   label="Duración"
                   variant="outlined"
                   density="comfortable"
                 />
               </VCol>
               <VCol cols="12" sm="6">
                 <VTextField
                   :model-value="fixedScheduleForm.end_time"
                   label="Hora Fin"
                   readonly
                   disabled
                   variant="outlined"
                   density="comfortable"
                 />
               </VCol>
              <VCol cols="12">
                <VTextField
                  v-model="fixedScheduleForm.client_name"
                  label="Nombre del Cliente"
                  required
                  :rules="[rules.required]"
                  variant="outlined"
                  color="secondary"
                  density="comfortable"
                />
              </VCol>
              <VCol cols="12">
                <VTextField
                  v-model="fixedScheduleForm.client_whatsapp"
                  label="WhatsApp (ej. 584121234567)"
                  required
                  placeholder="584121234567"
                  :rules="[rules.required, rules.whatsapp]"
                  variant="outlined"
                  color="secondary"
                  density="comfortable"
                  hint="Ingresa el número de WhatsApp para contacto"
                  persistent-hint
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VDivider />

        <VCardActions class="px-6 py-4">
          <VSpacer />
          <VBtn
            color="grey-darken-1"
            variant="text"
            @click="isFixedScheduleDialogOpen = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="secondary"
            variant="flat"
            :loading="store.loading"
            @click="submitFixedSchedule"
          >
            Guardar Horario Fijo
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VContainer>
</template>

<style scoped>
.border-b {
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}
</style>
