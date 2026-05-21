export default [
  {
    title: 'Configuración',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'gear'],
        size: 'sm',
      },
    },
    action: 'manage',
    subject: 'admin',
    children: [
      {
        title: 'General',
        to: 'configuration',
      },
      {
        title: 'Personalización',
        to: 'configuration-branding',
      },
    ],
  }, 
]
