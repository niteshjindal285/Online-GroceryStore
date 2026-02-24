import fs from 'fs';
import path from 'path';

const mockProductsPath = path.join(process.cwd(), 'src/data/mockProducts.ts');

const content = fs.readFileSync(mockProductsPath, 'utf8');

const regex = /export const mockProducts: Product\[\] = (\[[\s\S]*\]);/;
const match = content.match(regex);

if (!match) {
    console.error("Could not find mockProducts array");
    process.exit(1);
}

const mockProductsStr = match[1];

let products;
try {
    // We use eval because the string contains JS objects without quotes around keys sometimes, though here it might be proper JSON.
    // Actually, examining mockProducts.ts shows it's standard JS array of objects. We'll use a safer Function constructor approach.
    products = new Function(`return ${mockProductsStr};`)();
} catch (e) {
    console.error("Failed to parse array", e);
    process.exit(1);
}

const mapCategory = (name) => {
    const n = name.toLowerCase();

    if (n.includes('dal') || n.includes('chana') || n.includes('moong') || n.includes('masoor') || n.includes('urad') || n.includes('rajma') || n.includes('lobia')) return 'dals-pulses';
    if (n.includes('rice') || n.includes('basmati') || n.includes('poha')) return 'rice-products';
    if (n.includes('tea') || n.includes('coffee') || n.includes('drink') || n.includes('rooh afza') || n.includes('sprite') || n.includes('dew') || n.includes('limca') || n.includes('maaza') || n.includes('frooti') || n.includes('cola') || n.includes('beverage') || n.includes('horlicks') || n.includes('bournvita')) return 'beverages';
    if (n.includes('oil') || n.includes('mustard') || n.includes('soyabean') || n.includes('sunflower')) return 'cooking-oil';
    if (n.includes('ghee') || n.includes('vanaspati')) return 'ghee-vanaspati';
    if (n.includes('sugar') || n.includes('salt') || n.includes('jaggery') || n.includes('gur') || n.includes('cheeni') || n.includes('namak')) return 'sugar-salt-jaggery';
    if (n.includes('almond') || n.includes('badam') || n.includes('cashew') || n.includes('kaju') || n.includes('raisin') || n.includes('kishmis') || n.includes('makhana') || n.includes('walnut') || n.includes('pista') || n.includes('dates')) return 'dry-fruits-nuts';
    if (n.includes('atta') || n.includes('flour') || n.includes('maida') || n.includes('besan') || n.includes('suji') || n.includes('sooji') || n.includes('oats') || n.includes('dalia') || n.includes('chakki')) return 'flours-grains';
    if (n.includes('spice') || n.includes('masala') || n.includes('mirchi') || n.includes('jeera') || n.includes('turmeric') || n.includes('coriander') || n.includes('haldi') || n.includes('dhaniya') || n.includes('pepper') || n.includes('cardamom') || n.includes('elaichi') || n.includes('clove') || n.includes('laung') || n.includes('hing') || n.includes('cumin')) return 'spices-herbs';
    if (n.includes('clean') || n.includes('wash') || n.includes('detergent') || n.includes('soap') || n.includes('vim') || n.includes('surf') || n.includes('tide') || n.includes('ariel') || n.includes('lizol') || n.includes('harpic') || n.includes('colin') || n.includes('broom') || n.includes('mop') || n.includes('phenyl') || n.includes('nirma') || n.includes('rin') || n.includes('wheel')) return 'cleaning-home-care';
    if (n.includes('shampoo') || n.includes('conditioner') || n.includes('hair') || n.includes('face') || n.includes('skin') || n.includes('body') || n.includes('lotion') || n.includes('cream') || n.includes('paste') || n.includes('toothpaste') || n.includes('brush') || n.includes('colgate') || n.includes('pepsodent') || n.includes('dabur') || n.includes('santoor') || n.includes('lux') || n.includes('lifebuoy') || n.includes('dettol') || n.includes('pears') || n.includes('dove') || n.includes('deo') || n.includes('perfume') || n.includes('powder') || n.includes('sanitary') || n.includes('pad') || n.includes('whisper') || n.includes('stayfree') || n.includes('diaper') || n.includes('pampers') || n.includes('huggies') || n.includes('gillette') || n.includes('razor') || n.includes('shaving') || n.includes('oral')) return 'personal-care';
    if (n.includes('fruit') || n.includes('vegetable') || n.includes('veggie') || n.includes('apple') || n.includes('banana') || n.includes('mango') || n.includes('onion') || n.includes('potato') || n.includes('tomato')) return 'fruits-veggies';

    return 'grocery';
};

let modifiedCount = 0;
for (let p of products) {
    const newCat = mapCategory(p.name);
    if (p.category !== newCat) {
        p.category = newCat;
        modifiedCount++;
    }
}

if (modifiedCount > 0) {
    const newProductsStr = JSON.stringify(products, null, 2);
    const newContent = content.replace(regex, `export const mockProducts: Product[] = ${newProductsStr};`);
    fs.writeFileSync(mockProductsPath, newContent, 'utf8');
    console.log(`Updated ${modifiedCount} products categories.`);
} else {
    console.log('No updates needed.');
}
