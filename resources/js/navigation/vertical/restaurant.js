export default [
  {
    title: 'Restaurante',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'utensils'],
        size: 'sm',
      },
    },
    action: 'manage',
    subject: 'admin',
    children: [
      {
        title: 'Cocina (KDS)',
        to: 'restaurant-kds',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Platos / Menú',
        to: 'inventory-dishes',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Estaciones de Cocina',
        to: 'restaurant-stations',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Auditoría de Procesos',
        to: 'restaurant-process-audit',
        action: 'manage',
        subject: 'admin',
      },
    ],
  },
]
