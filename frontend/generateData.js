import fs from 'fs';

// Read extracted products
const newProducts = JSON.parse(fs.readFileSync('dealshare_extracted.json', 'utf-8'));

// Format to Typescript
let content = `export interface Product {
  id: string;
  name: string;
  price: number;
  category: string;
  image: string;
  rating: number;
  discount: number;
  inStock: boolean;
}

export const mockProducts: Product[] = ${JSON.stringify(newProducts, null, 2)};
`;

fs.mkdirSync('./src/data', { recursive: true });
fs.writeFileSync('./src/data/mockProducts.ts', content);
console.log('Created src/data/mockProducts.ts');
