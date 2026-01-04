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
    children: [
      {
        title: 'General',
        to: 'configuration',
      },
    ],
  }, 
]
