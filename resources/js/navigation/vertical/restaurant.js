export default [
  {
    title: 'Restaurante',
    icon: { icon: 'tabler-tools-kitchen-2' },
    children: [
      {
        title: 'Mesas',
        to: 'restaurant-floor-map',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Comandas',
        to: 'restaurant-kds',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Menú',
        to: 'inventory-dishes',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Producción',
        to: 'restaurant-production-and-waste',
        action: 'manage',
        subject: 'user',
      },
      {
        title: 'Mermas',
        to: 'restaurant-waste',
        action: 'manage',
        subject: 'user',
      },
      {
        title: 'Modificadores',
        to: 'restaurant-modifier-groups',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Estaciones',
        to: 'restaurant-stations',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Tiempos de Servicio',
        to: 'restaurant-process-audit',
        action: 'manage',
        subject: 'admin',
      },
    ],
  },
]
