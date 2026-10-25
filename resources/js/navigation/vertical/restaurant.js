export default [
  {
    title: 'Restaurante',
    icon: { icon: 'tabler-tools-kitchen-2' },
    action: 'manage',
    subject: 'admin',
    children: [
      {
        title: 'Salón y Mesas (3D)',
        to: 'restaurant-floor-map',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Cocina (KDS)',
        to: 'restaurant-kds',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Producción & Mermas',
        to: 'restaurant-production-and-waste',
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
