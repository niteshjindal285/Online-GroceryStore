const fs = require('fs');

const html = fs.readFileSync('dealshare_grocery.html', 'utf8');
const match = html.match(/"dealDetailsList":(\[.*?\]),"fallbackSlots"/);

if (match) {
    try {
        let jsonStr = match[1];
        // The data is inside a JS string like "f:[\"$\",...,{\"dealDetailsList\":[...
        // so quotes are escaped as \" 
        jsonStr = jsonStr.replace(/\\"/g, '"');
        const products = JSON.parse(jsonStr);
        console.log('Found', products.length, 'products');

        const formatted = products.map(p => ({
            id: p.id.toString(),
            name: p.title,
            price: p.price,
            category: 'grocery', // Assigning default category
            image: p.image,
            rating: 4.5,
            discount: parseInt(p.offPercentage) || 0,
            inStock: true
        }));

        fs.writeFileSync('dealshare_extracted.json', JSON.stringify(formatted, null, 2));
        console.log('Saved to dealshare_extracted.json');
    } catch (e) {
        console.error('Error parsing:', e.message);
    }
} else {
    console.log('dealDetailsList not found');
}
