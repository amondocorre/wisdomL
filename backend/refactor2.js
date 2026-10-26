const fs = require('fs');
const path = require('path');
const directoryPath = 'c:/xampp/htdocs/wisdomdev/frontend/src';

const files = [
  'VentaMovilVendedor.jsx',
  'VentaDesktopAdmin.jsx',
  'RegistroMermasDevoluciones.jsx',
  'IngresoImportacion.jsx'
];

files.forEach(file => {
  const filePath = path.join(directoryPath, file);
  if (fs.existsSync(filePath)) {
    let content = fs.readFileSync(filePath, 'utf8');
    content = content.replace(/setAlmacenes\(dataA\);/g, 'setAlmacenes(alms);');
    fs.writeFileSync(filePath, content, 'utf8');
  }
});
