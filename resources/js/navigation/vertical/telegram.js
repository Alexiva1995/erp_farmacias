export default [
  {
    title: 'Telegram',
    icon: { icon: 'tabler-brand-telegram' },
    action: 'manage',
    subject: 'admin',
    children: [
      {
        title: 'Configuraciones',
        to: 'telegram-configuration',
      },
      {
        title: 'Generales',
        to: 'telegram-generales',
      },
      {
        title: 'Farmacia',
        to: 'telegram-farmacia',
      },
      {
        title: 'Restaurante',
        to: 'telegram-restaurante',
      },
      {
        title: 'Cosméticos',
        to: 'telegram-cosmeticos',
      },
      {
        title: 'Alquileres',
        to: 'telegram-alquileres',
      },
    ],
  },
]
