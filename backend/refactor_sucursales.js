const fs = require('fs');
const path = require('path');

const directoryPath = 'c:/xampp/htdocs/wisdomdev/frontend/src';

const filesToProcess = [
  'VentaMovilVendedor.jsx',
  'VentaDesktopAdmin.jsx',
  'SeccionUsuarios.jsx',
  'RegistroMermasDevoluciones.jsx',
  'IngresoImportacion.jsx',
  'FormularioVentaMovil.jsx',
  'Dashboard.jsx'
];

filesToProcess.forEach(file => {
  const filePath = path.join(directoryPath, file);
  if (fs.existsSync(filePath)) {
    let content = fs.readFileSync(filePath, 'utf8');

    // 1. Reemplazar jerarquía dataA o resA o etc (Paises -> Ciudades -> Almacenes en vez de Ciudades -> Sucursales -> Almacenes)
    // Ejemplo: dataA.forEach(c => { if(c.sucursales) c.sucursales.forEach(s => { if(s.almacenes) alms.push(...) }) })
    content = content.replace(
      /dataA\.forEach\s*\(\s*([a-zA-Z0-9_]+)\s*=>\s*\{\s*if\s*\(\s*\1\.sucursales\s*\)\s*\{\s*\1\.sucursales\.forEach\s*\(\s*([a-zA-Z0-9_]+)\s*=>\s*\{\s*if\s*\(\s*\2\.almacenes\s*\)\s*alms\.push\(\.\.\.\2\.almacenes\);\s*\}\s*\);\s*\}\s*\}\s*\);/g,
      `dataA.forEach(pais => { if(pais.ciudades) pais.ciudades.forEach(c => { if(c.almacenes) alms.push(...c.almacenes); }); });`
    );

    // Variaciones manuales (RegistroMermas / IngresoImportacion):
    content = content.replace(
      /dataA\.forEach\s*\(\s*([a-zA-Z0-9_]+)\s*=>\s*\{\s*if\s*\(\s*\1\.sucursales\s*\)\s*\{\s*\1\.sucursales\.forEach\s*\(\s*([a-zA-Z0-9_]+)\s*=>\s*\{\s*if\s*\(\s*\2\.almacenes\s*\)\s*alms\.push\(\.\.\.\2\.almacenes\);\s*\}\s*\);\s*\}\s*\}\s*\);/g,
      `dataA.forEach(pais => { if(pais.ciudades) { pais.ciudades.forEach(c => { if(c.almacenes) alms.push(...c.almacenes); }); } });`
    );

    // 2. Reemplazos de estado y variables sucursal_id por ciudad_id
    content = content.replace(/sucursal_id/g, 'ciudad_id');
    content = content.replace(/sucursalId/g, 'ciudadId');
    content = content.replace(/setSucursalId/g, 'setCiudadId');
    content = content.replace(/sucursales/g, 'ciudades');
    content = content.replace(/Sucursales/g, 'Ciudades');
    content = content.replace(/sucursalSeleccionada/g, 'ciudadSeleccionada');
    content = content.replace(/setSucursalSeleccionada/g, 'setCiudadSeleccionada');
    content = content.replace(/sucursalesDisponibles/g, 'ciudadesDisponibles');
    content = content.replace(/setSucursalesDisponibles/g, 'setCiudadesDisponibles');
    content = content.replace(/sucursal/g, 'ciudad');
    content = content.replace(/Sucursal/g, 'Ciudad');
    content = content.replace(/SUCURSAL/g, 'CIUDAD');
    content = content.replace(/sucursalesSeleccionadas/g, 'ciudadesSeleccionadas');
    content = content.replace(/setSucursalesSeleccionadas/g, 'setCiudadesSeleccionadas');

    // Manejar SeccionUsuarios.jsx que tenía ciudades y sucursales (doble setCiudadesDisponibles)
    // El script va a generar variables repetidas si había ambas, pero luego lo arreglaré a mano.
    
    fs.writeFileSync(filePath, content, 'utf8');
    console.log(`Procesado ${file}`);
  }
});
