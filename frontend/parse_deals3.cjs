const fs = require('fs');
const html = fs.readFileSync('dealshare_grocery.html', 'utf8');

const startStr = '\\"dealDetailsList\\":[';
const endStr = '],\\"fallbackSlots\\"';

const startIdx = html.indexOf(startStr);
if (startIdx !== -1) {
    const endIdx = html.indexOf(endStr, startIdx);
    if (endIdx !== -1) {
        let jsonStr = html.substring(startIdx + startStr.length - 1, endIdx + 1);
        jsonStr = jsonStr.replace(/\\"/g, '"').replace(/\\\\/g, '\\');
        const products = JSON.parse(jsonStr);
        console.log('Found', products.length, 'products');

        const formatted = products.map(p => ({
            id: p.id.toString(),
            name: p.title,
            price: p.price,
            category: 'grocery',
            image: p.image,
            rating: 4.5,
            discount: parseInt(p.offPercentage) || 0,
            inStock: true
        }));

        fs.writeFileSync('dealshare_extracted.json', JSON.stringify(formatted, null, 2));
        console.log('Saved to dealshare_extracted.json');
    } else {
        console.log('endStr not found');
    }
} else {
    console.log('startStr not found');
}
