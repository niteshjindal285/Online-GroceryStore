import https from 'https';
import fs from 'fs';

const urls = [
    { url: 'https://www.dealshare.in/cln/collection/6180?categoryName=Collection', category: 'collection-6180' },
    { url: 'https://www.dealshare.in/cln/collection/6181?categoryName=Collection', category: 'collection-6181' },
    { url: 'https://www.dealshare.in/cln/collection/6178?categoryName=Collection', category: 'collection-6178' },
    { url: 'https://www.dealshare.in/cln/collection/6179?categoryName=Collection', category: 'collection-6179' },
    { url: 'https://www.dealshare.in/cln/collection/6182?categoryName=Collection', category: 'collection-6182' },
    { url: 'https://www.dealshare.in/cln/collection/6183?categoryName=Collection', category: 'collection-6183' },
    { url: 'https://www.dealshare.in/cln/collection/6184?categoryName=Collection', category: 'collection-6184' },
    { url: 'https://www.dealshare.in/cln/collection/6186?categoryName=Collection', category: 'collection-6186' },
    { url: 'https://www.dealshare.in/cln/collection/6187?categoryName=Collection', category: 'collection-6187' },
    { url: 'https://www.dealshare.in/cln/collection/6188?categoryName=Collection', category: 'collection-6188' },
    { url: 'https://www.dealshare.in/category/cn/personal-care/oral-care/cid/499?categoryName=Oral%20Care', category: 'personal-care' },
    { url: 'https://www.dealshare.in/cln/collection/6190?categoryName=Collection', category: 'collection-6190' },
    { url: 'https://www.dealshare.in/cln/collection/6189?categoryName=Collection', category: 'collection-6189' },
    { url: 'https://www.dealshare.in/category/cn/cleaning-household-care/detergents-fabric-care/cid/1155?categoryName=Detergents%20Fabric%20Care', category: 'cleaning-home-care' },
    { url: 'https://www.dealshare.in/cln/collection/6191?categoryName=Collection', category: 'collection-6191' }
];

const options = {
    headers: {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.127 Safari/537.36',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8'
    }
};

function fetchHTML(url) {
    return new Promise((resolve, reject) => {
        https.get(url, options, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => resolve(data));
        }).on('error', err => reject(err));
    });
}

function normalizeCategory(urlStr, givenCat) {
    let mapped = 'grocery';
    if (urlStr.includes('oral-care') || urlStr.includes('personal-care')) mapped = 'personal-care';
    else if (urlStr.includes('detergents-fabric-care') || urlStr.includes('cleaning-household-care')) mapped = 'cleaning-home-care';
    else mapped = 'grocery';
    return mapped;
}

async function scrape() {
    let allProducts = [];
    let idCounter = 300;

    console.log('Starting mass extraction...');
    for (const item of urls) {
        console.log(`Fetching ${item.url}...`);
        try {
            const html = await fetchHTML(item.url);

            const titleMatches = [...html.matchAll(/\\\"title\\\":\\\"([^\\\"]+)\\\"/g)];
            const priceMatches = [...html.matchAll(/\\\"price\\\":(\d+)/g)];
            const mrpMatches = [...html.matchAll(/\\\"mrp\\\":(\d+)/g)];
            const imgMatches = [...html.matchAll(/\\\"image\\\":\\\"([^\\\"]+)\\\"/g)];

            let count = Math.min(titleMatches.length, priceMatches.length, imgMatches.length);

            let mappedCategory = normalizeCategory(item.url, item.category);

            let foundForUrl = 0;
            for (let i = 0; i < count; i++) {
                let name = titleMatches[i][1];

                if (name.includes(' Products') || name.includes('Deals') || name.includes('Category')) {
                    continue;
                }

                if (!allProducts.find(ext => ext.name === name)) {
                    let price = parseInt(priceMatches[i]?.[1] || "100");
                    let mrp = parseInt(mrpMatches[i]?.[1] || "150");

                    if (mrp < price) mrp = price + 20;

                    let originalPrice = mrp;
                    let discount = Math.round(((originalPrice - price) / originalPrice) * 100) || 0;
                    let image = imgMatches[i]?.[1] || 'https://media.dealshare.in/img/no-image.jpg';

                    if (!image.startsWith('http')) {
                        image = 'https://media.dealshare.in/img/offer/' + image;
                    }

                    name = name.replace(/\\\\u0026/g, '&');

                    allProducts.push({
                        id: idCounter.toString(),
                        name: name.substring(0, 100),
                        price: price,
                        category: mappedCategory,
                        image: image,
                        rating: (Math.random() * (5.0 - 4.0) + 4.0).toFixed(1), // Mock rating
                        discount: discount,
                        inStock: true
                    });
                    idCounter++;
                    foundForUrl++;
                }
            }
            console.log(`Added ${foundForUrl} new products out of this batch.`);
        } catch (e) {
            console.error(`Error processing ${item.category}:`, e.message);
        }
    }

    console.log(`Total unique products extracted: ${allProducts.length}`);
    fs.writeFileSync('dealshare_extracted.json', JSON.stringify(allProducts, null, 2));
    console.log('Saved to dealshare_extracted.json');
}

scrape();
