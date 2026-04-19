import { mockProducts } from './src/data/mockProducts.ts';
import axios from 'axios';

// const SEED_URL = 'https://grocery-backend-s54s.onrender.com/api/products';
const SEED_URL = 'http://localhost:5000/api/products'; // Use localhost if testing locally

async function seedDatabase() {
    console.log(`Starting to seed ${mockProducts.length} products to ${SEED_URL}`);
    let successCount = 0;
    let failCount = 0;

    for (const product of mockProducts) {
        try {
            // Format payload according to the new backend schema
            const payload = {
                name: product.name,
                description: `Fresh ${product.category} product`,
                price: product.price,
                image: product.image,
                category: product.category,
                rating: typeof product.rating === 'string' ? parseFloat(product.rating) : product.rating,
                discount: product.discount || 0,
                inStock: product.inStock,
                countInStock: 100 // default value
            };

            await axios.post(SEED_URL, payload);
            successCount++;
            process.stdout.write(`\rProgress: ${successCount + failCount}/${mockProducts.length}`);
        } catch (err) {
            console.error(`\nFailed to seed product: ${product.name}`, err.message);
            failCount++;
        }
    }

    console.log(`\n\nSeeding Complete!`);
    console.log(`Success: ${successCount}`);
    console.log(`Failed: ${failCount}`);
}

seedDatabase();
