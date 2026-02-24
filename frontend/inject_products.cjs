const fs = require('fs');

const extractedStr = fs.readFileSync('dealshare_extracted.json', 'utf8');
const newProducts = JSON.parse(extractedStr);

let mockFile = fs.readFileSync('./src/data/mockProducts.ts', 'utf8');

const insertionPoint = mockFile.indexOf('export const mockProducts: Product[] = [') + 'export const mockProducts: Product[] = ['.length;

if (insertionPoint > 'export const mockProducts: Product[] = ['.length - 1) {
    // Generate the string representation
    const newProductsString = newProducts.map(p => JSON.stringify(p, null, 4)).join(',\n') + ',';

    const newMockFile = mockFile.substring(0, insertionPoint) + '\n' + newProductsString + mockFile.substring(insertionPoint);
    fs.writeFileSync('./src/data/mockProducts.ts', newMockFile);
    console.log('Injected', newProducts.length, 'products into mockProducts.ts');
} else {
    console.log('Could not find injection point');
}
