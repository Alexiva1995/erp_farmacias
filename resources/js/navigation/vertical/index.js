import crm from './crm'
import inventory from './inventory'
import lot from './lot'
import tpv from './tpv'

// Se han combinado los módulos de ambas ramas.
// Ahora se exportan 'inventory', 'lot', 'tpv' y 'crm'.
export default [...inventory, ...lot, ...tpv, ...crm]