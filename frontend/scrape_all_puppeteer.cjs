const puppeteer = require('puppeteer');
const fs = require('fs');

(async () => {
    const browser = await puppeteer.launch({ headless: 'new' });
    const page = await browser.newPage();

    // Set viewport to a typical desktop size
    await page.setViewport({ width: 1280, height: 800 });

    console.log('Navigating to DealShare...');
    await page.goto('https://www.dealshare.in/category/cn/grocery/cid/719', {
        waitUntil: 'networkidle2',
        timeout: 60000
    });

    console.log('Scrolling to load all products...');
    let previousHeight = 0;
    let productsCount = 0;
    let unchangedCount = 0;

    while (unchangedCount < 5) {
        // Evaluate products count
        const currentCount = await page.evaluate(() => {
            return document.querySelectorAll('.BauDealCard_cardContainer__YkCMh').length;
        });

        if (currentCount > productsCount) {
            productsCount = currentCount;
            unchangedCount = 0;
            console.log(`Loaded ${productsCount} products so far...`);

            // Wait for images or new items to render
            await new Promise(r => setTimeout(r, 1000));
        } else {
            unchangedCount++;
            await new Promise(r => setTimeout(r, 1000));
        }

        // Scroll down
        previousHeight = await page.evaluate('document.body.scrollHeight');
        await page.evaluate('window.scrollTo(0, document.body.scrollHeight)');
    }

    console.log(`Finished scrolling. Extracting ${productsCount} products...`);

    const products = await page.evaluate(() => {
        const items = [];
        const cards = document.querySelectorAll('.BauDealCard_cardContainer__YkCMh');
        cards.forEach((el) => {
            const id = el.id || el.getAttribute('id');
            const nameEl = el.querySelector('.BauDealCard_cardTitleContainer___Qh3b');
            const priceEl = el.querySelector('.BauDealCard_priceText__bt1qV');
            const mrpEl = el.querySelector('.BauDealCard_mrpText__0OFVz');
            const imgEl = el.querySelector('.BauDealCard_cardImage__mItG_');
            const offEl = el.querySelector('.BauDealCard_offTag__722c_');

            if (!nameEl || !priceEl) return;

            const name = nameEl.innerText.trim();
            const price = parseInt(priceEl.innerText.replace(/[^0-9]/g, '')) || 0;

            let image = imgEl ? imgEl.getAttribute('src') : '';
            if (image && image.includes('?')) {
                image = image.split('?')[0];
            }

            let discount = 0;
            if (offEl) {
                discount = parseInt(offEl.innerText.replace(/[^0-9]/g, '')) || 0;
            }

            items.push({
                id: id || Date.now().toString() + Math.floor(Math.random() * 1000),
                name: name,
                price: price,
                category: 'grocery',
                image: image,
                rating: parseFloat((4.0 + Math.random() * 0.9).toFixed(1)),
                discount: discount,
                inStock: true
            });
        });
        return items;
    });

    console.log(`Extracted ${products.length} products`);
    fs.writeFileSync('dealshare_extracted_all.json', JSON.stringify(products, null, 2));

    await browser.close();
})();
