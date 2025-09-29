export default [
  {
    title: 'Productividad',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'boxes-stacked'],
        size: 'sm',
      },
    },
    children: [
      {
        title: 'General',
        to: 'productivity-general',
      },
      {
        title: 'Tareas de Limpieza',
        to: 'productivity-cleaning',
      },
      {
        title: 'Laboratorios Por Empleados',
        to: 'productivity-laboratory',
      },
      {
        title: 'Productos Por Empleados',
        to: 'productivity-product',
      }
    ],
  }, 
]
