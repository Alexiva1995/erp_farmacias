export default [
  {
    title: 'CRM',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'address-book'],
        size: 'sm',
      },
    },
    action: 'manage', 
    subject: 'admin',
    children: [
      {
        title: 'Clientes',
        to: 'crm-clients',
      },
      {
        title: 'Convenios',
        to: 'crm-companies',
      },
      {
        title: 'Médicos',
        to: 'crm-doctors',
      },
      {
        title: 'Sorteo',
        to: 'crm-lottery',
      }
    ],
  },
]
