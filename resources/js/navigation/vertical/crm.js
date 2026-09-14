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
    children: [
      {
        title: 'Clientes',
        to: 'crm-clients',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Pacientes',
        children: [
          {
            title: 'Fidelización',
            to: 'crm-chronic-clients',
          },
          {
            title: 'Cuota Fidelización',
            to: 'crm-chronic-clients-quota',
            action: 'manage',
            subject: 'supervisor-or-admin',
          },
          {
            title: 'Clasificación',
            to: 'crm-chronic-clients-classification',
            action: 'manage',
            subject: 'admin',
          },
        ],
      },
      {
        title: 'Convenios',
        to: 'crm-companies',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Médicos',
        to: 'crm-doctors',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Sorteo',
        to: 'crm-lottery',
        action: 'manage',
        subject: 'admin',
      }
    ],
  },
]
