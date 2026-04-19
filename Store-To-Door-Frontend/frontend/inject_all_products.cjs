const fs = require('fs');

const extractedStr = fs.readFileSync('dealshare_extracted_all.json', 'utf8');
const allProducts = JSON.parse(extractedStr);

const previousExtractedStr = fs.readFileSync('dealshare_extracted.json', 'utf8');
const previousProducts = JSON.parse(previousExtractedStr);

// Find names of previously added products
const previousNames = new Set(previousProducts.map(p => p.name));

// Filter out products we already added
let newProducts = allProducts.filter(p => !previousNames.has(p.name));

// Deduplicate new products by name
const seenNames = new Set();
newProducts = newProducts.filter(p => {
    if (seenNames.has(p.name)) return false;
    seenNames.add(p.name);
    return true;
});

let mockFile = fs.readFileSync('./src/data/mockProducts.ts', 'utf8');
const insertionPoint = mockFile.indexOf('export const mockProducts: Product[] = [') + 'export const mockProducts: Product[] = ['.length;

if (insertionPoint > 'export const mockProducts: Product[] = ['.length - 1) {
    const newProductsString = newProducts.map(p => JSON.stringify(p, null, 4)).join(',\n') + (newProducts.length > 0 ? ',' : '');

    const newMockFile = mockFile.substring(0, insertionPoint) + '\n' + newProductsString + mockFile.substring(insertionPoint);
    fs.writeFileSync('./src/data/mockProducts.ts', newMockFile);
    console.log('Injected', newProducts.length, 'new unique products into mockProducts.ts');
} else {
    console.log('Could not find injection point');
}
