const fs = require('fs');
const html = fs.readFileSync('dealshare_grocery.html', 'utf8');

const startStr = '\\"dealDetailsList\\":[';
const endStr = '}],\\"fallbackSlots\\"';

const startIdx = html.indexOf(startStr);
if (startIdx !== -1) {
    const endIdx = html.indexOf(endStr, startIdx);
    if (endIdx !== -1) {
        let jsonStr = html.substring(startIdx + startStr.length - 1, endIdx + 2);
        // It should start with [ and end with ]
        jsonStr = jsonStr.replace(/\\"/g, '"').replace(/\\\\/g, '\\');
        // Let's test if it starts and ends correctly
        console.log("Starts with:", jsonStr.substring(0, 10));
        console.log("Ends with:", jsonStr.substring(jsonStr.length - 10));

        try {
            const products = JSON.parse(jsonStr);
            console.log('Found', products.length, 'products');

            const formatted = products.map((p, i) => ({
                id: (400 + i).toString(), // Making a unified ID sequence
                name: p.title,
                price: p.price,
                category: 'grocery',
                image: p.image.replace('?tr=f-webp', ''), // Optional, cleaning up URL
                rating: 4.5,
                discount: parseInt(p.offPercentage) || 0,
                inStock: true
            }));

            fs.writeFileSync('dealshare_extracted.json', JSON.stringify(formatted, null, 2));
            console.log('Saved to dealshare_extracted.json');
        } catch (e) {
            console.error('JSON Parse Error:', e.message);
        }
    } else {
        console.log('endStr not found');
    }
} else {
    console.log('startStr not found');
}
